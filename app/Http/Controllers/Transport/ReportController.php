<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Setting;
use App\Models\Transport\FuelEntry;
use App\Models\Transport\TransportStaff;
use App\Models\Transport\Vehicle;
use App\Models\Transport\VehicleMaintenance;
use App\Reports\ReportData;
use App\Reports\XlsxWriter;
use App\Services\TransportService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    /** type => [title, description, filter: range|year] */
    public const REPORTS = [
        'vehicles' => ['Bus-wise Profit & Loss', 'Fee income and every cost of each vehicle, cost per km.', 'range'],
        'routes' => ['Route-wise Profit & Loss', 'Students, fee billed and collected, share of vehicle cost and profit for each route.', 'range'],
        'monthly' => ['Monthly Transport Profitability', 'Income against fuel, maintenance, salary, EMI, rent and other costs, month by month.', 'year'],
        'annual' => ['Annual Transport Financial Report', 'Full-year totals by cost head and by vehicle.', 'year'],
        'fuel' => ['Fuel Report', 'Fuel quantity and cost per vehicle, km run, cost per km and mileage.', 'range'],
        'maintenance' => ['Maintenance Cost Report', 'Service, engine, tyre, battery, repair and breakdown costs per vehicle.', 'range'],
        'staff' => ['Driver & Conductor Performance', 'Trips, km, attendance, leave and breakdowns of each person.', 'range'],
        'documents' => ['Document Expiry Report', 'Insurance, PUC, permit, fitness and licence dates with days left.', 'none'],
    ];

    public function index()
    {
        return view('transport.reports.index', ['reports' => self::REPORTS]);
    }

    public function show(Request $request, string $type, TransportService $t)
    {
        abort_unless(isset(self::REPORTS[$type]), 404);
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'session_id' => ['nullable', 'integer'], 'format' => ['nullable', 'in:xlsx,pdf']]);

        $session = $request->session_id ? AcademicSession::find($request->session_id) : AcademicSession::current();
        [$from, $to] = self::REPORTS[$type][2] === 'year' && $session
            ? [$session->start_date->copy(), $session->end_date->copy()]
            : [$request->date('from') ?? now()->startOfMonth(), $request->date('to') ?? now()];
        $report = $this->{'build'.Str::studly($type)}($t, $from, $to);
        $report->subtitle = self::REPORTS[$type][2] === 'none' ? 'As on '.now()->format('d M Y') : $from->format('d M Y').' to '.$to->format('d M Y');
        $file = Str::slug($report->title.' '.$report->subtitle);

        if ($request->format === 'xlsx') {
            return response(XlsxWriter::fromReport($report, Setting::get('school_name')), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$file.'.xlsx"']);
        }
        if ($request->format === 'pdf') {
            $o = new Options();
            $o->set('isRemoteEnabled', false);
            $o->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($o);
            $pdf->loadHtml(view('reports.pdf', compact('report'))->render(), 'UTF-8');
            $pdf->setPaper('A4', count($report->columns) > 7 ? 'landscape' : 'portrait');
            $pdf->render();

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$file.'.pdf"']);
        }

        return view('transport.reports.show', ['type' => $type, 'meta' => self::REPORTS[$type], 'report' => $report, 'from' => $from, 'to' => $to,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(), 'session' => $session]);
    }

    private function money(array $keys): array
    {
        return array_fill_keys($keys, null);
    }

    private function buildVehicles(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $rows = $t->vehicleFinance($from, $to)->map(fn ($r) => [
            'vehicle' => $r['vehicle'] ? $r['vehicle']->label() : 'Not linked to a vehicle', 'km' => $r['km'], 'income' => $r['income'],
            'fuel' => $r['fuel'], 'maintenance' => $r['maintenance'], 'salary' => $r['salary'], 'emi_rent' => $r['emi'] + $r['rent'],
            'insurance' => $r['insurance'], 'other' => $r['other'], 'cost' => $r['cost'], 'profit' => $r['profit'], 'per_km' => $r['cost_per_km'],
        ])->values()->all();
        $m = ['income', 'fuel', 'maintenance', 'salary', 'emi_rent', 'insurance', 'other', 'cost', 'profit'];
        $cols = ['vehicle' => ['label' => 'Vehicle'], 'km' => ['label' => 'Km run', 'type' => 'int']];
        foreach (['income' => 'Fee collected', 'fuel' => 'Fuel', 'maintenance' => 'Maintenance', 'salary' => 'Salary', 'emi_rent' => 'EMI / rent',
            'insurance' => 'Insurance', 'other' => 'Other', 'cost' => 'Total cost', 'profit' => 'Profit / loss', 'per_km' => 'Cost per km'] as $k => $l) {
            $cols[$k] = ['label' => $l, 'type' => 'money'];
        }
        $totals = ['vehicle' => 'Total', 'km' => array_sum(array_column($rows, 'km'))];
        foreach ($m as $k) {
            $totals[$k] = array_sum(array_column($rows, $k));
        }
        $totals['per_km'] = $totals['km'] ? round($totals['cost'] / $totals['km'], 2) : null;

        return new ReportData('Bus-wise Profit & Loss', '', $cols, $rows, $totals, ['Fee collected' => inr($totals['income']), 'Total cost' => inr($totals['cost']), 'Profit / loss' => inr($totals['profit'])]);
    }

    private function buildRoutes(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $rows = $t->routeFinance($from, $to)->map(fn ($r) => [
            'route' => $r['route']->label(), 'vehicle' => $r['route']->vehicle?->label() ?? '—', 'students' => $r['students'], 'billed' => $r['billed'],
            'income' => $r['income'], 'share' => $r['route']->vehicle_id ? $r['share'].'%' : '—', 'cost' => $r['cost'], 'profit' => $r['profit'],
        ])->values()->all();
        $cols = ['route' => ['label' => 'Route'], 'vehicle' => ['label' => 'Vehicle'], 'students' => ['label' => 'Students', 'type' => 'int'],
            'billed' => ['label' => 'Fee billed', 'type' => 'money'], 'income' => ['label' => 'Fee collected', 'type' => 'money'],
            'share' => ['label' => 'Share of bus cost'], 'cost' => ['label' => 'Cost', 'type' => 'money'], 'profit' => ['label' => 'Profit / loss', 'type' => 'money']];
        $totals = ['route' => 'Total'];
        foreach (['students', 'billed', 'income', 'cost', 'profit'] as $k) {
            $totals[$k] = array_sum(array_column($rows, $k));
        }

        return new ReportData('Route-wise Profit & Loss', '', $cols, $rows, $totals, ['Note' => 'A bus running several routes shares its cost between them by route distance.']);
    }

    private function buildMonthly(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $keys = ['income' => 'Fee collected', 'fuel' => 'Fuel', 'maintenance' => 'Maintenance', 'salary' => 'Salary', 'emi' => 'EMI', 'rent' => 'Rent',
            'insurance' => 'Insurance', 'other' => 'Other', 'cost' => 'Total cost', 'profit' => 'Profit / loss'];
        $rows = $t->monthly($from, min($to, now()->endOfMonth()))->map(fn ($m) => ['month' => $m['month']->format('M Y'), 'km' => $m['km']] + array_intersect_key($m, $keys))->all();
        $cols = ['month' => ['label' => 'Month'], 'km' => ['label' => 'Km', 'type' => 'int']] + array_map(fn ($l) => ['label' => $l, 'type' => 'money'], $keys);
        $totals = ['month' => 'Total', 'km' => array_sum(array_column($rows, 'km'))];
        foreach (array_keys($keys) as $k) {
            $totals[$k] = array_sum(array_column($rows, $k));
        }

        return new ReportData('Monthly Transport Profitability', '', $cols, $rows, $totals, ['Profit / loss' => inr($totals['profit'])]);
    }

    private function buildAnnual(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $v = $t->vehicleFinance($from, $to);
        $sum = fn ($k) => (float) $v->sum($k);
        $heads = ['Transport fee collected' => $sum('income'), 'Transport fee billed' => $sum('billed'), 'Fuel' => -$sum('fuel'), 'Maintenance & repairs' => -$sum('maintenance'),
            'Driver & conductor salary' => -$sum('salary'), 'Vehicle EMI' => -$sum('emi'), 'Vehicle rent' => -$sum('rent'), 'Insurance' => -$sum('insurance'), 'Other expenses' => -$sum('other')];
        $rows = [];
        foreach ($heads as $label => $amount) {
            $rows[] = ['head' => $label, 'income' => $amount > 0 && $label !== 'Transport fee billed' ? $amount : null, 'expense' => $amount < 0 ? -$amount : null,
                'note' => $label === 'Transport fee billed' ? inr($amount).' billed; '.inr(max(0, $sum('billed') - $sum('income'))).' not yet collected' : ''];
        }
        foreach ($v as $r) {
            $rows[] = ['head' => '   '.($r['vehicle']?->label() ?? 'Not linked'), 'income' => $r['income'] ?: null, 'expense' => $r['cost'] ?: null, 'note' => 'Profit '.inr($r['profit']).($r['km'] ? ' · '.number_format($r['km']).' km' : '')];
        }

        return new ReportData('Annual Transport Financial Report', '', ['head' => ['label' => 'Head'], 'income' => ['label' => 'Income', 'type' => 'money'],
            'expense' => ['label' => 'Expense', 'type' => 'money'], 'note' => ['label' => 'Note']], $rows,
            ['head' => 'Net profit / loss', 'income' => $sum('income'), 'expense' => $sum('cost'), 'note' => inr($sum('profit'))],
            ['Income' => inr($sum('income')), 'Expenses' => inr($sum('cost')), 'Net' => inr($sum('profit'))]);
    }

    private function buildFuel(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $fin = $t->vehicleFinance($from, $to);
        $byType = FuelEntry::whereBetween('entry_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, COUNT(*) as fills, AVG(rate) as rate')->get()->keyBy('vehicle_id');
        $rows = Vehicle::orderBy('bus_no')->get()->map(function ($v) use ($fin, $byType) {
            $f = $fin[$v->id] ?? null;

            return ['vehicle' => $v->label(), 'fuel' => Vehicle::FUELS[$v->fuel_type] ?? $v->fuel_type, 'fills' => (int) ($byType[$v->id]->fills ?? 0),
                'qty' => $f['litres'] ?? 0, 'rate' => isset($byType[$v->id]) ? round((float) $byType[$v->id]->rate, 2) : null, 'amount' => $f['fuel'] ?? 0,
                'km' => $f['km'] ?? 0, 'per_km' => $f['fuel_per_km'] ?? null, 'mileage' => ($f['km'] ?? 0) && ($f['litres'] ?? 0) ? round($f['km'] / $f['litres'], 2) : null];
        })->filter(fn ($r) => $r['fills'] || $r['km'])->values()->all();

        return new ReportData('Fuel Report', '', ['vehicle' => ['label' => 'Vehicle'], 'fuel' => ['label' => 'Fuel'], 'fills' => ['label' => 'Fills', 'type' => 'int'],
            'qty' => ['label' => 'Quantity'], 'rate' => ['label' => 'Avg rate', 'type' => 'money'], 'amount' => ['label' => 'Amount', 'type' => 'money'],
            'km' => ['label' => 'Km run', 'type' => 'int'], 'per_km' => ['label' => 'Fuel cost / km', 'type' => 'money'], 'mileage' => ['label' => 'Km per L/kg/kWh']],
            $rows, ['vehicle' => 'Total', 'fills' => array_sum(array_column($rows, 'fills')), 'amount' => array_sum(array_column($rows, 'amount')), 'km' => array_sum(array_column($rows, 'km'))]);
    }

    private function buildMaintenance(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $data = VehicleMaintenance::where('status', '!=', 'scheduled')->whereBetween('service_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->groupBy('vehicle_id', 'type')->selectRaw('vehicle_id, type, SUM(total_cost) as total, SUM(parts_cost) as parts')->get()->groupBy('vehicle_id');
        $types = VehicleMaintenance::TYPES;
        $rows = Vehicle::orderBy('bus_no')->get()->map(function ($v) use ($data, $types) {
            $g = $data[$v->id] ?? collect();
            $row = ['vehicle' => $v->label()];
            foreach (array_keys($types) as $k) {
                $row[$k] = (float) ($g->firstWhere('type', $k)->total ?? 0);
            }
            $row['parts'] = (float) $g->sum('parts');
            $row['total'] = (float) $g->sum('total');

            return $row;
        })->filter(fn ($r) => $r['total'] > 0)->values()->all();
        $cols = ['vehicle' => ['label' => 'Vehicle']] + array_map(fn ($l) => ['label' => $l, 'type' => 'money'], $types)
            + ['parts' => ['label' => 'Of which spare parts', 'type' => 'money'], 'total' => ['label' => 'Total', 'type' => 'money']];
        $totals = ['vehicle' => 'Total'];
        foreach (array_merge(array_keys($types), ['parts', 'total']) as $k) {
            $totals[$k] = array_sum(array_column($rows, $k));
        }

        return new ReportData('Maintenance Cost Report', '', $cols, $rows, $totals);
    }

    private function buildStaff(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $rows = $t->staffPerformance($from, $to)->map(fn ($r) => ['name' => $r['staff']->name, 'type' => TransportStaff::TYPES[$r['staff']->type], 'id' => $r['staff']->employee_id,
            'vehicle' => $r['staff']->vehicle?->label() ?? '—', 'trips' => $r['trips'], 'km' => $r['km'], 'present' => $r['present'], 'absent' => $r['absent'],
            'leave' => $r['leave'], 'attendance' => $r['attendance'] === null ? '—' : $r['attendance'].'%', 'breakdowns' => $r['breakdowns']])->all();

        return new ReportData('Driver & Conductor Performance', '', ['name' => ['label' => 'Name'], 'type' => ['label' => 'Role'], 'id' => ['label' => 'Employee ID'],
            'vehicle' => ['label' => 'Vehicle'], 'trips' => ['label' => 'Trips', 'type' => 'int'], 'km' => ['label' => 'Km', 'type' => 'int'], 'present' => ['label' => 'Days present'],
            'absent' => ['label' => 'Absent', 'type' => 'int'], 'leave' => ['label' => 'Leave', 'type' => 'int'], 'attendance' => ['label' => 'Attendance'],
            'breakdowns' => ['label' => 'Breakdowns', 'type' => 'int']], $rows);
    }

    private function buildDocuments(TransportService $t, Carbon $from, Carbon $to): ReportData
    {
        $rows = [];
        foreach (Vehicle::where('is_active', true)->orderBy('bus_no')->get() as $v) {
            foreach (Vehicle::DOCUMENTS as $col => $label) {
                $rows[] = ['name' => $v->label(), 'doc' => $label, 'date' => $v->$col, 'days' => $v->$col ? (int) now()->startOfDay()->diffInDays($v->$col, false) : null,
                    'status' => ! $v->$col ? 'Not entered' : ($v->$col->isPast() ? 'EXPIRED' : ($v->$col->lte(now()->addDays(30)) ? 'Expiring soon' : 'OK'))];
            }
        }
        foreach (TransportStaff::where('is_active', true)->drivers()->orderBy('name')->get() as $s) {
            $rows[] = ['name' => $s->name, 'doc' => 'Driving licence', 'date' => $s->licence_expiry, 'days' => $s->licence_expiry ? (int) now()->startOfDay()->diffInDays($s->licence_expiry, false) : null,
                'status' => ! $s->licence_expiry ? 'Not entered' : ($s->licence_expiry->isPast() ? 'EXPIRED' : ($s->licence_expiry->lte(now()->addDays(30)) ? 'Expiring soon' : 'OK'))];
        }
        usort($rows, fn ($a, $b) => ($a['days'] ?? 99999) <=> ($b['days'] ?? 99999));

        return new ReportData('Document Expiry Report', '', ['name' => ['label' => 'Vehicle / driver'], 'doc' => ['label' => 'Document'], 'date' => ['label' => 'Valid till', 'type' => 'date'],
            'days' => ['label' => 'Days left', 'type' => 'int'], 'status' => ['label' => 'Status']], $rows);
    }
}
