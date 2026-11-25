<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;

class NumberGenerator
{
    /** e.g. ADM2026-0001 (sequence restarts each session start year). */
    public static function admissionNo(AcademicSession $session): string
    {
        $base = Setting::get('admission_prefix').$session->start_date->format('Y').'-';

        return $base.str_pad((string) (self::lastSequence(Student::class, 'admission_no', $base) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** e.g. RCPT-2026-000001 (sequence restarts each calendar year). */
    public static function receiptNo(): string
    {
        $base = Setting::get('receipt_prefix').'-'.now()->format('Y').'-';

        return $base.str_pad((string) (self::lastSequence(Payment::class, 'receipt_no', $base) + 1), 6, '0', STR_PAD_LEFT);
    }

    private static function lastSequence(string $model, string $column, string $base): int
    {
        return (int) $model::where($column, 'like', $base.'%')
            ->pluck($column)
            ->map(fn ($v) => (int) substr($v, strlen($base)))
            ->max();
    }
}
