<?php

namespace App\Services;

use App\Models\License;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ₹1 → 7-day trial → ₹599 every month by Autopay (Razorpay Subscriptions), with no manual work:
 * - start(): creates the Razorpay subscription (₹1 add-on now, first ₹599 charge after the trial)
 * - authenticated(): ₹1 paid + mandate given → license until the trial ends
 * - charged(): each monthly charge → license until the paid period ends (+ grace days)
 * Licenses are only ever extended, so browser callback and webhooks can arrive in any order or twice.
 */
class SubscriptionManager
{
    public const GRACE_DAYS = 2; // bank retries can take a day or two

    public function __construct(private Razorpay $razorpay, private LicenseIssuer $issuer) {}

    public static function trialDays(): int
    {
        return max(0, (int) Setting::get('trial_days'));
    }

    public static function trialPaise(): int
    {
        return (int) round((float) Setting::get('trial_price') * 100);
    }

    public static function monthlyPaise(): int
    {
        return (int) round((float) Setting::get('monthly_price') * 100);
    }

    /** One trial per school account and per computer. */
    public function trialAvailable(User $user, string $installationId): bool
    {
        if (self::trialDays() === 0 || self::trialPaise() <= 0) {
            return false;
        }

        return ! Subscription::whereIn('status', Subscription::STARTED)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('installation_id', $installationId))
            ->exists()
            && ! License::where('installation_id', $installationId)->exists();
    }

    public function liveSubscription(string $installationId): ?Subscription
    {
        return Subscription::where('installation_id', $installationId)->whereIn('status', ['authenticated', 'active', 'pending'])->latest('id')->first();
    }

    public function start(User $user, string $installationId): Subscription
    {
        $trial = $this->trialAvailable($user, $installationId);
        $planId = $this->razorpay->monthlyPlanId(self::monthlyPaise(), 'TDS School Management Software - Monthly');

        $rzp = $this->razorpay->createSubscription(
            $planId,
            $trial ? now()->addDays(self::trialDays())->timestamp : null,
            $trial ? self::trialPaise() : 0,
            self::trialDays().'-day trial',
            ['installation_id' => $installationId, 'user_id' => (string) $user->id, 'school' => mb_substr($user->school_name, 0, 200)],
        );

        return Subscription::create([
            'user_id' => $user->id,
            'installation_id' => $installationId,
            'razorpay_subscription_id' => $rzp['id'],
            'razorpay_plan_id' => $planId,
            'monthly_amount' => self::monthlyPaise(),
            'trial_amount' => $trial ? self::trialPaise() : 0,
        ]);
    }

    /** ₹1 paid and Autopay set up. */
    public function authenticated(string $subscriptionId): ?Subscription
    {
        return DB::transaction(function () use ($subscriptionId) {
            $sub = Subscription::where('razorpay_subscription_id', $subscriptionId)->lockForUpdate()->first();
            if (! $sub) {
                return null;
            }
            if ($sub->status === 'created') {
                $sub->status = 'authenticated';
            }
            if ($sub->trial_amount > 0) {
                $sub->trial_ends_at ??= now()->addDays(self::trialDays());
                $this->extendLicense($sub, $sub->trial_ends_at, 'trial');
            } else {
                // no trial: the first month is charged with the authorisation
                $this->extendLicense($sub, now()->addMonthNoOverflow()->addDays(self::GRACE_DAYS), 'monthly');
            }
            $sub->save();

            return $sub;
        });
    }

    /** A monthly payment succeeded. $currentEnd = end of the paid period (unix time from Razorpay). */
    public function charged(string $subscriptionId, ?int $currentEnd): ?Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $currentEnd) {
            $sub = Subscription::where('razorpay_subscription_id', $subscriptionId)->lockForUpdate()->first();
            if (! $sub) {
                return null;
            }
            $end = $currentEnd ? Carbon::createFromTimestamp($currentEnd) : now()->addMonthNoOverflow();
            $sub->fill(['status' => 'active', 'current_end' => $end, 'paid_count' => $sub->paid_count + 1])->save();
            $this->extendLicense($sub, $end->copy()->addDays(self::GRACE_DAYS), 'monthly');

            return $sub;
        });
    }

    public function statusChanged(string $subscriptionId, string $status): void
    {
        $sub = Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
        if ($sub && in_array($status, ['pending', 'halted', 'cancelled', 'completed', 'active'], true)) {
            $sub->update(['status' => $status] + ($status === 'cancelled' ? ['cancelled_at' => now()] : []));
        }
    }

    public function cancel(Subscription $sub): void
    {
        if ($sub->isLive()) {
            $this->razorpay->cancelSubscription($sub->razorpay_subscription_id);
        }
        $sub->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    /** Issue a new key only if it lasts longer than the newest key this computer already has. */
    private function extendLicense(Subscription $sub, Carbon $until, string $plan): void
    {
        $current = $this->issuer->currentExpiry($sub->installation_id);
        if ($current === 'life' || ($current instanceof Carbon && $current->gte($until->copy()->startOfDay()))) {
            return;
        }
        $this->issuer->issue($sub->installation_id, $sub->user->school_name, $plan, 'online', $sub->user_id, null,
            'Subscription '.$sub->razorpay_subscription_id, $until->toDateString());
    }
}
