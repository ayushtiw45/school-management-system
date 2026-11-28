<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
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

    /**
     * Can this school use this code now? Returns the coupon, or throws with a plain message.
     */
    public function coupon(?string $code, School $school): ?Coupon
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }
        $c = Coupon::where('code', $code)->first();
        $fail = fn (string $m) => throw new \RuntimeException($m);
        if (! $c || ! $c->is_active) {
            $fail('This coupon code is not valid.');
        }
        if ($c->valid_from && $c->valid_from->isFuture()) {
            $fail('This coupon can be used from '.$c->valid_from->format('d M Y').'.');
        }
        if ($c->valid_until && $c->valid_until->copy()->endOfDay()->isPast()) {
            $fail('This coupon has expired.');
        }
        if ($c->max_uses !== null && $c->used_count >= $c->max_uses) {
            $fail('This coupon has already been used the maximum number of times.');
        }
        if ($c->school_id && $c->school_id !== $school->id) {
            $fail('This coupon is not for your school.');
        }
        if ($c->new_schools_only && ($school->paid_until || $school->subscriptions()->where('paid_count', '>', 0)->exists())) {
            $fail('This coupon is only for new schools.');
        }
        if (CouponRedemption::where('coupon_id', $c->id)->where('school_id', $school->id)->exists()) {
            $fail('Your school has already used this coupon.');
        }

        return $c;
    }

    /** What the school would pay with a coupon (for the billing page). */
    public function quote(School $school, ?Coupon $coupon): array
    {
        $list = self::monthlyPaise();
        $monthly = $coupon ? $coupon->apply($list) : $list;
        $trial = $this->trialAvailable($school);
        $extra = $coupon?->extra_days ?? 0;

        return ['list' => $list, 'monthly' => $monthly, 'trial' => $trial, 'trial_paise' => $trial ? self::trialPaise() : 0,
            'free_days' => ($trial ? self::trialDays() : 0) + $extra, 'extra_days' => $extra];
    }

    public function start(School $school, ?Coupon $coupon = null): Subscription
    {
        $q = $this->quote($school, $coupon);
        $planId = $this->razorpay->monthlyPlanId($q['monthly'], P::get('company_name').' - Monthly'.($q['monthly'] !== $q['list'] ? ' (₹'.($q['monthly'] / 100).')' : ''));

        $rzp = $this->razorpay->createSubscription(
            $planId,
            $q['free_days'] > 0 ? now()->addDays($q['free_days'])->timestamp : null,
            $q['trial_paise'],
            self::trialDays().'-day trial',
            ['school_id' => (string) $school->id, 'school' => mb_substr($school->name, 0, 200)] + ($coupon ? ['coupon' => $coupon->code] : []),
        );

        return $school->subscriptions()->create([
            'razorpay_subscription_id' => $rzp['id'],
            'razorpay_plan_id' => $planId,
            'monthly_amount' => $q['monthly'],
            'trial_amount' => $q['trial_paise'],
            'coupon_id' => $coupon?->id,
            'list_amount' => $q['list'],
            'extra_days' => $q['extra_days'],
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
            $this->redeem($sub);
            if ($sub->trial_amount > 0) {
                $sub->trial_ends_at ??= now()->addDays(self::trialDays() + (int) $sub->extra_days);
                $school->trial_used = true;
                $school->extendUntil($sub->trial_ends_at);
            } elseif ($sub->extra_days > 0) {
                // coupon free days: first monthly charge after them
                $sub->trial_ends_at ??= now()->addDays((int) $sub->extra_days);
                $school->extendUntil($sub->trial_ends_at);
            } else {
                // no trial: the first month is charged with the authorisation
                $school->extendUntil(now()->addMonthNoOverflow()->addDays(self::GRACE_DAYS));
            }
            $sub->save();

            return $sub;
        });
    }

    /** The coupon counts as used once the school has paid / approved Autopay (once per school). */
    private function redeem(Subscription $sub): void
    {
        if (! $sub->coupon_id || CouponRedemption::where('coupon_id', $sub->coupon_id)->where('school_id', $sub->school_id)->exists()) {
            return;
        }
        CouponRedemption::create(['coupon_id' => $sub->coupon_id, 'school_id' => $sub->school_id, 'subscription_id' => $sub->id,
            'monthly_before' => $sub->list_amount ?? $sub->monthly_amount, 'monthly_after' => $sub->monthly_amount, 'extra_days' => $sub->extra_days]);
        Coupon::whereKey($sub->coupon_id)->increment('used_count');
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
