<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendance;
use App\Models\Hr\EmployeeLeave;
use App\Models\Hr\LeaveType;
use App\Models\Hr\Payroll;
use App\Models\Hr\Period;
use App\Models\Hr\SalaryAdvance;
use App\Models\Hr\Subject;
use App\Models\Hr\Substitution;
use App\Models\Hr\TimetableEntry;
use App\Models\SchoolClass;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StaffTest extends SchoolTestCase
{
    private function addTeacher(string $name, array $extra = []): Employee
    {
        $this->post(route('staff.employees.store'), array_merge(['type' => 'teaching', 'name' => $name, 'employment_type' => 'permanent',
            'joining_date' => now()->subYears(2)->toDateString(), 'designation' => 'TGT', 'phone' => '9876500011'], $extra))->assertRedirect()->assertSessionHasNoErrors();

        return Employee::where('name', $name)->firstOrFail();
    }

    public function test_defaults_profiles_documents_and_letters(): void
    {
        $this->assertSame(4, LeaveType::count(), 'CL, SL, EL, LWP for every school');
        $this->assertGreaterThan(5, Period::count());
        $this->assertGreaterThan(10, Subject::count());

        Storage::fake('local');
        $this->actingAs($this->user('principal'));
        $t = $this->addTeacher('Anjali Verma', ['gender' => 'female', 'qualification' => 'M.Sc. B.Ed.', 'pan_no' => 'abcde1234f', 'photo' => UploadedFile::fake()->image('p.jpg')]);
        $this->assertSame('TCH-001', $t->employee_id);
        $this->assertSame('ABCDE1234F', $t->pan_no);
        $this->post(route('staff.employees.store'), ['type' => 'non_teaching', 'name' => 'Ramu', 'employment_type' => 'contract'])->assertSessionHasNoErrors();
        $this->assertSame('STF-001', Employee::where('name', 'Ramu')->value('employee_id'));

        $this->post(route('staff.documents.store', $t), ['title' => 'B.Ed. degree', 'file' => UploadedFile::fake()->create('bed.pdf', 50, 'application/pdf')])->assertSessionHas('success');
        $this->get(route('staff.documents.show', $t->documents()->first()))->assertOk();
        $this->get(route('staff.employees.photo', $t))->assertOk();
        foreach (['joining', 'experience', 'relieving'] as $type) {
            $this->get(route('staff.letter', [$t, $type]))->assertOk()->assertSee('Anjali Verma');
        }
        $this->get(route('staff.idcards'))->assertOk()->assertSee('TCH-001');

        // leaving frees class-teacher duty
        $section = SchoolClass::where('name', 'Class 1')->first()->sections->first();
        $this->post(route('staff.classteachers'), ['ct' => [$section->id => $t->id]])->assertSessionHas('success');
        $this->assertSame($t->id, $section->fresh()->class_teacher_id);
        $this->post(route('staff.employees.exit', $t), ['status' => 'resigned', 'leaving_date' => now()->toDateString(), 'leaving_reason' => 'Moved'])->assertSessionHas('success');
        $this->assertNull($section->fresh()->class_teacher_id);
        $this->get(route('staff.employees.index'))->assertDontSee('Anjali Verma');
        $this->get(route('staff.employees.index', ['status' => 'left']))->assertSee('Anjali Verma');
    }

    public function test_timetable_clash_copy_and_substitution(): void
    {
        $this->actingAs($this->user('principal'));
        $a = $this->addTeacher('Teacher A');
        $b = $this->addTeacher('Teacher B');
        $c1 = SchoolClass::where('name', 'Class 1')->first()->sections->first();
        $c2 = SchoolClass::where('name', 'Class 2')->first()->sections->first();
        $p1 = Period::ordered()->where('is_break', false)->first();
        $maths = Subject::where('name', 'Mathematics')->first();

        $this->post(route('staff.assignments.store'), ['employee_id' => $a->id, 'subject_id' => $maths->id, 'school_class_id' => $c1->school_class_id])->assertSessionHas('success');
        $this->get(route('staff.timetable.index', ['section_id' => $c1->id]))->assertOk()->assertSee('data-teacher="'.$a->id.'"', false);

        $this->post(route('staff.timetable.save'), ['section_id' => $c1->id, 'day' => 1, 'period_id' => $p1->id, 'subject_id' => $maths->id, 'employee_id' => $a->id])->assertSessionHas('success');
        // same teacher, same time, other class → refused
        $this->post(route('staff.timetable.save'), ['section_id' => $c2->id, 'day' => 1, 'period_id' => $p1->id, 'subject_id' => $maths->id, 'employee_id' => $a->id])->assertSessionHas('error');
        $this->assertSame(1, TimetableEntry::count());

        $this->post(route('staff.timetable.copy'), ['section_id' => $c1->id, 'from' => 1])->assertSessionHas('success');
        $this->assertSame(6, TimetableEntry::where('employee_id', $a->id)->count());
        $this->get(route('staff.timetable.teacher', ['employee_id' => $a->id]))->assertOk()->assertSee('6 periods per week');

        // A absent on a weekday → substitution needed; B is free and suggested
        $day = now()->isSunday() ? now()->subDay() : now();
        $this->post(route('staff.attendance.store'), ['date' => $day->toDateString(), 'att' => [$a->id => ['status' => 'absent'], $b->id => ['status' => 'present']]])->assertSessionHas('success');
        $this->post(route('staff.attendance.store'), ['date' => $day->toDateString(), 'att' => [$a->id => ['status' => 'absent']]])->assertSessionHas('success');
        $this->assertSame(2, EmployeeAttendance::count(), 'saving again updates, never duplicates');
        $this->get(route('staff.substitutions.index', ['date' => $day->toDateString()]))->assertOk()->assertSee('Teacher B');
        $this->post(route('staff.substitutions.store'), ['date' => $day->toDateString(), 'period_id' => $p1->id, 'section_id' => $c1->id, 'absent_employee_id' => $a->id, 'substitute_employee_id' => $b->id])->assertSessionHas('success');
        $this->post(route('staff.substitutions.store'), ['date' => $day->toDateString(), 'period_id' => $p1->id, 'section_id' => $c1->id, 'absent_employee_id' => $a->id, 'substitute_employee_id' => $b->id])->assertSessionHas('success');
        $this->assertSame(1, Substitution::count());
        $this->get(route('staff.dashboard'))->assertOk()->assertSee('Teacher B');
    }

    public function test_leave_balance_and_payroll_with_loss_of_pay_advance_and_expense(): void
    {
        $this->actingAs($this->user('principal'));
        $t = $this->addTeacher('Pay Teacher', ['joining_date' => now()->subYears(3)->toDateString()]);
        $month = now()->subMonthNoOverflow()->format('Y-m');
        $start = now()->subMonthNoOverflow()->startOfMonth();
        $workdays = collect(\Carbon\CarbonPeriod::create($start, $start->copy()->endOfMonth()))->filter(fn ($d) => ! $d->isSunday())->values();

        // 2 days casual leave (paid) + 1 day LWP (unpaid) + 1 absent
        $cl = LeaveType::where('code', 'CL')->first();
        $lwp = LeaveType::where('code', 'LWP')->first();
        $this->post(route('staff.leaves.store'), ['employee_id' => $t->id, 'leave_type_id' => $cl->id, 'from_date' => $workdays[0]->toDateString(), 'to_date' => $workdays[1]->toDateString(), 'status' => 'pending'])->assertSessionHas('success');
        $leave = EmployeeLeave::firstOrFail();
        $this->put(route('staff.leaves.decide', $leave), ['status' => 'approved'])->assertSessionHas('success');
        $this->assertEquals(2, $leave->fresh()->days);
        $this->post(route('staff.leaves.store'), ['employee_id' => $t->id, 'leave_type_id' => $lwp->id, 'from_date' => $workdays[2]->toDateString(), 'to_date' => $workdays[2]->toDateString(), 'status' => 'approved']);
        $this->post(route('staff.attendance.store'), ['date' => $workdays[3]->toDateString(), 'att' => [$t->id => ['status' => 'absent']]]);
        $this->assertSame(3, EmployeeAttendance::where('employee_id', $t->id)->where('status', 'leave')->count());
        $bal = app(\App\Services\StaffService::class)->balances($t)->firstWhere('type.code', 'CL');
        if ($start->gte(\App\Services\StaffService::leaveYear()[0])) {
            $this->assertEquals(10, $bal['balance']);
        }

        // principal cannot touch salary
        $this->put(route('staff.employees.salary', $t), ['basic' => 20000])->assertForbidden();

        // accounts: salary structure, advance, payroll
        $this->actingAs($this->user('accountant'));
        $this->put(route('staff.employees.salary', $t), ['basic' => 20000, 'hra' => 4000, 'da' => 2000, 'pf' => 1800])->assertSessionHas('success');
        $this->post(route('staff.advances.store'), ['employee_id' => $t->id, 'given_on' => $start->toDateString(), 'amount' => 5000, 'monthly_deduction' => 2000])->assertSessionHas('success');
        $this->post(route('staff.payroll.generate'), ['month' => $month])->assertSessionHas('success');
        $p = Payroll::where('employee_id', $t->id)->firstOrFail();

        $working = $workdays->count();
        $this->assertEquals($working, $p->working_days);
        $this->assertEquals(2, $p->lop_days, '1 LWP + 1 absent; paid casual leave is not cut');
        $this->assertEquals(26000, $p->gross);
        $this->assertEquals(round(26000 / $working * 2, 2), (float) $p->lop_deduction);
        $this->assertEquals(2000, $p->advance_deduction);
        $this->assertEquals(round(26000 - 1800 - 2000 - round(26000 / $working * 2, 2), 2), (float) $p->net);

        // edit: bonus
        $this->put(route('staff.payroll.update', $p), array_merge($p->only(['working_days', 'lop_days', 'basic', 'hra', 'da', 'conveyance', 'other_allowance', 'pf', 'esi', 'tds', 'other_deduction', 'advance_deduction', 'lop_deduction']), ['bonus' => 1000]))
            ->assertSessionHas('success');
        $net = (float) $p->fresh()->net;

        // pay → expense + advance recovered
        $this->post(route('staff.payroll.pay'), ['ids' => [$p->id], 'paid_on' => now()->toDateString(), 'method' => 'bank', 'reference_no' => 'UTR1'])->assertSessionHas('success');
        $this->assertSame('paid', $p->fresh()->status);
        $this->assertEquals($net, (float) Expense::where('payroll_id', $p->id)->value('amount'));
        $this->assertEquals(2000, SalaryAdvance::first()->recovered);
        $this->get(route('staff.payroll.slip', $p))->assertOk()->assertSee('Pay Teacher')->assertSee('Advance recovery')->assertSee('Rupees');
        $this->put(route('staff.payroll.update', $p), ['working_days' => 1, 'lop_days' => 0])->assertStatus(422);

        // only admin can undo
        $this->post(route('staff.payroll.unpay', $p))->assertForbidden();
        $this->actingAs($this->user('admin'))->post(route('staff.payroll.unpay', $p))->assertSessionHas('success');
        $this->assertSame(0, Expense::where('payroll_id', $p->id)->count());
        $this->assertEquals(0, SalaryAdvance::first()->recovered);

        foreach (array_keys(\App\Http\Controllers\Hr\ReportController::REPORTS) as $type) {
            $this->get(route('staff.reports.show', [$type, 'month' => $month]))->assertOk();
        }
        $this->get(route('staff.reports.show', ['salary', 'month' => $month, 'format' => 'xlsx']))->assertOk();
        $this->get(route('staff.reports.show', ['directory', 'format' => 'pdf']))->assertOk();
    }

    public function test_every_page_and_permissions(): void
    {
        $this->actingAs($this->user('principal'));
        $t = $this->addTeacher('Page Teacher', ['dob' => now()->addDays(3)->subYears(30)->toDateString()]);
        $this->get(route('staff.dashboard'))->assertOk()->assertSee('Birthday');

        $this->actingAs($this->user('accountant'));
        $this->put(route('staff.employees.salary', $t), ['basic' => 10000]);
        $this->post(route('staff.payroll.generate'), ['month' => now()->format('Y-m')]);
        $p = Payroll::first();
        $this->post(route('staff.advances.store'), ['employee_id' => $t->id, 'given_on' => now()->toDateString(), 'amount' => 1000, 'monthly_deduction' => 500]);

        $this->actingAs($this->user('admin'));
        $section = SchoolClass::where('name', 'Class 1')->first()->sections->first();
        foreach ([route('staff.dashboard'), route('staff.employees.index'), route('staff.employees.create'), route('staff.employees.show', $t), route('staff.employees.edit', $t),
            route('staff.academics'), route('staff.attendance.index'), route('staff.attendance.monthly'), route('staff.leaves.index'), route('staff.leaves.index', ['balances' => 1]),
            route('staff.timetable.index'), route('staff.timetable.index', ['section_id' => $section->id]), route('staff.timetable.teacher'), route('staff.substitutions.index'),
            route('staff.payroll.index'), route('staff.payroll.edit', $p), route('staff.payroll.slips'), route('staff.advances.index'), route('staff.reports.index')] as $url) {
            $this->get($url)->assertOk();
        }

        // accounts: can view staff, cannot add; can run payroll
        $this->actingAs($this->user('accountant'));
        $this->get(route('staff.employees.index'))->assertOk()->assertSee('View only');
        $this->post(route('staff.employees.store'), ['type' => 'teaching', 'name' => 'X', 'employment_type' => 'permanent'])->assertForbidden();
        $this->get(route('staff.payroll.index'))->assertOk();
        // reception & transport: no access
        $this->actingAs($this->user('receptionist'))->get(route('staff.dashboard'))->assertForbidden();
        $this->actingAs($this->user('transport'))->get(route('staff.employees.index'))->assertForbidden();
        // principal sees payroll, cannot pay
        $this->actingAs($this->user('principal'));
        $this->get(route('staff.payroll.index'))->assertOk();
        $this->post(route('staff.payroll.pay'), ['ids' => [$p->id], 'paid_on' => now()->toDateString(), 'method' => 'cash'])->assertForbidden();
        // other school cannot open this teacher
        $other = \App\Models\School::create(['name' => 'Other', 'email' => 'o2@x.test', 'paid_until' => now()->addMonth()]);
        $this->actingAs($this->user('admin', $other))->get(route('staff.employees.show', $t))->assertNotFound();
    }
}
