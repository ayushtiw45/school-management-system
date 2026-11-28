<?php

namespace App\Models\Transport;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A school bus / van. */
class Vehicle extends Model
{
    use BelongsToSchool;

    public const FUELS = ['diesel' => 'Diesel', 'petrol' => 'Petrol', 'cng' => 'CNG', 'ev' => 'Electric (EV)'];

    public const OWNERSHIP = ['owned' => 'School-owned', 'rented' => 'Rented'];

    /** document => [label, column] used for expiry reminders */
    public const DOCUMENTS = ['insurance_expiry' => 'Insurance', 'puc_expiry' => 'Pollution (PUC)', 'permit_expiry' => 'Permit', 'fitness_expiry' => 'Fitness certificate'];

    protected $fillable = ['school_id', 'bus_no', 'registration_no', 'make', 'model', 'year', 'seats', 'fuel_type', 'ownership', 'owner_name',
        'monthly_rent', 'monthly_emi', 'insurance_company', 'insurance_policy_no', 'insurance_expiry', 'insurance_amount', 'puc_no', 'puc_expiry',
        'permit_no', 'permit_expiry', 'fitness_expiry', 'odometer', 'service_interval_km', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['insurance_expiry' => 'date', 'puc_expiry' => 'date', 'permit_expiry' => 'date', 'fitness_expiry' => 'date', 'is_active' => 'boolean',
            'monthly_rent' => 'decimal:2', 'monthly_emi' => 'decimal:2', 'insurance_amount' => 'decimal:2'];
    }

    public function label(): string
    {
        return trim($this->bus_no.' · '.$this->registration_no, ' ·');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class)->latest('entry_date')->latest('id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class)->latest('service_date')->latest('id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(TransportTrip::class)->latest('trip_date')->latest('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(TransportExpense::class)->latest('expense_date');
    }

    public function files(): HasMany
    {
        return $this->hasMany(TransportFile::class, 'owner_id')->where('owner_type', 'vehicle')->latest('id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(TransportStaff::class);
    }

    /** Highest known odometer reading (vehicle record, trips, fuel, service). */
    public function currentKm(): int
    {
        return (int) max($this->odometer, (int) $this->trips()->max('end_km'), (int) $this->fuelEntries()->max('odometer'), (int) $this->maintenances()->max('odometer'));
    }
}
