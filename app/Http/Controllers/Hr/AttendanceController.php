<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendance;
use App\Models\Hr\EmployeeLeave;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? now();
        $employees = Employee::active()->when($request->type, fn ($q, $t) => $q->where('type', $t))->orderBy('type')->orderBy('name')->get();
        $marks = EmployeeAttendance::whereDate('date', $date)->get()->keyBy('employee_id');
        $onLeave = EmployeeLeave::with('type')->where('status', 'approved')->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->get()->keyBy('employee_id');

        return view('staff.attendance.index', compact('date', 'employees', 'marks', 'onLeave'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'att' => ['required', 'array'],
            'att.*.status' => ['required', Rule::in(array_keys(EmployeeAttendance::STATUSES))],
            'att.*.in_time' => ['nullable', 'date_format:H:i'], 'att.*.out_time' => ['nullable', 'date_format:H:i'], 'att.*.note' => ['nullable', 'string', 'max:200'],
        ]);
        $ids = Employee::whereIn('id', array_keys($data['att']))->pluck('id')->all();
        foreach ($data['att'] as $id => $a) {
            if (in_array((int) $id, $ids, true)) {
                EmployeeAttendance::updateOrCreate(['employee_id' => $id, 'date' => Carbon::parse($data['date'])->startOfDay()], $a);
            }
        }

        return back()->with('success', 'Attendance saved for '.Carbon::parse($data['date'])->format('d M Y').'.');
    }

    public function monthly(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $days = CarbonPeriod::create($start, $start->copy()->endOfMonth())->toArray();
        $employees = Employee::where(fn ($q) => $q->where('status', 'active')->orWhereDate('leaving_date', '>=', $start))->orderBy('type')->orderBy('name')->get();
        $marks = EmployeeAttendance::whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString().' 23:59:59'])->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($a) => $a->date->format('Y-m-d')));

        return view('staff.attendance.monthly', compact('month', 'start', 'days', 'employees', 'marks'));
    }
}
