<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public function monthLabel(): string
    {
        return \Carbon\Carbon::createFromFormat('Y-m-d', $this->month.'-01')->format('F Y');
    }

    /** Work out gross, deductions and net from the line amounts. */
    public function recalc(): static
    {
        $this->gross = round(array_sum(array_map(fn ($k) => (float) $this->$k, [...array_keys(Employee::EARNINGS), 'bonus'])), 2);
        $this->total_deduction = round(array_sum(array_map(fn ($k) => (float) $this->$k, [...array_keys(Employee::DEDUCTIONS), 'advance_deduction', 'lop_deduction'])), 2);
        $this->net = max(0, round($this->gross - $this->total_deduction, 2));

        return $this;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
