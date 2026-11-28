<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Transport\RouteChange;
use App\Models\Transport\RouteStop;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\Vehicle;
use App\Services\TransportService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    public function index()
    {
        $routes = TransportRoute::with(['vehicle', 'driver', 'conductor'])->withCount(['students', 'stops'])->orderBy('route_no')->orderBy('name')->get();

        // trips per vehicle (a vehicle can run several routes one after another)
        $trips = $routes->whereNotNull('vehicle_id')->groupBy('vehicle_id');

        return view('transport.routes.index', compact('routes', 'trips'));
    }

    public function create()
    {
        return view('transport.routes.form', $this->formData(new TransportRoute(['shift' => 'both', 'trip_no' => 1, 'is_active' => true])));
    }

    public function store(Request $request)
    {
        $route = TransportRoute::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('transport.routes.show', $route)->with('success', 'Route created. Now add its stops.');
    }

    public function show(TransportRoute $route)
    {
        $route->load(['vehicle', 'driver', 'conductor', 'stops', 'changes.vehicle', 'changes.driver', 'changes.conductor']);
        $students = $route->students()->with(['schoolClass', 'section', 'routeStop'])->whereIn('status', ['active', 'pending'])->orderBy('first_name')->get();

        return view('transport.routes.show', [
            'route' => $route,
            'students' => $students,
            'today' => $route->changeOn(now()),
            'classes' => SchoolClass::ordered()->get(),
            'vehicles' => Vehicle::where('is_active', true)->orderBy('bus_no')->get(),
            'drivers' => TransportStaff::drivers()->where('is_active', true)->orderBy('name')->get(),
            'conductors' => TransportStaff::conductors()->where('is_active', true)->orderBy('name')->get(),
            'candidates' => request('class_id') ? Student::with('section')->where('school_class_id', request('class_id'))->whereIn('status', ['active', 'pending'])
                ->whereNull('transport_route_id')->orderBy('first_name')->get() : collect(),
        ]);
    }

    public function edit(TransportRoute $route)
    {
        return view('transport.routes.form', $this->formData($route));
    }

    public function update(Request $request, TransportRoute $route)
    {
        $route->update($this->validated($request) + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('transport.routes.show', $route)->with('success', 'Route updated.');
    }

    public function destroy(TransportRoute $route)
    {
        abort_if($route->students()->exists(), 422, 'Move the students to another route first.');
        $route->delete();

        return redirect()->route('transport.routes.index')->with('success', 'Route deleted.');
    }

    // ---- stops

    public function storeStop(Request $request, TransportRoute $route)
    {
        $data = $this->stopData($request);
        $data['sequence'] = $data['sequence'] ?? ((int) $route->stops()->max('sequence') + 1);
        $route->stops()->create($data);

        return back()->with('success', 'Stop added.');
    }

    public function updateStop(Request $request, RouteStop $stop)
    {
        $stop->update($this->stopData($request));

        return back()->with('success', 'Stop updated.');
    }

    public function destroyStop(RouteStop $stop)
    {
        Student::where('route_stop_id', $stop->id)->update(['route_stop_id' => null]);
        $stop->delete();

        return back()->with('success', 'Stop removed.');
    }

    /** Re-number stops by distance from school (farthest first in the morning). */
    public function sortStops(TransportRoute $route)
    {
        $stops = $route->stops()->get()->sortByDesc(fn ($s) => (float) $s->distance_km)->values();
        foreach ($stops as $i => $s) {
            $s->update(['sequence' => $i + 1]);
        }

        return back()->with('success', 'Stops arranged from the farthest to the nearest.');
    }

    // ---- temporary changes / substitutes

    public function storeChange(Request $request, TransportRoute $route)
    {
        $data = $request->validate([
            'from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'vehicle_id' => ['nullable', Tenant::exists('vehicles')], 'driver_id' => ['nullable', Tenant::exists('transport_staff')],
            'conductor_id' => ['nullable', Tenant::exists('transport_staff')], 'reason' => ['nullable', 'string', 'max:300'],
        ]);
        abort_if(empty($data['vehicle_id']) && empty($data['driver_id']) && empty($data['conductor_id']), 422, 'Choose what changes.');
        $route->changes()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Temporary change saved.');
    }

    public function destroyChange(RouteChange $change)
    {
        $change->delete();

        return back()->with('success', 'Temporary change removed.');
    }

    // ---- students

    public function addStudents(Request $request, TransportRoute $route, TransportService $transport)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => [Tenant::exists('students')],
            'route_stop_id' => ['nullable', Rule::exists('route_stops', 'id')->where('transport_route_id', $route->id)],
            'from' => ['required', 'date'], 'fee' => ['nullable', 'numeric', 'min:0'],
        ], ['student_ids.required' => 'Tick at least one student.']);

        $stop = isset($data['route_stop_id']) ? RouteStop::find($data['route_stop_id']) : null;
        $full = $route->seatLimit() && $route->students()->count() + count($data['student_ids']) > $route->seatLimit();
        foreach (Student::whereIn('id', $data['student_ids'])->get() as $student) {
            $transport->assign($student, $route, $stop, isset($data['fee']) ? (float) $data['fee'] : null, \Carbon\Carbon::parse($data['from']));
        }

        return back()->with($full ? 'error' : 'success', count($data['student_ids']).' student(s) added. Monthly transport fee dues were created.'
            .($full ? ' Warning: the route is now over its seat limit.' : ''));
    }

    private function stopData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'], 'sequence' => ['nullable', 'integer', 'min:1', 'max:500'],
            'pickup_time' => ['nullable', 'date_format:H:i'], 'drop_time' => ['nullable', 'date_format:H:i'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:500'], 'monthly_fee' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'route_no' => ['nullable', 'string', 'max:20'],
            'shift' => ['required', Rule::in(array_keys(TransportRoute::SHIFTS))], 'trip_no' => ['required', 'integer', 'min:1', 'max:9'],
            'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'vehicle_id' => ['nullable', Tenant::exists('vehicles')],
            'driver_id' => ['nullable', Tenant::exists('transport_staff')], 'conductor_id' => ['nullable', Tenant::exists('transport_staff')],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'], 'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['distance_km'] = $data['distance_km'] ?? 0;
        $data['monthly_fee'] = $data['monthly_fee'] ?? 0;

        return $data;
    }

    private function formData(TransportRoute $route): array
    {
        return [
            'route' => $route,
            'vehicles' => Vehicle::where('is_active', true)->orWhere('id', $route->vehicle_id)->orderBy('bus_no')->get(),
            'drivers' => TransportStaff::drivers()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $route->driver_id))->orderBy('name')->get(),
            'conductors' => TransportStaff::conductors()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $route->conductor_id))->orderBy('name')->get(),
        ];
    }
}
