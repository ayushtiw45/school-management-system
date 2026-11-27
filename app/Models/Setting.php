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

        // Notifications
        'notify_channel' => 'sms', // sms, whatsapp, both
        'notify_payment' => '1',
        'notify_admission' => '1',
        'notify_fee_due_auto' => '0',      // daily automatic reminders
        'reminder_days_after_due' => '3',  // remind this many days after the due date
        'tpl_fee_due' => 'Dear Parent, fee of Rs.{amount} for {student} ({class}) is pending since {due_date}. Please pay at the earliest. - {school}',
        'tpl_payment' => 'Dear Parent, we received Rs.{amount} for {student} ({class}). Receipt No: {receipt}. Balance: Rs.{balance}. Thank you. - {school}',
        'tpl_admission' => 'Dear Parent, admission of {student} in {class} is confirmed. Admission No: {admission_no}. Welcome to {school}!',
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
