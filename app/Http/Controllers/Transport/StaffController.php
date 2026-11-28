<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\Vehicle;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = TransportStaff::with('vehicle')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status !== 'all', fn ($q) => $q->where('is_active', $request->status !== 'inactive'))
            ->orderBy('type')->orderBy('name')->get();

        return view('transport.staff.index', compact('staff'));
    }

    public function create(Request $request)
    {
        return view('transport.staff.form', ['member' => new TransportStaff(['type' => $request->input('type', 'driver'), 'verification_status' => 'pending', 'is_active' => true]),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('bus_no')->get()]);
    }

    public function store(Request $request)
    {
        $member = TransportStaff::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('transport.staff.show', $member)->with('success', TransportStaff::TYPES[$member->type].' added.');
    }

    public function show(TransportStaff $staff)
    {
        $staff->load(['vehicle', 'files']);
        $col = $staff->type === 'conductor' ? 'conductor_id' : 'driver_id';
        $month = now()->startOfMonth();

        return view('transport.staff.show', [
            'member' => $staff,
            'routes' => TransportRoute::where($col, $staff->id)->get(),
            'trips' => $staff->trips()->with(['vehicle', 'route'])->limit(20)->get(),
            'attendance' => $staff->attendances()->whereDate('date', '>=', $month)->get(),
            'leaves' => $staff->leaves()->with('substitute')->limit(10)->get(),
            'salaries' => auth()->user()->canSeeAccounts() ? $staff->salaries()->limit(12)->get() : collect(),
            'stats' => [
                'trips' => $staff->trips()->count(),
                'km' => (int) \App\Models\Transport\TransportTrip::where($col, $staff->id)->whereNotNull('end_km')->selectRaw('SUM(end_km - start_km) as k')->value('k'),
                'breakdowns' => \App\Models\Transport\VehicleMaintenance::where('driver_id', $staff->id)->where('type', 'breakdown')->count(),
            ],
        ]);
    }

    public function edit(TransportStaff $staff)
    {
        return view('transport.staff.form', ['member' => $staff, 'vehicles' => Vehicle::orderBy('bus_no')->get()]);
    }

    public function update(Request $request, TransportStaff $staff)
    {
        $staff->update($this->validated($request, $staff));

        return redirect()->route('transport.staff.show', $staff)->with('success', 'Saved.');
    }

    public function toggle(TransportStaff $staff)
    {
        $staff->update(['is_active' => ! $staff->is_active]);

        return back()->with('success', $staff->is_active ? 'Marked active.' : 'Marked inactive. History is kept.');
    }

    private function validated(Request $request, ?TransportStaff $staff = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(TransportStaff::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:30', Tenant::unique('transport_staff', 'employee_id')->ignore($staff?->id)],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'], 'alt_phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'address' => ['nullable', 'string', 'max:300'], 'dob' => ['nullable', 'date', 'before:today'], 'joining_date' => ['nullable', 'date'],
            'licence_no' => [Rule::requiredIf($request->type === 'driver'), 'nullable', 'string', 'max:40'],
            'licence_type' => ['nullable', 'string', 'max:40'],
            'licence_expiry' => [Rule::requiredIf($request->type === 'driver'), 'nullable', 'date'],
            'aadhaar_no' => ['nullable', 'regex:/^[0-9 ]{12,14}$/'],
            'verification_status' => ['required', Rule::in(array_keys(TransportStaff::VERIFICATION))],
            'verified_on' => ['nullable', 'date'], 'verification_note' => ['nullable', 'string', 'max:300'],
            'duty_start' => ['nullable', 'date_format:H:i'], 'duty_end' => ['nullable', 'date_format:H:i'],
            'vehicle_id' => ['nullable', Tenant::exists('vehicles')],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['licence_no.required' => 'A driver needs a driving licence number.', 'licence_expiry.required' => 'Enter the licence expiry date.']);
        if (empty($data['employee_id']) && ! $staff) {
            $prefix = $data['type'] === 'driver' ? 'DRV' : 'CON';
            $n = TransportStaff::where('employee_id', 'like', $prefix.'-%')->count() + 1;
            while (TransportStaff::where('employee_id', $prefix.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
                $n++;
            }
            $data['employee_id'] = $prefix.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        }
        $data['monthly_salary'] = $data['monthly_salary'] ?? 0;

        return $data;
    }
}
