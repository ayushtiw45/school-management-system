<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\TransportTrip;
use App\Models\Transport\Vehicle;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daily trip log: one row per route run, with km readings. */
class TripController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? now();
        $shift = $request->input('shift', now()->hour < 12 ? 'morning' : 'afternoon');
        $trips = TransportTrip::with(['vehicle', 'route', 'driver', 'conductor'])->whereDate('trip_date', $date)->orderBy('shift')->orderBy('id')->get();

        // routes that run in this shift and have no trip logged yet, with today's substitutes applied
        $logged = $trips->where('shift', $shift)->pluck('transport_route_id')->filter()->all();
        $pending = TransportRoute::with(['vehicle', 'driver', 'conductor'])->where('is_active', true)->whereIn('shift', [$shift, 'both'])
            ->whereNotIn('id', $logged)->orderBy('trip_no')->orderBy('route_no')->get()
            ->map(function ($r) use ($date) {
                $c = $r->changeOn($date);
                $vehicleId = $c?->vehicle_id ?: $r->vehicle_id;
                $last = $vehicleId ? TransportTrip::where('vehicle_id', $vehicleId)->max('end_km') : null;

                return ['route' => $r, 'change' => $c, 'vehicle_id' => $vehicleId, 'driver_id' => $c?->driver_id ?: $r->driver_id,
                    'conductor_id' => $c?->conductor_id ?: $r->conductor_id, 'start_km' => $last, 'students' => $r->students()->where('status', 'active')->count()];
            });

        return view('transport.trips.index', [
            'date' => $date, 'shift' => $shift, 'trips' => $trips, 'pending' => $pending,
            'vehicles' => Vehicle::where('is_active', true)->orderBy('bus_no')->get(),
            'drivers' => TransportStaff::drivers()->where('is_active', true)->orderBy('name')->get(),
            'conductors' => TransportStaff::conductors()->where('is_active', true)->orderBy('name')->get(),
            'routes' => TransportRoute::orderBy('name')->get(),
        ]);
    }

    /** Save several trips at once (the rows of the daily sheet that have km filled). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'trip_date' => ['required', 'date', 'before_or_equal:today'], 'shift' => ['required', Rule::in(['morning', 'afternoon', 'other'])],
            'trips' => ['required', 'array'],
            'trips.*.vehicle_id' => ['nullable', Tenant::exists('vehicles')],
            'trips.*.transport_route_id' => ['nullable', Tenant::exists('transport_routes')],
            'trips.*.driver_id' => ['nullable', Tenant::exists('transport_staff')],
            'trips.*.conductor_id' => ['nullable', Tenant::exists('transport_staff')],
            'trips.*.start_km' => ['nullable', 'integer', 'min:0'], 'trips.*.end_km' => ['nullable', 'integer', 'min:0'],
            'trips.*.students_count' => ['nullable', 'integer', 'min:0'], 'trips.*.notes' => ['nullable', 'string', 'max:300'],
            'trips.*.save' => ['nullable'],
        ]);
        $saved = 0;
        foreach ($data['trips'] as $i => $t) {
            if (empty($t['save']) || empty($t['vehicle_id'])) {
                continue;
            }
            if (isset($t['start_km'], $t['end_km']) && $t['end_km'] < $t['start_km']) {
                return back()->withInput()->withErrors(["trips.$i.end_km" => 'End km cannot be less than start km.']);
            }
            TransportTrip::create(collect($t)->except('save')->all() + ['trip_date' => $data['trip_date'], 'shift' => $data['shift'], 'created_by' => $request->user()->id]);
            $saved++;
        }

        return back()->with($saved ? 'success' : 'error', $saved ? "{$saved} trip(s) saved." : 'Tick the trips to save.');
    }

    public function update(Request $request, TransportTrip $trip)
    {
        $data = $request->validate(['start_km' => ['nullable', 'integer', 'min:0'], 'end_km' => ['nullable', 'integer', 'min:0', 'gte:start_km'],
            'students_count' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string', 'max:300']]);
        $trip->update($data);

        return back()->with('success', 'Trip updated.');
    }

    public function destroy(TransportTrip $trip)
    {
        $trip->delete();

        return back()->with('success', 'Trip removed.');
    }
}
