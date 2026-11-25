<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'school_name' => 'My School',
        'school_address' => '',
        'school_phone' => '',
        'school_email' => '',
        'admission_prefix' => 'ADM',
        'receipt_prefix' => 'RCPT',
        'late_fine_amount' => '0',
        'fee_due_day' => '10',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('settings', function () {
            return Schema::hasTable('settings') ? static::pluck('value', 'key')->all() : [];
        });

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
