<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffAttendance extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['present' => 'Present', 'absent' => 'Absent', 'half_day' => 'Half day', 'leave' => 'On leave'];

    protected $fillable = ['school_id', 'transport_staff_id', 'date', 'status', 'in_time', 'out_time', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'transport_staff_id');
    }
}
