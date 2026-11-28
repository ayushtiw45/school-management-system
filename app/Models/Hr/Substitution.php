<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Substitution extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'date', 'period_id', 'section_id', 'absent_employee_id', 'substitute_employee_id', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Section::class);
    }

    public function absent(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'absent_employee_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }
}
