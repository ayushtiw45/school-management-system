<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One run of a vehicle (daily log): km driven, who drove. */
class TransportTrip extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'trip_date', 'shift', 'vehicle_id', 'transport_route_id', 'driver_id', 'conductor_id', 'start_km', 'end_km',
        'students_count', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['trip_date' => 'date'];
    }

    public function km(): int
    {
        return $this->end_km && $this->start_km !== null ? max(0, $this->end_km - $this->start_km) : 0;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'driver_id');
    }

    public function conductor(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'conductor_id');
    }
}
