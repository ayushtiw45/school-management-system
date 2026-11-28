<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryAdvance;
use App\Models\Payment;
use App\Services\StaffService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $payrolls = Payroll::with('employee')->where('month', $month)->get()->sortBy(fn ($p) => [$p->employee->type, $p->employee->name])->values();
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01');
        $missing = Employee::where(fn ($q) => $q->where('status', 'active')->orWhereDate('leaving_date', '>=', $start))
            ->whereNotIn('id', $payrolls->pluck('employee_id'))->orderBy('name')->get();

        return view('staff.payroll.index', compact('month', 'payrolls', 'missing', 'start'));
    }

    /** Create draft salary for everyone (or chosen staff) for the month. */
    public function generate(Request $request, StaffService $staff)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'employee_ids' => ['nullable', 'array'], 'employee_ids.*' => [Tenant::exists('employees')]]);
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $data['month'].'-01');
        abort_if($start->gt(now()->endOfMonth()), 422, 'You cannot make salary for a future month.');
        $q = Employee::where(fn ($q) => $q->where('status', 'active')->orWhereDate('leaving_date', '>=', $start))
            ->whereDoesntHave('payrolls', fn ($p) => $p->where('month', $data['month']));
        if (! empty($data['employee_ids'])) {
            $q->whereIn('id', $data['employee_ids']);
        }
        $n = 0;
        $noSalary = 0;
        foreach ($q->get() as $e) {
            if ($e->gross() <= 0) {
                $noSalary++;

                continue;
            }
            $staff->draft($e, $data['month'])->save();
            $n++;
        }

        return back()->with('success', "{$n} salary draft(s) made from attendance and the salary structure.".($noSalary ? " {$noSalary} skipped: salary structure not filled." : ''));
    }

    public function edit(Payroll $payroll)
    {
        return view('staff.payroll.edit', ['p' => $payroll->load('employee')]);
    }

    public function update(Request $request, Payroll $payroll)
    {
        abort_if($payroll->status === 'paid', 422, 'This salary is already paid. Undo the payment first.');
        $rules = ['working_days' => ['required', 'numeric', 'min:0', 'max:31'], 'lop_days' => ['required', 'numeric', 'min:0', 'max:31'], 'note' => ['nullable', 'string', 'max:300']];
        foreach ([...array_keys(Employee::EARNINGS), 'bonus', ...array_keys(Employee::DEDUCTIONS), 'advance_deduction', 'lop_deduction'] as $k) {
            $rules[$k] = ['nullable', 'numeric', 'min:0', 'max:9999999'];
        }
        $data = array_map(fn ($v) => $v ?? 0, $request->validate($rules));
        $data['note'] = $request->note;
        $data['paid_days'] = max(0, $data['working_days'] - $data['lop_days']);
        $payroll->fill($data)->recalc()->save();

        return redirect()->route('staff.payroll.index', ['month' => $payroll->month])->with('success', 'Salary of '.$payroll->employee->name.' updated: net '.inr($payroll->net).'.');
    }

    /** Recalculate a draft (after attendance or salary changes). */
    public function refresh(Payroll $payroll, StaffService $staff)
    {
        abort_if($payroll->status === 'paid', 422);
        $fresh = $staff->draft($payroll->employee, $payroll->month);
        $payroll->fill(collect($fresh->getAttributes())->except(['employee_id', 'month', 'status'])->all())->save();

        return back()->with('success', 'Recalculated.');
    }

    public function pay(Request $request, StaffService $staff)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => [Tenant::exists('payrolls')], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))], 'reference_no' => ['nullable', 'string', 'max:100']], ['ids.required' => 'Tick the salaries to pay.']);
        $n = 0;
        foreach (Payroll::with('employee')->whereIn('id', $data['ids'])->where('status', 'draft')->get() as $p) {
            $staff->pay($p, $data, $request->user()->id);
            $n++;
        }

        return back()->with('success', "{$n} salary payment(s) recorded and added to school expenses (Staff Salary).");
    }

    public function unpay(Payroll $payroll, StaffService $staff)
    {
        abort_unless($payroll->status === 'paid', 422);
        $staff->unpay($payroll);

        return back()->with('success', 'Payment undone. The expense entry was removed.');
    }

    public function destroy(Payroll $payroll)
    {
        abort_if($payroll->status === 'paid', 422, 'Undo the payment first.');
        $payroll->delete();

        return back()->with('success', 'Draft deleted.');
    }

    public function slip(Payroll $payroll)
    {
        return view('staff.payroll.slip', ['p' => $payroll->load('employee')]);
    }

    /** Print all slips of a month. */
    public function slips(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        return view('staff.payroll.slips', ['payrolls' => Payroll::with('employee')->where('month', $month)->get()->sortBy('employee.name')]);
    }

    // ---- advances

    public function advances()
    {
        return view('staff.payroll.advances', ['advances' => SalaryAdvance::with('employee')->latest('given_on')->get(),
            'employees' => Employee::active()->orderBy('name')->get()]);
    }

    public function storeAdvance(Request $request)
    {
        $data = $request->validate(['employee_id' => ['required', Tenant::exists('employees')], 'given_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'], 'monthly_deduction' => ['required', 'numeric', 'min:1', 'lte:amount'], 'note' => ['nullable', 'string', 'max:300']]);
        SalaryAdvance::create($data);

        return back()->with('success', 'Advance recorded. '.inr($data['monthly_deduction']).' will be cut from each salary until it is repaid.');
    }

    public function destroyAdvance(SalaryAdvance $advance)
    {
        abort_if((float) $advance->recovered > 0, 422, 'Part of this advance is already recovered.');
        $advance->delete();

        return back()->with('success', 'Advance deleted.');
    }
}
