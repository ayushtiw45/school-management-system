<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'school_id', 'subscription_id', 'monthly_before', 'monthly_after', 'extra_days'];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
