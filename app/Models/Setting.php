<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Settings of the current school (school name, prefixes, message templates…). */
class Setting extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'key', 'value'];

    /** @var array<int, array<string, string>> per-request cache, keyed by school id */
    private static array $cache = [];

    public const DEFAULTS = [
        'school_name' => 'My School',
        'school_address' => '',
        'school_phone' => '',
        'school_email' => '',
        'school_legal_name' => '',
        'school_state' => 'Uttar Pradesh',
        'school_website' => '',
        'signatory_name' => '',
        'signatory_title' => 'Accounts',
        'bank_account_name' => '',
        'bank_account_no' => '',
        'bank_ifsc' => '',
        'bank_name' => '',
        'upi_id' => '',
        'receipt_note' => 'Thank you. Fees once paid are not refundable.',
        'receipt_terms' => '',
        'slip_terms' => 'Please pay the fee by the due date to avoid late fine.',
        'student_code_prefix' => 'STU',
        'logo_path' => '',
        'signature_path' => '',
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
        $sid = Tenant::id();
        if (! $sid) {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }
        self::$cache[$sid] ??= Schema::hasTable('settings') ? static::pluck('value', 'key')->all() : [];

        return self::$cache[$sid][$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        unset(self::$cache[Tenant::id()]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
