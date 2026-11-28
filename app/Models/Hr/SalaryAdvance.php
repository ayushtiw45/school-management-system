<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryAdvance extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'employee_id', 'given_on', 'amount', 'monthly_deduction', 'recovered', 'note'];

    protected function casts(): array
    {
        return ['given_on' => 'date', 'amount' => 'decimal:2', 'monthly_deduction' => 'decimal:2', 'recovered' => 'decimal:2'];
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount - (float) $this->recovered, 2));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
