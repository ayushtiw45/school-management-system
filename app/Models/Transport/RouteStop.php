<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteStop extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'transport_route_id', 'name', 'sequence', 'pickup_time', 'drop_time', 'distance_km', 'monthly_fee'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'transport_route_id');
    }
}
