<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Driver or conductor. */
class TransportStaff extends Model
{
    use BelongsToSchool;

    protected $table = 'transport_staff';

    public const TYPES = ['driver' => 'Driver', 'conductor' => 'Conductor'];

    public const VERIFICATION = ['pending' => 'Pending', 'verified' => 'Verified', 'failed' => 'Not cleared'];

    protected $fillable = ['school_id', 'type', 'name', 'employee_id', 'phone', 'alt_phone', 'address', 'dob', 'joining_date', 'licence_no', 'licence_type',
        'licence_expiry', 'aadhaar_no', 'verification_status', 'verified_on', 'verification_note', 'duty_start', 'duty_end', 'vehicle_id', 'monthly_salary',
        'is_active', 'notes'];

    protected function casts(): array
    {
        return ['dob' => 'date', 'joining_date' => 'date', 'licence_expiry' => 'date', 'verified_on' => 'date', 'is_active' => 'boolean', 'monthly_salary' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class)->latest('date');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class)->latest('from_date');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(StaffSalary::class)->latest('month');
    }

    public function files(): HasMany
    {
        return $this->hasMany(TransportFile::class, 'owner_id')->where('owner_type', 'staff')->latest('id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(TransportTrip::class, $this->type === 'conductor' ? 'conductor_id' : 'driver_id')->latest('trip_date')->latest('id');
    }

    public function scopeDrivers($q)
    {
        return $q->where('type', 'driver');
    }

    public function scopeConductors($q)
    {
        return $q->where('type', 'conductor');
    }
}
