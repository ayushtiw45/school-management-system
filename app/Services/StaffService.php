<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendance;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\EmployeeLeave;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryAdvance;
use App\Models\Hr\Substitution;
use App\Models\Hr\TimetableEntry;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StaffService
{
    /** Default leave types, periods and subjects for a new school. */
    public static function seedDefaults(int $schoolId): void
    {
        $now = now();
        if (! DB::table('leave_types')->where('school_id', $schoolId)->exists()) {
            foreach ([['Casual Leave', 'CL', 12, true], ['Sick Leave', 'SL', 10, true], ['Earned Leave', 'EL', 15, true], ['Leave Without Pay', 'LWP', 0, false]] as [$n, $c, $d, $p]) {
                DB::table('leave_types')->insert(['school_id' => $schoolId, 'name' => $n, 'code' => $c, 'days_per_year' => $d, 'is_paid' => $p, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! DB::table('periods')->where('school_id', $schoolId)->exists()) {
            $rows = [['Period 1', '08:00', '08:40'], ['Period 2', '08:40', '09:20'], ['Period 3', '09:20', '10:00'], ['Period 4', '10:00', '10:40'],
                ['Lunch', '10:40', '11:10', true], ['Period 5', '11:10', '11:50'], ['Period 6', '11:50', '12:30'], ['Period 7', '12:30', '13:10'], ['Period 8', '13:10', '13:50']];
            foreach ($rows as $i => $r) {
                DB::table('periods')->insert(['school_id' => $schoolId, 'name' => $r[0], 'start_time' => $r[1], 'end_time' => $r[2], 'sort_order' => $i + 1,
                    'is_break' => $r[3] ?? false, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! DB::table('subjects')->where('school_id', $schoolId)->exists()) {
            foreach (['English', 'Hindi', 'Mathematics', 'Science', 'Social Science', 'Computer', 'Sanskrit', 'General Knowledge', 'Drawing', 'Physical Education',
                'Physics', 'Chemistry', 'Biology', 'Accountancy', 'Business Studies', 'Economics'] as $n) {
                DB::table('subjects')->insert(['school_id' => $schoolId, 'name' => $n, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public static function nextEmployeeId(string $type): string
    {
        $prefix = $type === 'teaching' ? 'TCH' : 'STF';
        $n = Employee::where('employee_id', 'like', $prefix.'-%')->count() + 1;
        while (Employee::where('employee_id', $prefix.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefix.'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** Working days = all days except Sundays. */
    public static function workingDays(Carbon $from, Carbon $to): array
    {
        return array_values(array_filter(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay())->toArray(), fn ($d) => ! $d->isSunday()));
    }

    // ---------------------------------------------------------------- leave

    /** The leave year: the current academic session (April–March), or the calendar year. */
    public static function leaveYear(): array
    {
        $s = AcademicSession::current();

        return $s ? [$s->start_date->copy(), $s->end_date->copy()] : [now()->startOfYear(), now()->endOfYear()];
    }

    /** @return Collection<int, array{type: LeaveType, allowed: float, used: float, pending: float, balance: ?float}> */
    public function balances(Employee $employee): Collection
    {
        [$from, $to] = self::leaveYear();
        $leaves = EmployeeLeave::where('employee_id', $employee->id)->whereIn('status', ['approved', 'pending'])
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->get();

        return LeaveType::orderBy('id')->get()->map(function ($t) use ($leaves) {
            $used = (float) $leaves->where('leave_type_id', $t->id)->where('status', 'approved')->sum('days');
            $pending = (float) $leaves->where('leave_type_id', $t->id)->where('status', 'pending')->sum('days');
            $allowed = (float) $t->days_per_year;

            return ['type' => $t, 'allowed' => $allowed, 'used' => $used, 'pending' => $pending, 'balance' => $t->is_paid ? $allowed - $used : null];
        });
    }

    public static function leaveDays(Carbon $from, Carbon $to, bool $halfDay): float
    {
        return $halfDay ? 0.5 : (float) count(self::workingDays($from, $to));
    }

    /** Approved leave: attendance of those days (up to today) becomes "leave". */
    public function applyLeave(EmployeeLeave $leave): void
    {
        if ($leave->status !== 'approved') {
            return;
        }
        foreach (self::workingDays($leave->from_date, $leave->to_date->copy()->min(now())) as $d) {
            EmployeeAttendance::updateOrCreate(['employee_id' => $leave->employee_id, 'date' => $d->copy()->startOfDay()],
                ['status' => $leave->half_day ? 'half_day' : 'leave', 'note' => $leave->type?->code.' leave']);
        }
    }

    /** Ids of staff away (absent / on leave) on a date. */
    public function awayOn(Carbon $date): Collection
    {
        $marked = EmployeeAttendance::whereDate('date', $date)->whereIn('status', ['absent', 'leave'])->pluck('employee_id');
        $leave = EmployeeLeave::where('status', 'approved')->where('half_day', false)->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->pluck('employee_id');

        return $marked->merge($leave)->unique()->values();
    }

    // ---------------------------------------------------------------- timetable / substitution

    /** Periods of absent teachers on a date that need a substitute. */
    public function substitutionNeeds(Carbon $date): Collection
    {
        $day = $date->dayOfWeekIso;
        if ($day === 7) {
            return collect();
        }
        $away = $this->awayOn($date);
        $done = Substitution::whereDate('date', $date)->get()->keyBy(fn ($s) => $s->period_id.'-'.$s->section_id);

        return TimetableEntry::with(['period', 'section.schoolClass', 'subject', 'employee'])->where('day', $day)->whereIn('employee_id', $away)->get()
            ->sortBy(fn ($e) => [$e->period->sort_order, $e->section_id])->values()
            ->map(fn ($e) => ['entry' => $e, 'substitution' => $done[$e->period_id.'-'.$e->section_id] ?? null]);
    }

    /** Teachers free in a period on a date (not teaching then, not away, not already substituting). */
    public function freeTeachers(Carbon $date, int $periodId): Collection
    {
        $busy = TimetableEntry::where('day', $date->dayOfWeekIso)->where('period_id', $periodId)->whereNotNull('employee_id')->pluck('employee_id')
            ->merge(Substitution::whereDate('date', $date)->where('period_id', $periodId)->pluck('substitute_employee_id'))
            ->merge($this->awayOn($date));
        $load = TimetableEntry::where('day', $date->dayOfWeekIso)->groupBy('employee_id')->selectRaw('employee_id, COUNT(*) as c')->pluck('c', 'employee_id');

        return Employee::active()->teachers()->whereNotIn('id', $busy->filter())->orderBy('name')->get()
            ->each(fn ($t) => $t->periods_today = (int) ($load[$t->id] ?? 0))->sortBy('periods_today')->values();
    }

    /** Teacher already busy in that slot (other section)? Returns the clashing entry. */
    public static function clash(int $employeeId, int $day, int $periodId, int $sectionId): ?TimetableEntry
    {
        return TimetableEntry::with('section.schoolClass')->where('employee_id', $employeeId)->where('day', $day)->where('period_id', $periodId)
            ->where('section_id', '!=', $sectionId)->first();
    }

    // ---------------------------------------------------------------- payroll

    /** Draft payroll for a month from the salary structure, attendance (loss of pay) and running advances. */
    public function draft(Employee $e, string $month): Payroll
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $working = count(self::workingDays($start, $end));

        $att = EmployeeAttendance::where('employee_id', $e->id)->whereBetween('date', [$start->toDateString(), $end->toDateString().' 23:59:59'])->get();
        $lop = $att->where('status', 'absent')->count() + 0.5 * $att->where('status', 'half_day')->count();
        // unpaid leave (e.g. LWP) days in this month count as loss of pay
        $unpaid = LeaveType::where('is_paid', false)->pluck('id');
        foreach (EmployeeLeave::where('employee_id', $e->id)->where('status', 'approved')->whereIn('leave_type_id', $unpaid)
            ->whereDate('from_date', '<=', $end)->whereDate('to_date', '>=', $start)->get() as $l) {
            if ($l->half_day) {
                continue; // a half-day leave is marked "half day" in attendance and already counted above
            }
            // full days are marked "leave" in attendance, not "absent", so they are not counted twice
            $lop += count(self::workingDays($l->from_date->max($start), $l->to_date->min($end)));
        }
        // joined or left in the middle of the month
        $notWorking = 0;
        if ($e->joining_date && $e->joining_date->gt($start)) {
            $notWorking += count(self::workingDays($start, $e->joining_date->copy()->subDay()->max($start)));
        }
        if ($e->leaving_date && $e->leaving_date->lt($end)) {
            $notWorking += count(self::workingDays($e->leaving_date->copy()->addDay(), $end));
        }
        $lop = min($working, $lop + $notWorking);

        $p = new Payroll(['employee_id' => $e->id, 'month' => $month, 'working_days' => $working, 'lop_days' => $lop, 'paid_days' => $working - $lop, 'status' => 'draft']);
        foreach ([...array_keys(Employee::EARNINGS), ...array_keys(Employee::DEDUCTIONS)] as $k) {
            $p->$k = (float) $e->$k;
        }
        $p->lop_deduction = $working ? round($e->gross() / $working * $lop, 2) : 0;
        $p->advance_deduction = (float) SalaryAdvance::where('employee_id', $e->id)->get()->sum(fn ($a) => min((float) $a->monthly_deduction, $a->balance()));

        return $p->recalc();
    }

    /** Mark paid: advances are recovered and the salary is added to school expenses. */
    public function pay(Payroll $p, array $details, ?int $userId): void
    {
        DB::transaction(function () use ($p, $details, $userId) {
            $p->fill(['status' => 'paid', 'paid_on' => $details['paid_on'], 'method' => $details['method'], 'reference_no' => $details['reference_no'] ?? null])->save();

            $left = (float) $p->advance_deduction;
            foreach (SalaryAdvance::where('employee_id', $p->employee_id)->orderBy('given_on')->get() as $a) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, $a->balance());
                $a->increment('recovered', $take);
                $left -= $take;
            }

            $category = ExpenseCategory::where('is_salary', true)->first() ?? ExpenseCategory::create(['name' => 'Staff Salary', 'is_salary' => true]);
            Expense::create(['expense_category_id' => $category->id, 'expense_date' => $p->paid_on, 'amount' => $p->net, 'title' => 'Salary '.$p->monthLabel().' - '.$p->employee->name,
                'paid_to' => $p->employee->name, 'salary_month' => $p->month, 'payment_method' => $p->method, 'reference_no' => $p->reference_no, 'payroll_id' => $p->id, 'created_by' => $userId]);
        });
    }

    /** Undo a payment (admin): advance recovery and the expense entry are reversed. */
    public function unpay(Payroll $p): void
    {
        DB::transaction(function () use ($p) {
            $left = (float) $p->advance_deduction;
            foreach (SalaryAdvance::where('employee_id', $p->employee_id)->orderByDesc('given_on')->get() as $a) {
                if ($left <= 0) {
                    break;
                }
                $back = min($left, (float) $a->recovered);
                $a->decrement('recovered', $back);
                $left -= $back;
            }
            Expense::where('payroll_id', $p->id)->delete();
            $p->update(['status' => 'draft', 'paid_on' => null, 'method' => null, 'reference_no' => null]);
        });
    }

    // ---------------------------------------------------------------- reminders

    /** Birthdays & work anniversaries in the next $days, contracts/probation ending, documents expiring. */
    public function reminders(int $days = 30): Collection
    {
        $out = collect();
        $today = now()->startOfDay();
        foreach (Employee::active()->get() as $e) {
            foreach (['dob' => 'Birthday', 'joining_date' => 'Work anniversary'] as $col => $label) {
                if (! $e->$col) {
                    continue;
                }
                $next = $e->$col->copy()->year($today->year);
                if ($next->lt($today)) {
                    $next->addYear();
                }
                $in = (int) $today->diffInDays($next);
                if ($in <= min($days, 15) && ! ($col === 'joining_date' && $next->year === $e->$col->year)) {
                    $years = $next->year - $e->$col->year;
                    $out->push(['kind' => $col === 'dob' ? 'birthday' : 'anniversary', 'what' => $label.($col === 'joining_date' ? " ({$years} yr)" : ''), 'employee' => $e, 'date' => $next, 'days' => $in]);
                }
            }
            if ($e->contract_end && $e->contract_end->lte($today->copy()->addDays($days))) {
                $out->push(['kind' => 'contract', 'what' => Employee::EMPLOYMENT[$e->employment_type].' ends', 'employee' => $e, 'date' => $e->contract_end, 'days' => (int) $today->diffInDays($e->contract_end, false)]);
            }
        }
        foreach (EmployeeDocument::with('employee')->whereNotNull('valid_till')->whereDate('valid_till', '<=', $today->copy()->addDays($days))->get() as $d) {
            if ($d->employee?->isActive()) {
                $out->push(['kind' => 'document', 'what' => $d->title.' expires', 'employee' => $d->employee, 'date' => $d->valid_till, 'days' => (int) $today->diffInDays($d->valid_till, false)]);
            }
        }

        return $out->sortBy('days')->values();
    }
}
