<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Settings of the platform owner (not per school). */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'company_name' => 'TDS School Management Software',
        'contact_phone' => '',
        'contact_whatsapp' => '',
        'contact_email' => '',
        'trial_days' => '7',
        'trial_price' => '1',
        'monthly_price' => '599',
        'razorpay_key_id' => '',
        'razorpay_key_secret' => '',
        'razorpay_webhook_secret' => '',
        'razorpay_plan_id' => '',
        'razorpay_plan_amount' => '',
    ];

    public static function get(string $key): ?string
    {
        $all = Cache::rememberForever('platform_settings', fn () => Schema::hasTable('platform_settings') ? static::pluck('value', 'key')->all() : []);
        $v = $all[$key] ?? null;

        return ($v === null || $v === '') ? (self::DEFAULTS[$key] ?? null) : $v;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('platform_settings');
    }
}
