<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportRoute extends Model
{
    use BelongsToSchool;

    public const SHIFTS = ['both' => 'Morning & afternoon', 'morning' => 'Morning only', 'afternoon' => 'Afternoon only'];

    protected $fillable = ['school_id', 'name', 'route_no', 'shift', 'trip_no', 'start_time', 'end_time', 'distance_km', 'vehicle_id', 'driver_id',
        'conductor_id', 'capacity', 'monthly_fee', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'monthly_fee' => 'decimal:2', 'distance_km' => 'decimal:2'];
    }

    public function label(): string
    {
        return trim(($this->route_no ? $this->route_no.' · ' : '').$this->name);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'driver_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'conductor_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('sequence')->orderBy('id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(\App\Models\Student::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(RouteChange::class)->latest('from_date');
    }

    public function seatLimit(): int
    {
        return (int) ($this->capacity ?: $this->vehicle?->seats ?: 0);
    }

    /** Temporary change active on a date (substitute vehicle / driver), if any. */
    public function changeOn($date): ?RouteChange
    {
        return $this->changes()->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->first();
    }
}
