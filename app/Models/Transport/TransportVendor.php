<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mechanic, workshop, parts / tyre shop, fuel pump. */
class TransportVendor extends Model
{
    use BelongsToSchool;

    public const TYPES = ['mechanic' => 'Mechanic', 'workshop' => 'Workshop / service centre', 'parts' => 'Spare parts', 'tyres' => 'Tyres & battery',
        'fuel' => 'Fuel pump', 'other' => 'Other'];

    protected $fillable = ['school_id', 'name', 'type', 'contact_person', 'phone', 'address', 'notes'];

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class);
    }
}
