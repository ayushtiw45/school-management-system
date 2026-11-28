<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Transport\RouteChange;
use App\Models\Transport\StaffAttendance;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\TransportTrip;
use App\Models\Transport\Vehicle;
use App\Models\Transport\VehicleMaintenance;
use App\Services\TransportService;

class DashboardController extends Controller
{
    public function __invoke(TransportService $transport)
    {
        $month = [now()->startOfMonth(), now()->endOfMonth()];
        $rows = $transport->vehicleFinance(...$month);
        $routes = TransportRoute::with('vehicle')->withCount(['students' => fn ($q) => $q->whereIn('status', ['active', 'pending'])])->where('is_active', true)->get();

        return view('transport.dashboard', [
            'counts' => [
                'vehicles' => Vehicle::where('is_active', true)->count(),
                'routes' => $routes->count(),
                'drivers' => TransportStaff::drivers()->where('is_active', true)->count(),
                'conductors' => TransportStaff::conductors()->where('is_active', true)->count(),
                'students' => Student::whereNotNull('transport_route_id')->whereIn('status', ['active', 'pending'])->count(),
                'off_road' => VehicleMaintenance::where('status', 'open')->count(),
            ],
            'month' => ['income' => $rows->sum('income'), 'billed' => $rows->sum('billed'), 'cost' => $rows->sum('cost'), 'fuel' => $rows->sum('fuel'),
                'maintenance' => $rows->sum('maintenance'), 'salary' => $rows->sum('salary'), 'km' => $rows->sum('km')],
            'reminders' => $transport->reminders(30),
            'overfull' => $routes->filter(fn ($r) => $r->seatLimit() && $r->students_count > $r->seatLimit()),
            'today' => [
                'trips' => TransportTrip::whereDate('trip_date', now())->count(),
                'absent' => StaffAttendance::with('staff')->whereDate('date', now())->whereIn('status', ['absent', 'leave'])->get(),
                'changes' => RouteChange::with(['route', 'vehicle', 'driver', 'conductor'])->whereDate('from_date', '<=', now())->whereDate('to_date', '>=', now())->get(),
                'breakdowns' => VehicleMaintenance::with('vehicle')->where('status', 'open')->get(),
            ],
            'routes' => $routes,
        ]);
    }
}
