<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffSalary extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'transport_staff_id', 'month', 'amount', 'deduction', 'bonus', 'paid_on', 'method', 'note'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2', 'deduction' => 'decimal:2', 'bonus' => 'decimal:2'];
    }

    public function net(): float
    {
        return round((float) $this->amount + (float) $this->bonus - (float) $this->deduction, 2);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'transport_staff_id');
    }
}
