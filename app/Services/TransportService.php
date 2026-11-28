<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\Transport\FuelEntry;
use App\Models\Transport\RouteStop;
use App\Models\Transport\StaffSalary;
use App\Models\Transport\TransportExpense;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\TransportTrip;
use App\Models\Transport\Vehicle;
use App\Models\Transport\VehicleMaintenance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransportService
{
    // ---------------------------------------------------------------- students

    public function feeHead(): FeeHead
    {
        return FeeHead::firstOrCreate(['name' => 'Transport Fee'], ['frequency' => 'monthly', 'concession_applicable' => false,
            'description' => 'Added automatically when a student is given a bus route.']);
    }

    /** Monthly fee for a student on a route / stop (stop fee wins over route fee). */
    public static function defaultFee(TransportRoute $route, ?RouteStop $stop): float
    {
        return (float) ($stop?->monthly_fee ?? 0) ?: (float) $route->monthly_fee;
    }

    /**
     * Put a student on a route from a date: monthly Transport Fee dues are created for the rest of the session.
     * Changing the route or fee replaces the future unpaid transport dues.
     */
    public function assign(Student $student, TransportRoute $route, ?RouteStop $stop, ?float $fee, Carbon $from): int
    {
        $fee = $fee ?? self::defaultFee($route, $stop);

        return DB::transaction(function () use ($student, $route, $stop, $fee, $from) {
            $this->cancelFutureDues($student, $from);
            $student->update(['transport_route_id' => $route->id, 'route_stop_id' => $stop?->id, 'transport_fee' => $fee, 'transport_from' => $from->toDateString()]);

            return $fee > 0 ? $this->generateDues($student->fresh(), $from) : 0;
        });
    }

    /** Stop transport from a date: future unpaid transport dues are removed. */
    public function remove(Student $student, Carbon $from): int
    {
        return DB::transaction(function () use ($student, $from) {
            $removed = $this->cancelFutureDues($student, $from);
            $student->update(['transport_route_id' => null, 'route_stop_id' => null, 'transport_fee' => null, 'transport_from' => null]);

            return $removed;
        });
    }

    /** Monthly dues from the student's transport start month to the end of the session. */
    public function generateDues(Student $student, ?Carbon $from = null, ?AcademicSession $session = null): int
    {
        $session ??= AcademicSession::current() ?? $student->academicSession;
        if (! $session || ! $student->transport_route_id || ! (float) $student->transport_fee) {
            return 0;
        }
        $from = ($from ?? $student->transport_from ?? $session->start_date)->copy()->startOfMonth();
        $head = $this->feeHead();
        $dueDay = max(1, min(28, (int) Setting::get('fee_due_day')));
        $created = 0;

        foreach ($session->months() as $month) {
            if ($month->lt($from)) {
                continue;
            }
            $title = 'Transport Fee - '.$month->format('M Y');
            $exists = StudentFee::where('student_id', $student->id)->where('academic_session_id', $session->id)->where('title', $title)->exists();
            if ($exists) {
                continue;
            }
            StudentFee::create([
                'student_id' => $student->id, 'academic_session_id' => $session->id, 'fee_head_id' => $head->id, 'title' => $title,
                'due_date' => $month->copy()->day($dueDay), 'amount' => $student->transport_fee, 'discount' => 0, 'status' => 'unpaid',
                'transport_route_id' => $student->transport_route_id,
            ]);
            $created++;
        }

        return $created;
    }

    private function cancelFutureDues(Student $student, Carbon $from): int
    {
        $head = $this->feeHead();

        return StudentFee::where('student_id', $student->id)->where('fee_head_id', $head->id)
            ->whereDate('due_date', '>=', $from->copy()->startOfMonth())
            ->where('paid', 0)->where('status', 'unpaid')->delete();
    }

    // ---------------------------------------------------------------- reminders

    /**
     * Documents expiring, licences, services due — within $days (or already overdue).
     *
     * @return Collection<int, array{what: string, name: string, due: ?Carbon, days: ?int, km_left: ?int, url: string}>
     */
    public function reminders(int $days = 30): Collection
    {
        $limit = now()->addDays($days)->endOfDay();
        $out = collect();
        $vehicles = Vehicle::where('is_active', true)->get();

        foreach ($vehicles as $v) {
            foreach (Vehicle::DOCUMENTS as $col => $label) {
                if ($v->$col && $v->$col->lte($limit)) {
                    $out->push(['what' => $label, 'name' => $v->label(), 'due' => $v->$col, 'days' => (int) now()->startOfDay()->diffInDays($v->$col, false), 'km_left' => null, 'url' => route('transport.vehicles.show', $v)]);
                }
            }
        }
        foreach (TransportStaff::where('is_active', true)->whereNotNull('licence_expiry')->whereDate('licence_expiry', '<=', $limit)->get() as $s) {
            $out->push(['what' => 'Driving licence', 'name' => $s->name, 'due' => $s->licence_expiry, 'days' => (int) now()->startOfDay()->diffInDays($s->licence_expiry, false), 'km_left' => null, 'url' => route('transport.staff.show', $s)]);
        }

        // service due: the latest record of each vehicle that sets a next due date / km, plus scheduled jobs
        $latest = VehicleMaintenance::whereIn('vehicle_id', $vehicles->pluck('id'))->where(fn ($q) => $q->whereNotNull('next_due_date')->orWhereNotNull('next_due_km'))
            ->orderByDesc('service_date')->orderByDesc('id')->get()->unique('vehicle_id');
        foreach ($latest as $m) {
            $v = $vehicles->firstWhere('id', $m->vehicle_id);
            $kmLeft = $m->next_due_km ? $m->next_due_km - $v->currentKm() : null;
            $dateDue = $m->next_due_date && $m->next_due_date->lte($limit);
            if ($dateDue || ($kmLeft !== null && $kmLeft <= 500)) {
                $out->push(['what' => 'Service due', 'name' => $v->label(), 'due' => $m->next_due_date, 'days' => $m->next_due_date ? (int) now()->startOfDay()->diffInDays($m->next_due_date, false) : null,
                    'km_left' => $kmLeft, 'url' => route('transport.vehicles.show', $v)]);
            }
        }
        foreach (VehicleMaintenance::with('vehicle')->where('status', 'scheduled')->whereDate('service_date', '<=', $limit)->get() as $m) {
            $out->push(['what' => 'Scheduled: '.(VehicleMaintenance::TYPES[$m->type] ?? $m->type), 'name' => $m->vehicle->label(), 'due' => $m->service_date,
                'days' => (int) now()->startOfDay()->diffInDays($m->service_date, false), 'km_left' => null, 'url' => route('transport.maintenance.edit', $m)]);
        }

        return $out->sortBy(fn ($r) => $r['days'] ?? ($r['km_left'] !== null ? intdiv($r['km_left'], 50) : 999))->values();
    }

    // ---------------------------------------------------------------- money

    /** Transport fee actually collected between two dates, by route id. */
    public function incomeByRoute(Carbon $from, Carbon $to): Collection
    {
        return DB::table('payment_items')
            ->join('payments', 'payments.id', '=', 'payment_items.payment_id')
            ->join('student_fees', 'student_fees.id', '=', 'payment_items.student_fee_id')
            ->where('payment_items.school_id', \App\Support\Tenant::id())
            ->whereNotNull('student_fees.transport_route_id')
            ->whereBetween('payments.payment_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->groupBy('student_fees.transport_route_id')
            ->selectRaw('student_fees.transport_route_id as route_id, SUM(payment_items.amount) as total')
            ->pluck('total', 'route_id')->map(fn ($v) => (float) $v);
    }

    /** Transport fee billed (dues falling in the period), by route id. */
    public function billedByRoute(Carbon $from, Carbon $to): Collection
    {
        return StudentFee::whereNotNull('transport_route_id')->whereBetween('due_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->groupBy('transport_route_id')->selectRaw('transport_route_id, SUM(amount - discount) as total')
            ->pluck('total', 'transport_route_id')->map(fn ($v) => (float) $v);
    }

    /**
     * Income and costs per vehicle for a period. Key 0 = costs not linked to a vehicle.
     *
     * @return Collection<int, array> keyed by vehicle id
     */
    public function vehicleFinance(Carbon $from, Carbon $to): Collection
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString().' 23:59:59'];
        $routes = TransportRoute::get(['id', 'vehicle_id', 'distance_km']);
        $income = $this->incomeByRoute($from, $to);
        $billed = $this->billedByRoute($from, $to);

        $sum = fn ($q, string $col, string $key = 'vehicle_id') => $q->groupBy($key)->selectRaw("{$key} as k, SUM({$col}) as total")->pluck('total', 'k')->map(fn ($v) => (float) $v);
        $fuel = $sum(FuelEntry::whereBetween('entry_date', [$f, $t]), 'amount');
        $litres = $sum(FuelEntry::whereBetween('entry_date', [$f, $t]), 'quantity');
        $maint = $sum(VehicleMaintenance::where('status', '!=', 'scheduled')->whereBetween('service_date', [$f, $t]), 'total_cost');
        $exp = TransportExpense::whereBetween('expense_date', [$f, $t])->groupBy('vehicle_id', 'category')
            ->selectRaw('vehicle_id, category, SUM(amount) as total')->get();
        $km = TransportTrip::whereBetween('trip_date', [$f, $t])->whereNotNull('end_km')->whereNotNull('start_km')
            ->groupBy('vehicle_id')->selectRaw('vehicle_id as k, SUM(end_km - start_km) as total')->pluck('total', 'k');
        $staffVehicle = TransportStaff::pluck('vehicle_id', 'id');
        $salaries = StaffSalary::whereBetween('month', [$from->format('Y-m'), $to->format('Y-m')])->get()
            ->groupBy(fn ($s) => (int) ($staffVehicle[$s->transport_staff_id] ?? 0))->map(fn ($g) => $g->sum(fn ($s) => $s->net()));

        $vehicles = Vehicle::orderBy('bus_no')->get();
        $rows = collect();
        foreach ($vehicles->pluck('id')->push(0) as $vid) {
            $routeIds = $routes->where('vehicle_id', $vid ?: null)->pluck('id');
            $e = $exp->where('vehicle_id', $vid ?: null);
            $row = [
                'vehicle' => $vehicles->firstWhere('id', $vid),
                'income' => $vid ? $routeIds->sum(fn ($id) => $income[$id] ?? 0) : $income->except($routes->whereNotNull('vehicle_id')->pluck('id')->all())->sum(),
                'billed' => $vid ? $routeIds->sum(fn ($id) => $billed[$id] ?? 0) : 0,
                'fuel' => $fuel[$vid ?: ''] ?? 0,
                'litres' => $litres[$vid ?: ''] ?? 0,
                'maintenance' => $maint[$vid ?: ''] ?? 0,
                'salary' => $salaries[$vid] ?? 0,
                'emi' => (float) $e->where('category', 'emi')->sum('total'),
                'rent' => (float) $e->where('category', 'rent')->sum('total'),
                'insurance' => (float) $e->where('category', 'insurance')->sum('total'),
                'other' => (float) $e->whereNotIn('category', ['emi', 'rent', 'insurance'])->sum('total'),
                'km' => (int) ($km[$vid] ?? 0),
            ];
            $row['cost'] = $row['fuel'] + $row['maintenance'] + $row['salary'] + $row['emi'] + $row['rent'] + $row['insurance'] + $row['other'];
            $row['profit'] = $row['income'] - $row['cost'];
            $row['cost_per_km'] = $row['km'] ? round($row['cost'] / $row['km'], 2) : null;
            $row['fuel_per_km'] = $row['km'] ? round($row['fuel'] / $row['km'], 2) : null;
            if ($vid || $row['cost'] > 0 || $row['income'] > 0) {
                $rows[$vid] = $row;
            }
        }

        return $rows;
    }

    /** Route P&L: route income minus its share of the vehicle's costs (shared between the vehicle's routes by distance). */
    public function routeFinance(Carbon $from, Carbon $to): Collection
    {
        $vehicles = $this->vehicleFinance($from, $to);
        $income = $this->incomeByRoute($from, $to);
        $billed = $this->billedByRoute($from, $to);
        $routes = TransportRoute::with(['vehicle'])->withCount('students')->orderBy('route_no')->orderBy('name')->get();

        return $routes->map(function ($r) use ($routes, $vehicles, $income, $billed) {
            $siblings = $routes->where('vehicle_id', $r->vehicle_id)->whereNotNull('vehicle_id');
            $totalKm = (float) $siblings->sum('distance_km');
            $share = ! $r->vehicle_id ? 0 : ($totalKm > 0 ? (float) $r->distance_km / $totalKm : 1 / max(1, $siblings->count()));
            $cost = $r->vehicle_id ? round(($vehicles[$r->vehicle_id]['cost'] ?? 0) * $share, 2) : 0;
            $in = $income[$r->id] ?? 0;

            return ['route' => $r, 'students' => $r->students_count, 'billed' => $billed[$r->id] ?? 0, 'income' => $in, 'cost' => $cost,
                'share' => round($share * 100), 'profit' => $in - $cost];
        });
    }

    /** Month-by-month totals for a period (income vs each kind of cost). */
    public function monthly(Carbon $from, Carbon $to): Collection
    {
        $out = collect();
        foreach (CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to) as $m) {
            $rows = $this->vehicleFinance($m->copy()->startOfMonth(), $m->copy()->endOfMonth());
            $sum = fn ($k) => (float) $rows->sum($k);
            $out->push(['month' => $m->copy(), 'income' => $sum('income'), 'billed' => $sum('billed'), 'fuel' => $sum('fuel'), 'maintenance' => $sum('maintenance'),
                'salary' => $sum('salary'), 'emi' => $sum('emi'), 'rent' => $sum('rent'), 'insurance' => $sum('insurance'), 'other' => $sum('other'),
                'cost' => $sum('cost'), 'profit' => $sum('profit'), 'km' => (int) $rows->sum('km')]);
        }

        return $out;
    }

    /** Driver / conductor performance for a period. */
    public function staffPerformance(Carbon $from, Carbon $to): Collection
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString().' 23:59:59'];
        $att = DB::table('staff_attendances')->where('school_id', \App\Support\Tenant::id())->whereBetween('date', [$f, $t])
            ->groupBy('transport_staff_id', 'status')->selectRaw('transport_staff_id, status, COUNT(*) as c')->get()->groupBy('transport_staff_id');
        $breakdowns = VehicleMaintenance::where('type', 'breakdown')->whereBetween('service_date', [$f, $t])->whereNotNull('driver_id')
            ->groupBy('driver_id')->selectRaw('driver_id, COUNT(*) as c')->pluck('c', 'driver_id');

        return TransportStaff::with('vehicle')->where('is_active', true)->orderBy('type')->orderBy('name')->get()->map(function ($s) use ($att, $breakdowns, $f, $t) {
            $col = $s->type === 'conductor' ? 'conductor_id' : 'driver_id';
            $trips = TransportTrip::where($col, $s->id)->whereBetween('trip_date', [$f, $t])->get();
            $a = ($att[$s->id] ?? collect())->pluck('c', 'status');
            $marked = (int) $a->sum();
            $present = (int) ($a['present'] ?? 0) + 0.5 * (int) ($a['half_day'] ?? 0);

            return ['staff' => $s, 'trips' => $trips->count(), 'km' => $trips->sum(fn ($x) => $x->km()), 'present' => $present,
                'absent' => (int) ($a['absent'] ?? 0), 'leave' => (int) ($a['leave'] ?? 0), 'attendance' => $marked ? round($present / $marked * 100) : null,
                'breakdowns' => (int) ($breakdowns[$s->id] ?? 0)];
        });
    }

    /** Fuel efficiency of a vehicle: km per unit between full-tank fills. */
    public static function mileage(Vehicle $vehicle): ?float
    {
        $fills = $vehicle->fuelEntries()->whereNotNull('odometer')->reorder('odometer')->get(['odometer', 'quantity', 'full_tank']);
        if ($fills->count() < 2) {
            return null;
        }
        $km = $fills->last()->odometer - $fills->first()->odometer;
        $qty = (float) $fills->slice(1)->sum('quantity');

        return $km > 0 && $qty > 0 ? round($km / $qty, 2) : null;
    }
}
