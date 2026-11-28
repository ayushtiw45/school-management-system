<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMaintenance extends Model
{
    use BelongsToSchool;

    public const TYPES = ['service' => 'Regular service', 'engine' => 'Engine work', 'tyre' => 'Tyre replacement', 'battery' => 'Battery replacement',
        'repair' => 'Repair', 'breakdown' => 'Breakdown', 'other' => 'Other'];

    public const STATUSES = ['done' => 'Done', 'scheduled' => 'Scheduled', 'open' => 'Open (vehicle off road)'];

    protected $fillable = ['school_id', 'vehicle_id', 'service_date', 'type', 'title', 'transport_vendor_id', 'driver_id', 'odometer', 'parts_cost',
        'labour_cost', 'total_cost', 'next_due_date', 'next_due_km', 'status', 'bill_path', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'next_due_date' => 'date', 'parts_cost' => 'decimal:2', 'labour_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(TransportVendor::class, 'transport_vendor_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'driver_id');
    }
}
