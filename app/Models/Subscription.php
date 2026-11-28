<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STARTED = ['authenticated', 'active', 'pending', 'halted', 'cancelled', 'completed'];

    protected $fillable = ['school_id', 'razorpay_subscription_id', 'razorpay_plan_id', 'monthly_amount', 'trial_amount',
        'status', 'trial_ends_at', 'current_end', 'paid_count', 'cancelled_at', 'coupon_id', 'list_amount', 'extra_days'];

    public function coupon(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'current_end' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isLive(): bool
    {
        return in_array($this->status, ['authenticated', 'active', 'pending'], true);
    }

    public function label(): string
    {
        return [
            'created' => 'Payment not completed',
            'authenticated' => 'Trial running',
            'active' => 'Active (Autopay on)',
            'pending' => 'Payment retrying',
            'halted' => 'Stopped (payment failed)',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
        ][$this->status] ?? $this->status;
    }
}
