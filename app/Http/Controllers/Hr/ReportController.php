<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendance;
use App\Models\Hr\EmployeeLeave;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payroll;
use App\Models\Hr\TimetableEntry;
use App\Models\Setting;
use App\Reports\ReportData;
use App\Reports\XlsxWriter;
use App\Services\StaffService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    /** type => [title, description, filter: month|year|none, accounts only] */
    public const REPORTS = [
        'directory' => ['Staff Directory', 'All staff with designation, phone, qualification, joining date and experience.', 'none', false],
        'attendance' => ['Monthly Attendance Summary', 'Present, late, half day, absent and leave days of every staff member.', 'month', false],
        'leave' => ['Leave Register', 'Leave taken and balance of each type for the year.', 'none', false],
        'workload' => ['Teacher Workload', 'Periods per week, subjects and class-teacher duty of each teacher.', 'none', false],
        'salary' => ['Salary Register', 'Earnings, deductions and net pay of everyone for a month, with bank details.', 'month', true],
        'annual' => ['Annual Salary Statement', 'Net salary paid to each person, month by month, for the session.', 'year', true],
    ];

    public function index(Request $request)
    {
        return view('staff.reports.index', ['reports' => collect(self::REPORTS)->filter(fn ($r) => ! $r[3] || $request->user()->canSeeAccounts())]);
    }

    public function show(Request $request, string $type, StaffService $staff)
    {
        abort_unless(isset(self::REPORTS[$type]), 404);
        abort_if(self::REPORTS[$type][3] && ! $request->user()->canSeeAccounts(), 403);
        $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'format' => ['nullable', 'in:xlsx,pdf']]);
        $month = $request->input('month', now()->format('Y-m'));
        $report = $this->{'build'.Str::studly($type)}($staff, $month);
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

        return view('staff.reports.show', ['type' => $type, 'meta' => self::REPORTS[$type], 'report' => $report, 'month' => $month]);
    }

    private function buildDirectory(StaffService $s, string $month): ReportData
    {
        $rows = Employee::active()->orderBy('type')->orderBy('name')->get()->map(fn ($e) => ['id' => $e->employee_id, 'name' => $e->name, 'type' => Employee::TYPES[$e->type],
            'designation' => $e->designation, 'department' => $e->department, 'phone' => $e->phone, 'qualification' => $e->qualification,
            'joined' => $e->joining_date, 'exp' => $e->totalExperience().' yrs', 'employment' => Employee::EMPLOYMENT[$e->employment_type] ?? ''])->all();

        return new ReportData('Staff Directory', 'As on '.now()->format('d M Y'), ['id' => ['label' => 'ID'], 'name' => ['label' => 'Name'], 'type' => ['label' => 'Type'],
            'designation' => ['label' => 'Designation'], 'department' => ['label' => 'Department'], 'phone' => ['label' => 'Mobile'], 'qualification' => ['label' => 'Qualification'],
            'joined' => ['label' => 'Joined', 'type' => 'date'], 'exp' => ['label' => 'Experience'], 'employment' => ['label' => 'Employment']], $rows, [],
            ['Teaching' => (string) collect($rows)->where('type', 'Teaching')->count(), 'Non-teaching' => (string) collect($rows)->where('type', 'Non-teaching')->count()]);
    }

    private function buildAttendance(StaffService $s, string $month): ReportData
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $working = count(StaffService::workingDays($start, $start->copy()->endOfMonth()->min(now())));
        $att = EmployeeAttendance::whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString().' 23:59:59'])->get()->groupBy('employee_id');
        $rows = Employee::where(fn ($q) => $q->where('status', 'active')->orWhereDate('leaving_date', '>=', $start))->orderBy('name')->get()->map(function ($e) use ($att, $working) {
            $a = $att[$e->id] ?? collect();
            $c = fn ($st) => $a->where('status', $st)->count();
            $present = $c('present') + $c('late') + 0.5 * $c('half_day');

            return ['name' => $e->name, 'designation' => $e->designation, 'present' => $c('present'), 'late' => $c('late'), 'half' => $c('half_day'), 'absent' => $c('absent'),
                'leave' => $c('leave'), 'unmarked' => max(0, $working - $a->count()), 'pct' => $a->count() ? round($present / $a->count() * 100).'%' : '—'];
        })->all();

        return new ReportData('Monthly Attendance Summary', $start->format('F Y')." · {$working} working days so far", ['name' => ['label' => 'Name'], 'designation' => ['label' => 'Designation'],
            'present' => ['label' => 'Present', 'type' => 'int'], 'late' => ['label' => 'Late', 'type' => 'int'], 'half' => ['label' => 'Half day', 'type' => 'int'],
            'absent' => ['label' => 'Absent', 'type' => 'int'], 'leave' => ['label' => 'Leave', 'type' => 'int'], 'unmarked' => ['label' => 'Not marked', 'type' => 'int'], 'pct' => ['label' => 'Attendance']], $rows);
    }

    private function buildLeave(StaffService $s, string $month): ReportData
    {
        [$from, $to] = StaffService::leaveYear();
        $types = LeaveType::orderBy('id')->get();
        $cols = ['name' => ['label' => 'Name']];
        foreach ($types as $t) {
            $cols['t'.$t->id] = ['label' => $t->code.' taken / left'];
        }
        $cols['total'] = ['label' => 'Total taken'];
        $rows = Employee::active()->orderBy('name')->get()->map(function ($e) use ($s, $types) {
            $row = ['name' => $e->name];
            $total = 0;
            foreach ($s->balances($e) as $b) {
                $row['t'.$b['type']->id] = ($b['used'] + 0).($b['balance'] !== null ? ' / '.($b['balance'] + 0) : '');
                $total += $b['used'];
            }
            $row['total'] = $total + 0;

            return $row;
        })->all();

        return new ReportData('Leave Register', $from->format('d M Y').' to '.$to->format('d M Y'), $cols, $rows);
    }

    private function buildWorkload(StaffService $s, string $month): ReportData
    {
        $entries = TimetableEntry::with(['subject', 'section.schoolClass'])->whereNotNull('employee_id')->get()->groupBy('employee_id');
        $rows = Employee::active()->teachers()->with('classTeacherOf.schoolClass')->orderBy('name')->get()->map(function ($e) use ($entries) {
            $mine = $entries[$e->id] ?? collect();

            return ['name' => $e->name, 'designation' => $e->designation, 'periods' => $mine->count(),
                'subjects' => $mine->pluck('subject.name')->filter()->unique()->implode(', '),
                'classes' => $mine->map(fn ($x) => $x->section?->schoolClass?->name.' '.$x->section?->name)->unique()->implode(', '),
                'ct' => $e->classTeacherOf->map(fn ($sec) => $sec->schoolClass->name.' '.$sec->name)->implode(', ') ?: '—'];
        })->sortByDesc('periods')->values()->all();

        return new ReportData('Teacher Workload', 'Periods per week from the timetable', ['name' => ['label' => 'Teacher'], 'designation' => ['label' => 'Designation'],
            'periods' => ['label' => 'Periods / week', 'type' => 'int'], 'subjects' => ['label' => 'Subjects'], 'classes' => ['label' => 'Classes'], 'ct' => ['label' => 'Class teacher of']], $rows);
    }

    private function buildSalary(StaffService $s, string $month): ReportData
    {
        $rows = Payroll::with('employee')->where('month', $month)->get()->sortBy('employee.name')->map(fn ($p) => [
            'name' => $p->employee->name, 'days' => ($p->paid_days + 0).'/'.($p->working_days + 0), 'basic' => $p->basic, 'allow' => $p->hra + $p->da + $p->conveyance + $p->other_allowance + $p->bonus,
            'gross' => $p->gross, 'pf' => $p->pf + $p->esi, 'tds' => $p->tds, 'lop' => $p->lop_deduction, 'other' => $p->advance_deduction + $p->other_deduction, 'net' => $p->net,
            'bank' => trim($p->employee->bank_account.' '.$p->employee->bank_ifsc), 'status' => $p->status === 'paid' ? 'Paid '.$p->paid_on?->format('d M') : 'Draft'])->values()->all();
        $money = ['basic', 'allow', 'gross', 'pf', 'tds', 'lop', 'other', 'net'];
        $cols = ['name' => ['label' => 'Name'], 'days' => ['label' => 'Paid days'], 'basic' => ['label' => 'Basic'], 'allow' => ['label' => 'Allowances + bonus'], 'gross' => ['label' => 'Gross'],
            'pf' => ['label' => 'PF + ESI'], 'tds' => ['label' => 'TDS'], 'lop' => ['label' => 'Loss of pay'], 'other' => ['label' => 'Advance + other'], 'net' => ['label' => 'Net pay'],
            'bank' => ['label' => 'Bank a/c · IFSC'], 'status' => ['label' => 'Status']];
        foreach ($money as $k) {
            $cols[$k]['type'] = 'money';
        }
        $totals = ['name' => 'Total'];
        foreach ($money as $k) {
            $totals[$k] = array_sum(array_column($rows, $k));
        }

        return new ReportData('Salary Register', Carbon::createFromFormat('Y-m-d', $month.'-01')->format('F Y'), $cols, $rows, $totals, ['Net pay' => inr($totals['net']), 'Staff' => (string) count($rows)]);
    }

    private function buildAnnual(StaffService $s, string $month): ReportData
    {
        [$from, $to] = StaffService::leaveYear();
        $months = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $months[$m->format('Y-m')] = $m->format('M');
        }
        $paid = Payroll::where('status', 'paid')->whereIn('month', array_keys($months))->get()->groupBy('employee_id');
        $cols = ['name' => ['label' => 'Name']] + array_map(fn ($l) => ['label' => $l, 'type' => 'money'], $months) + ['total' => ['label' => 'Total', 'type' => 'money']];
        $rows = Employee::whereIn('id', $paid->keys())->orderBy('name')->get()->map(function ($e) use ($paid, $months) {
            $row = ['name' => $e->name];
            foreach (array_keys($months) as $k) {
                $row[$k] = (float) ($paid[$e->id] ?? collect())->where('month', $k)->sum('net') ?: null;
            }
            $row['total'] = (float) ($paid[$e->id] ?? collect())->sum('net');

            return $row;
        })->all();
        $totals = ['name' => 'Total'];
        foreach ([...array_keys($months), 'total'] as $k) {
            $totals[$k] = array_sum(array_map(fn ($r) => (float) $r[$k], $rows));
        }

        return new ReportData('Annual Salary Statement', $from->format('M Y').' – '.$to->format('M Y'), $cols, $rows, $totals);
    }
}
