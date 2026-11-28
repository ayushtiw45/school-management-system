<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeLeave;
use App\Models\Hr\LeaveType;
use App\Services\StaffService;
use App\Support\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function index(Request $request, StaffService $staff)
    {
        $status = $request->input('status', 'pending');
        $leaves = EmployeeLeave::with(['employee', 'type', 'decider'])->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('from_date')->paginate(30)->withQueryString();
        $employees = Employee::active()->orderBy('name')->get();
        [$from, $to] = StaffService::leaveYear();

        return view('staff.leaves.index', [
            'leaves' => $leaves, 'status' => $status, 'employees' => $employees, 'types' => LeaveType::orderBy('id')->get(),
            'pendingCount' => EmployeeLeave::where('status', 'pending')->count(), 'year' => [$from, $to],
            'balances' => $request->balances ? $employees->mapWithKeys(fn ($e) => [$e->id => $staff->balances($e)]) : null,
        ]);
    }

    public function store(Request $request, StaffService $staff)
    {
        $data = $request->validate([
            'employee_id' => ['required', Tenant::exists('employees')], 'leave_type_id' => ['required', Tenant::exists('leave_types')],
            'from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date'], 'reason' => ['nullable', 'string', 'max:300'],
            'status' => ['required', Rule::in(['pending', 'approved'])],
        ]);
        $data['half_day'] = $request->boolean('half_day');
        if ($data['half_day']) {
            $data['to_date'] = $data['from_date'];
        }
        $data['days'] = StaffService::leaveDays(Carbon::parse($data['from_date']), Carbon::parse($data['to_date']), $data['half_day']);
        abort_if($data['days'] <= 0, 422, 'Those dates are only Sundays.');

        $employee = Employee::findOrFail($data['employee_id']);
        $bal = $staff->balances($employee)->firstWhere('type.id', (int) $data['leave_type_id']);
        $warning = $bal && $bal['balance'] !== null && $data['days'] > $bal['balance'] ? " Note: only {$bal['balance']} day(s) of {$bal['type']->code} were left." : '';

        $leave = EmployeeLeave::create($data + ['decided_by' => $data['status'] === 'approved' ? $request->user()->id : null]);
        $staff->applyLeave($leave->load('type'));

        return back()->with('success', 'Leave saved ('.($data['days'] + 0).' day(s)).'.$warning);
    }

    public function decide(Request $request, EmployeeLeave $leave, StaffService $staff)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])]]);
        $leave->update($data + ['decided_by' => $request->user()->id]);
        $staff->applyLeave($leave->load('type'));

        return back()->with('success', 'Leave '.strtolower(EmployeeLeave::STATUSES[$data['status']]).'.');
    }

    public function types(Request $request)
    {
        $data = $request->validate(['types' => ['required', 'array'], 'types.*.name' => ['required', 'string', 'max:60'], 'types.*.code' => ['required', 'string', 'max:10'],
            'types.*.days_per_year' => ['required', 'numeric', 'min:0', 'max:366'], 'new.name' => ['nullable', 'string', 'max:60'], 'new.code' => ['nullable', 'string', 'max:10'],
            'new.days_per_year' => ['nullable', 'numeric', 'min:0', 'max:366']]);
        foreach ($data['types'] as $id => $t) {
            LeaveType::whereKey($id)->update(['name' => $t['name'], 'code' => strtoupper($t['code']), 'days_per_year' => $t['days_per_year'], 'is_paid' => ! empty($request->input("types.$id.is_paid"))]);
        }
        if (! empty($data['new']['name']) && ! empty($data['new']['code'])) {
            LeaveType::create(['name' => $data['new']['name'], 'code' => strtoupper($data['new']['code']), 'days_per_year' => $data['new']['days_per_year'] ?? 0, 'is_paid' => $request->boolean('new.is_paid', true)]);
        }

        return back()->with('success', 'Leave types saved.');
    }
}
