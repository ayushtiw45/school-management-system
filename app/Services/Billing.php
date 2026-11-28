<?php

namespace App\Services;

use App\Models\PlatformSetting as P;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * ₹1 → 7-day trial → ₹599/month by Autopay, fully automatic:
 *  start()         creates the Razorpay subscription (₹1 add-on now, first ₹599 after the trial)
 *  authenticated() ₹1 paid + Autopay approved → school unlocked until the trial ends
 *  charged()       every monthly payment → unlocked until the paid period ends (+ grace days)
 * Access is only ever extended, so the browser callback and webhooks may arrive in any order or twice.
 */
class Billing
{
    public const GRACE_DAYS = 2;

    public function __construct(private Razorpay $razorpay) {}

    public static function trialDays(): int
    {
        return max(0, (int) P::get('trial_days'));
    }

    public static function trialPaise(): int
    {
        return (int) round((float) P::get('trial_price') * 100);
    }

    public static function monthlyPaise(): int
    {
        return (int) round((float) P::get('monthly_price') * 100);
    }

    public function ready(): bool
    {
        return $this->razorpay->configured() && self::monthlyPaise() > 0;
    }

    /** The ₹1 trial is available once per school. */
    public function trialAvailable(School $school): bool
    {
        return ! $school->trial_used && ! $school->paid_until && self::trialDays() > 0 && self::trialPaise() > 0
            && ! $school->subscriptions()->whereIn('status', Subscription::STARTED)->exists();
    }

    public function start(School $school): Subscription
    {
        $trial = $this->trialAvailable($school);
        $planId = $this->razorpay->monthlyPlanId(self::monthlyPaise(), P::get('company_name').' - Monthly');

        $rzp = $this->razorpay->createSubscription(
            $planId,
            $trial ? now()->addDays(self::trialDays())->timestamp : null,
            $trial ? self::trialPaise() : 0,
            self::trialDays().'-day trial',
            ['school_id' => (string) $school->id, 'school' => mb_substr($school->name, 0, 200)],
        );

        return $school->subscriptions()->create([
            'razorpay_subscription_id' => $rzp['id'],
            'razorpay_plan_id' => $planId,
            'monthly_amount' => self::monthlyPaise(),
            'trial_amount' => $trial ? self::trialPaise() : 0,
        ]);
    }

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
            $school = $sub->school;
            if ($sub->trial_amount > 0) {
                $sub->trial_ends_at ??= now()->addDays(self::trialDays());
                $school->trial_used = true;
                $school->extendUntil($sub->trial_ends_at);
            } else {
                // no trial: the first month is charged with the authorisation
                $school->extendUntil(now()->addMonthNoOverflow()->addDays(self::GRACE_DAYS));
            }
            $sub->save();

            return $sub;
        });
    }

    public function charged(string $subscriptionId, ?int $currentEnd): ?Subscription
    {
        return DB::transaction(function () use ($subscriptionId, $currentEnd) {
            $sub = Subscription::where('razorpay_subscription_id', $subscriptionId)->lockForUpdate()->first();
            if (! $sub) {
                return null;
            }
            $end = $currentEnd ? \Carbon\Carbon::createFromTimestamp($currentEnd) : now()->addMonthNoOverflow();
            $sub->fill(['status' => 'active', 'current_end' => $end, 'paid_count' => $sub->paid_count + 1])->save();
            $sub->school->extendUntil($end->copy()->addDays(self::GRACE_DAYS));

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
}
