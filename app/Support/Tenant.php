<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Validation\Rule;

/**
 * The school whose data the current request (or command) works with.
 * Every tenant model is filtered by it automatically (see BelongsToSchool).
 */
class Tenant
{
    private static ?School $school = null;

    public static function set(?School $school): void
    {
        self::$school = $school;
        \App\Models\Setting::flush();
    }

    public static function school(): ?School
    {
        return self::$school;
    }

    public static function id(): ?int
    {
        return self::$school?->id;
    }

    /** Run code as a given school, then restore the previous one. */
    public static function run(School $school, callable $fn)
    {
        $previous = self::$school;
        self::set($school);
        try {
            return $fn();
        } finally {
            self::set($previous);
        }
    }

    /** exists rule limited to the current school */
    public static function exists(string $table, string $column = 'id')
    {
        return Rule::exists($table, $column)->where('school_id', self::id());
    }

    /** unique rule limited to the current school */
    public static function unique(string $table, string $column = 'NULL')
    {
        return Rule::unique($table, $column)->where('school_id', self::id());
    }
}
