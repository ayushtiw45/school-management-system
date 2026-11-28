<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelEntry extends Model
{
    use BelongsToSchool;

    public const UNITS = ['diesel' => 'L', 'petrol' => 'L', 'cng' => 'kg', 'ev' => 'kWh'];

    protected $fillable = ['school_id', 'vehicle_id', 'entry_date', 'fuel_type', 'quantity', 'rate', 'amount', 'odometer', 'full_tank', 'station',
        'driver_id', 'bill_path', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'full_tank' => 'boolean', 'quantity' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    public function unit(): string
    {
        return self::UNITS[$this->fuel_type] ?? 'L';
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportStaff::class, 'driver_id');
    }
}
