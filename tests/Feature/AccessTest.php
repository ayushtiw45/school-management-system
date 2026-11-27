<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Student;
use Database\Seeders\DemoDataSeeder;

class AccessTest extends SchoolTestCase
{
    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->get('/apply')->assertOk();
    }

    public function test_login_and_inactive_user_blocked(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));

        auth()->logout();
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_role_permissions(): void
    {
        $staff = $this->user('admission_staff');
        $this->actingAs($staff)->get(route('payments.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('admissions.create'))->assertOk();
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();

        $acct = $this->user('accountant');
        $this->actingAs($acct)->get(route('payments.create'))->assertOk();
        $this->actingAs($acct)->get(route('admissions.create'))->assertForbidden();
        $this->actingAs($acct)->get(route('settings.edit'))->assertForbidden();
    }

    /** Every screen renders with demo data for admin. */
    public function test_all_admin_pages_render(): void
    {
        $this->seed(DemoDataSeeder::class);
        $student = Student::where('status', 'active')->first();
        $pending = Student::where('status', 'pending')->first();
        $payment = Payment::first();
        $this->actingAs($this->user());

        $urls = [
            route('dashboard'), route('admissions.index'), route('admissions.index', ['status' => 'all']), route('admissions.create'),
            route('admissions.print', $student), route('students.index'), route('students.index', ['class_id' => $student->school_class_id, 'q' => 'a']),
            route('students.show', $student), route('students.show', $pending), route('students.edit', $student), route('students.idcard', $student),
            route('students.idcards', ['class_id' => $student->school_class_id]), route('students.leave', $student),
            route('students.promote'), route('students.promote', ['from_class_id' => $student->school_class_id]),
            route('fee-heads.index'), route('fee-structure.index'), route('fees.pending'), route('payments.index'),
            route('payments.create'), route('payments.create', ['q' => $student->admission_no]), route('payments.create', ['student_id' => $student->id]),
            route('payments.show', $payment), route('classes.index'), route('sessions.index'), route('users.index'), route('users.create'),
            route('settings.edit'), route('password.edit'), route('reports.index'), route('expenses.index'), route('expenses.create'), route('expense-categories.index'), route('notifications.index'), route('notifications.announce'), route('notifications.settings'), route('classes.sections.json', $student->school_class_id),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_leaving_removes_future_unpaid_fees(): void
    {
        $student = $this->admit();
        $before = $student->fees()->count();

        $this->post(route('students.leave.store', $student), [
            'leaving_date' => $student->academicSession->start_date->copy()->addMonths(2)->toDateString(),
            'leaving_reason' => 'Transfer',
            'cancel_future_fees' => 1,
        ])->assertRedirect();

        $this->assertSame('left', $student->fresh()->status);
        $this->assertLessThan($before, $student->fees()->count());
    }
}
