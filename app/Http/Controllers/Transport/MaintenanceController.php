<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\TransportVendor;
use App\Models\Transport\Vehicle;
use App\Models\Transport\VehicleMaintenance;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MaintenanceController extends Controller
{
    use StoresBills;

    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfYear()->month(4)->subYears(now()->month < 4 ? 1 : 0);
        $to = $request->date('to') ?? now()->addMonths(3);
        $q = VehicleMaintenance::with(['vehicle', 'vendor'])->whereBetween('service_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->when($request->vehicle_id, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s));
        $byType = (clone $q)->where('status', '!=', 'scheduled')->groupBy('type')->selectRaw('type, SUM(total_cost) as total, COUNT(*) as c')->get()->keyBy('type');
        $records = $q->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")->latest('service_date')->latest('id')->paginate(40)->withQueryString();

        return view('transport.maintenance.index', ['records' => $records, 'byType' => $byType, 'from' => $from, 'to' => $to, 'vehicles' => Vehicle::orderBy('bus_no')->get()]);
    }

    public function create(Request $request)
    {
        return view('transport.maintenance.form', $this->formData(new VehicleMaintenance(['service_date' => now(), 'vehicle_id' => $request->vehicle_id,
            'type' => $request->input('type', 'service'), 'status' => $request->input('type') === 'breakdown' ? 'open' : 'done'])));
    }

    public function store(Request $request)
    {
        $m = VehicleMaintenance::create($this->validated($request) + $this->storeBill($request, 'maintenance') + ['created_by' => $request->user()->id]);
        $this->bumpOdometer($m);

        return redirect()->route('transport.maintenance.index')->with('success', 'Saved.');
    }

    public function edit(VehicleMaintenance $maintenance)
    {
        return view('transport.maintenance.form', $this->formData($maintenance));
    }

    public function update(Request $request, VehicleMaintenance $maintenance)
    {
        $maintenance->update($this->validated($request) + $this->storeBill($request, 'maintenance', $maintenance));
        $this->bumpOdometer($maintenance);

        return redirect()->route('transport.maintenance.index')->with('success', 'Updated.');
    }

    public function destroy(VehicleMaintenance $maintenance)
    {
        $this->deleteWithBill($maintenance);

        return back()->with('success', 'Deleted.');
    }

    public function bill(VehicleMaintenance $maintenance)
    {
        return $this->billResponse($maintenance);
    }

    private function bumpOdometer(VehicleMaintenance $m): void
    {
        if ($m->odometer && $m->odometer > $m->vehicle->odometer) {
            $m->vehicle->update(['odometer' => $m->odometer]);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'vehicle_id' => ['required', Tenant::exists('vehicles')], 'service_date' => ['required', 'date'],
            'type' => ['required', Rule::in(array_keys(VehicleMaintenance::TYPES))], 'title' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::in(array_keys(VehicleMaintenance::STATUSES))],
            'transport_vendor_id' => ['nullable', Tenant::exists('transport_vendors')], 'driver_id' => ['nullable', Tenant::exists('transport_staff')],
            'odometer' => ['nullable', 'integer', 'min:0'], 'parts_cost' => ['nullable', 'numeric', 'min:0'], 'labour_cost' => ['nullable', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date', 'after:service_date'], 'next_due_km' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'], 'bill' => self::BILL_RULE,
        ]);
        $data['parts_cost'] = $data['parts_cost'] ?? 0;
        $data['labour_cost'] = $data['labour_cost'] ?? 0;
        $data['total_cost'] = $data['parts_cost'] + $data['labour_cost'];
        // a regular service sets the next one by the vehicle's service interval when not given
        if ($data['type'] === 'service' && empty($data['next_due_km']) && ! empty($data['odometer'])) {
            $interval = (int) Vehicle::whereKey($data['vehicle_id'])->value('service_interval_km');
            $data['next_due_km'] = $interval ? $data['odometer'] + $interval : null;
        }
        unset($data['bill']);

        return $data;
    }

    private function formData(VehicleMaintenance $m): array
    {
        return ['m' => $m, 'vehicles' => Vehicle::where('is_active', true)->orWhere('id', $m->vehicle_id)->orderBy('bus_no')->get(),
            'vendors' => TransportVendor::orderBy('name')->get(), 'drivers' => TransportStaff::drivers()->orderBy('name')->get()];
    }
}
