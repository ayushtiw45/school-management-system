<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Transport\RouteStop;
use App\Models\Transport\TransportRoute;
use App\Services\TransportService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Give a student a bus route + stop (creates monthly transport fee dues), change it or stop it. */
class StudentTransportController extends Controller
{
    public function edit(Student $student)
    {
        return view('transport.student', [
            'student' => $student->load(['transportRoute', 'routeStop', 'schoolClass', 'section']),
            'routes' => TransportRoute::with(['stops', 'vehicle'])->withCount('students')->where('is_active', true)->orderBy('route_no')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Student $student, TransportService $transport)
    {
        if ($request->boolean('remove')) {
            $from = Carbon::parse($request->validate(['from' => ['required', 'date']])['from']);
            $n = $transport->remove($student, $from);

            return redirect()->route('students.show', $student)->with('success', "Transport stopped. {$n} future transport fee due(s) removed.");
        }
        $data = $request->validate([
            'transport_route_id' => ['required', Tenant::exists('transport_routes')],
            'route_stop_id' => ['nullable', Tenant::exists('route_stops')],
            'transport_fee' => ['nullable', 'numeric', 'min:0'],
            'from' => ['required', 'date'],
        ], ['transport_route_id.required' => 'Choose a route.']);
        $route = TransportRoute::findOrFail($data['transport_route_id']);
        $stop = isset($data['route_stop_id']) ? RouteStop::where('transport_route_id', $route->id)->findOrFail($data['route_stop_id']) : null;
        $n = $transport->assign($student, $route, $stop, isset($data['transport_fee']) ? (float) $data['transport_fee'] : null, Carbon::parse($data['from']));

        return redirect()->route('students.show', $student)->with('success', "Transport saved: {$route->label()}. {$n} monthly transport fee due(s) created.");
    }
}
