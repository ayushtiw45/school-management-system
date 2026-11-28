<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Owner-made discount code for the school plan (not tied to one school's data). */
class Coupon extends Model
{
    public const TYPES = ['percent' => '% off monthly price', 'flat' => '₹ off monthly price', 'none' => 'No price discount (free days only)'];

    protected $fillable = ['code', 'description', 'discount_type', 'discount_value', 'extra_days', 'max_uses', 'used_count', 'valid_from', 'valid_until',
        'new_schools_only', 'school_id', 'is_active'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'new_schools_only' => 'boolean', 'is_active' => 'boolean', 'discount_value' => 'decimal:2'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class)->latest('id');
    }

    /** Monthly price after this coupon (paise). Razorpay needs at least ₹1. */
    public function apply(int $monthlyPaise): int
    {
        $off = match ($this->discount_type) {
            'percent' => (int) round($monthlyPaise * min(100, (float) $this->discount_value) / 100),
            'flat' => (int) round((float) $this->discount_value * 100),
            default => 0,
        };

        // whole rupees, rounded down in the school's favour (50% of ₹599 = ₹299)
        return max(100, intdiv(max(0, $monthlyPaise - $off), 100) * 100);
    }

    public function label(): string
    {
        $parts = [];
        if ($this->discount_type === 'percent' && (float) $this->discount_value > 0) {
            $parts[] = ((float) $this->discount_value + 0).'% off every month';
        } elseif ($this->discount_type === 'flat' && (float) $this->discount_value > 0) {
            $parts[] = '₹'.number_format((float) $this->discount_value).' off every month';
        }
        if ($this->extra_days) {
            $parts[] = $this->extra_days.' extra free days';
        }

        return implode(' + ', $parts) ?: 'No discount';
    }

    public function status(): string
    {
        return match (true) {
            ! $this->is_active => 'Off',
            $this->valid_until && $this->valid_until->endOfDay()->isPast() => 'Expired',
            $this->valid_from && $this->valid_from->isFuture() => 'Not started',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'Used up',
            default => 'Active',
        };
    }
}
