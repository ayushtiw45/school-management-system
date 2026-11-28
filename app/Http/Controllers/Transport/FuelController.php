<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\FuelEntry;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\Vehicle;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuelController extends Controller
{
    use StoresBills;

    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $q = FuelEntry::with(['vehicle', 'driver'])->whereBetween('entry_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->when($request->vehicle_id, fn ($q, $v) => $q->where('vehicle_id', $v));
        $byVehicle = (clone $q)->groupBy('vehicle_id')->selectRaw('vehicle_id, SUM(amount) as amount, SUM(quantity) as qty, COUNT(*) as fills')->get()->keyBy('vehicle_id');
        $entries = $q->latest('entry_date')->latest('id')->paginate(40)->withQueryString();

        return view('transport.fuel.index', ['entries' => $entries, 'byVehicle' => $byVehicle, 'from' => $from, 'to' => $to,
            'vehicles' => Vehicle::orderBy('bus_no')->get()]);
    }

    public function create(Request $request)
    {
        $vehicle = $request->vehicle_id ? Vehicle::find($request->vehicle_id) : null;
        $last = $vehicle ? FuelEntry::where('vehicle_id', $vehicle->id)->latest('entry_date')->first() : null;

        return view('transport.fuel.form', $this->formData(new FuelEntry(['entry_date' => now(), 'vehicle_id' => $vehicle?->id, 'fuel_type' => $vehicle?->fuel_type ?? 'diesel',
            'rate' => $last?->rate, 'full_tank' => true])));
    }

    public function store(Request $request)
    {
        FuelEntry::create($this->validated($request) + $this->storeBill($request, 'fuel') + ['created_by' => $request->user()->id]);

        return redirect()->route('transport.fuel.index')->with('success', 'Fuel entry saved.');
    }

    public function edit(FuelEntry $fuel)
    {
        return view('transport.fuel.form', $this->formData($fuel));
    }

    public function update(Request $request, FuelEntry $fuel)
    {
        $fuel->update($this->validated($request) + $this->storeBill($request, 'fuel', $fuel));

        return redirect()->route('transport.fuel.index')->with('success', 'Fuel entry updated.');
    }

    public function destroy(FuelEntry $fuel)
    {
        $this->deleteWithBill($fuel);

        return back()->with('success', 'Fuel entry deleted.');
    }

    public function bill(FuelEntry $fuel)
    {
        return $this->billResponse($fuel);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'vehicle_id' => ['required', Tenant::exists('vehicles')], 'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'fuel_type' => ['required', Rule::in(array_keys(Vehicle::FUELS))],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:5000'], 'rate' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'min:0'], 'odometer' => ['nullable', 'integer', 'min:0'],
            'station' => ['nullable', 'string', 'max:100'], 'driver_id' => ['nullable', Tenant::exists('transport_staff')],
            'notes' => ['nullable', 'string', 'max:300'], 'bill' => self::BILL_RULE,
        ]);
        $data['amount'] = ($data['amount'] ?? 0) ?: round($data['quantity'] * $data['rate'], 2);
        $data['full_tank'] = $request->boolean('full_tank');
        unset($data['bill']);

        return $data;
    }

    private function formData(FuelEntry $entry): array
    {
        return ['entry' => $entry, 'vehicles' => Vehicle::where('is_active', true)->orWhere('id', $entry->vehicle_id)->orderBy('bus_no')->get(),
            'drivers' => TransportStaff::drivers()->where('is_active', true)->orderBy('name')->get()];
    }
}
