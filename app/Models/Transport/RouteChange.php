<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Temporary change of vehicle / driver / conductor on a route (also used for substitutes). */
class RouteChange extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'transport_route_id', 'from_date', 'to_date', 'vehicle_id', 'driver_id', 'conductor_id', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
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
}
