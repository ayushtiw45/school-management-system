<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'code', 'days_per_year', 'is_paid'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'days_per_year' => 'decimal:1'];
    }
}
