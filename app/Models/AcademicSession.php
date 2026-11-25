<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'is_current'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public static function current(): ?self
    {
        return static::where('is_current', true)->first() ?? static::latest('start_date')->first();
    }

    public function makeCurrent(): void
    {
        static::where('id', '!=', $this->id)->update(['is_current' => false]);
        $this->update(['is_current' => true]);
    }

    /** First day of every month in the session. */
    public function months(): array
    {
        $months = [];
        foreach (CarbonPeriod::create($this->start_date->copy()->startOfMonth(), '1 month', $this->end_date) as $m) {
            $months[] = $m->copy();
        }

        return $months;
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
