<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffLeave extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $fillable = ['school_id', 'transport_staff_id', 'from_date', 'to_date', 'reason', 'status', 'substitute_id'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    public function days(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'transport_staff_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'substitute_id');
    }
}
