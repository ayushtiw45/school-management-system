<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\Vehicle;
use App\Services\TransportService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::withCount(['routes', 'staff'])
            ->when($request->status !== 'all', fn ($q) => $q->where('is_active', $request->status !== 'inactive'))
            ->orderBy('bus_no')->get();

        return view('transport.vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        return view('transport.vehicles.form', ['vehicle' => new Vehicle(['fuel_type' => 'diesel', 'ownership' => 'owned', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $vehicle = Vehicle::create($this->validated($request));

        return redirect()->route('transport.vehicles.show', $vehicle)->with('success', 'Vehicle added.');
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['routes.driver', 'staff', 'files']);

        return view('transport.vehicles.show', [
            'vehicle' => $vehicle,
            'km' => $vehicle->currentKm(),
            'mileage' => TransportService::mileage($vehicle),
            'fuel' => $vehicle->fuelEntries()->limit(10)->get(),
            'maintenance' => $vehicle->maintenances()->with('vendor')->limit(15)->get(),
            'trips' => $vehicle->trips()->with(['route', 'driver'])->limit(15)->get(),
            'expenses' => $vehicle->expenses()->limit(10)->get(),
            'totals' => [
                'fuel' => (float) $vehicle->fuelEntries()->sum('amount'),
                'maintenance' => (float) $vehicle->maintenances()->where('status', '!=', 'scheduled')->sum('total_cost'),
                'expenses' => (float) $vehicle->expenses()->sum('amount'),
                'trips' => $vehicle->trips()->count(),
            ],
        ]);
    }

    public function edit(Vehicle $vehicle)
    {
        return view('transport.vehicles.form', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($this->validated($request, $vehicle));

        return redirect()->route('transport.vehicles.show', $vehicle)->with('success', 'Vehicle updated.');
    }

    public function toggle(Vehicle $vehicle)
    {
        $vehicle->update(['is_active' => ! $vehicle->is_active]);

        return back()->with('success', $vehicle->is_active ? 'Vehicle activated.' : 'Vehicle deactivated. Its history is kept.');
    }

    private function validated(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'bus_no' => ['required', 'string', 'max:30'],
            'registration_no' => ['required', 'string', 'max:30', Tenant::unique('vehicles', 'registration_no')->ignore($vehicle?->id)],
            'make' => ['nullable', 'string', 'max:60'], 'model' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'seats' => ['required', 'integer', 'min:1', 'max:120'],
            'fuel_type' => ['required', Rule::in(array_keys(Vehicle::FUELS))],
            'ownership' => ['required', Rule::in(array_keys(Vehicle::OWNERSHIP))],
            'owner_name' => ['nullable', 'string', 'max:100'],
            'monthly_rent' => ['nullable', 'numeric', 'min:0'], 'monthly_emi' => ['nullable', 'numeric', 'min:0'],
            'insurance_company' => ['nullable', 'string', 'max:100'], 'insurance_policy_no' => ['nullable', 'string', 'max:60'],
            'insurance_expiry' => ['nullable', 'date'], 'insurance_amount' => ['nullable', 'numeric', 'min:0'],
            'puc_no' => ['nullable', 'string', 'max:60'], 'puc_expiry' => ['nullable', 'date'],
            'permit_no' => ['nullable', 'string', 'max:60'], 'permit_expiry' => ['nullable', 'date'], 'fitness_expiry' => ['nullable', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0'], 'service_interval_km' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['registration_no'] = strtoupper(preg_replace('/\s+/', ' ', trim($data['registration_no'])));
        foreach (['monthly_rent', 'monthly_emi', 'insurance_amount', 'odometer', 'service_interval_km'] as $n) {
            $data[$n] = $data[$n] ?? 0;
        }
        $data['is_active'] = $vehicle?->is_active ?? true;

        return $data;
    }
}
