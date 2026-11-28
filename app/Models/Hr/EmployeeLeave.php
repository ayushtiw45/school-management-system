<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLeave extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];

    protected $fillable = ['school_id', 'employee_id', 'leave_type_id', 'from_date', 'to_date', 'half_day', 'days', 'reason', 'status', 'decided_by'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date', 'half_day' => 'boolean', 'days' => 'decimal:1'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'decided_by');
    }
}
