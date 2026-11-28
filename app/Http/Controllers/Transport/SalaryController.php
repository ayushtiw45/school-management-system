<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transport\StaffAttendance;
use App\Models\Transport\StaffSalary;
use App\Models\Transport\TransportStaff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalaryController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01');
        $staff = TransportStaff::where(fn ($q) => $q->where('is_active', true)->orWhereHas('salaries', fn ($s) => $s->where('month', $month)))
            ->orderBy('type')->orderBy('name')->get();
        $paid = StaffSalary::where('month', $month)->get()->keyBy('transport_staff_id');
        $absent = StaffAttendance::whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString().' 23:59:59'])
            ->whereIn('status', ['absent', 'half_day'])->get()->groupBy('transport_staff_id')
            ->map(fn ($g) => $g->sum(fn ($a) => $a->status === 'absent' ? 1 : 0.5));

        return view('transport.salaries.index', compact('month', 'staff', 'paid', 'absent', 'start'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'pay' => ['required', 'array'],
            'pay.*.amount' => ['nullable', 'numeric', 'min:0'], 'pay.*.deduction' => ['nullable', 'numeric', 'min:0'],
            'pay.*.bonus' => ['nullable', 'numeric', 'min:0'], 'pay.*.note' => ['nullable', 'string', 'max:300'], 'pay.*.save' => ['nullable'],
        ]);
        $ids = TransportStaff::pluck('id')->all();
        $n = 0;
        foreach ($data['pay'] as $id => $p) {
            if (empty($p['save']) || ! in_array((int) $id, $ids, true) || ! (float) ($p['amount'] ?? 0)) {
                continue;
            }
            StaffSalary::updateOrCreate(['transport_staff_id' => $id, 'month' => $data['month']], [
                'amount' => $p['amount'], 'deduction' => $p['deduction'] ?? 0, 'bonus' => $p['bonus'] ?? 0, 'note' => $p['note'] ?? null,
                'paid_on' => $data['paid_on'], 'method' => $data['method'],
            ]);
            $n++;
        }

        return back()->with($n ? 'success' : 'error', $n ? "Salary recorded for {$n} person(s)." : 'Tick the people being paid.');
    }

    public function slip(StaffSalary $salary)
    {
        return view('transport.salaries.slip', ['salary' => $salary->load('staff.vehicle')]);
    }

    public function destroy(StaffSalary $salary)
    {
        $salary->delete();

        return back()->with('success', 'Salary entry deleted.');
    }
}
