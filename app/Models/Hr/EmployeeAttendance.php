<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAttendance extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['present' => 'Present', 'late' => 'Late', 'half_day' => 'Half day', 'absent' => 'Absent', 'leave' => 'On leave'];

    public const SHORT = ['present' => 'P', 'late' => 'LT', 'half_day' => 'H', 'absent' => 'A', 'leave' => 'L'];

    protected $fillable = ['school_id', 'employee_id', 'date', 'status', 'in_time', 'out_time', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
