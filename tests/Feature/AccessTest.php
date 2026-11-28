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
        $this->get('/apply/'.$this->school->slug)->assertOk();
        $this->get('/')->assertOk()->assertSee('TDS School');
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
        $staff = $this->user('receptionist');
        $this->actingAs($staff)->get(route('payments.create'))->assertForbidden();
        $this->actingAs($staff)->get(route('admissions.create'))->assertOk();
        $this->actingAs($staff)->get(route('enquiries.index'))->assertOk();
        $this->actingAs($staff)->get(route('enquiries.report'))->assertOk();
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();

        $acct = $this->user('accountant');
        $this->actingAs($acct)->get(route('payments.create'))->assertOk();
        $this->actingAs($acct)->get(route('admissions.create'))->assertForbidden();
        $this->actingAs($acct)->get(route('enquiries.index'))->assertForbidden();
        $this->actingAs($acct)->get(route('settings.edit'))->assertForbidden();
    }

    public function test_principal_sees_accounts_but_cannot_change_them(): void
    {
        $student = $this->admit();
        $fee = $student->fees()->first();
        $principal = $this->user('principal');
        $this->actingAs($principal);

        foreach ([route('payments.create'), route('payments.index'), route('fees.pending'), route('reports.index'), route('expenses.index'),
            route('enquiries.index'), route('admissions.index'), route('classes.index'), route('students.feeslip', $student)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post(route('payments.store', $student), ['lines' => [$fee->id => ['amount' => 100]], 'payment_date' => now()->toDateString(), 'method' => 'cash'])
            ->assertForbidden();
        $this->post(route('expenses.store'), [])->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('settings.edit'))->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    public function test_dashboard_for_every_role(): void
    {
        $this->seed(DemoDataSeeder::class);
        foreach (array_keys(\App\Models\User::ROLES) as $role) {
            $res = $this->actingAs($this->user($role))->get(route('dashboard'))->assertOk();
            $role === 'receptionist'
                ? $res->assertSee('Follow-ups due')->assertDontSee('Total Fees Collected')->assertDontSee('Recent Payments')
                : null;
            $role === 'accountant' ? $res->assertSee('Total Fees Collected')->assertDontSee('Follow-ups due') : null;
        }
    }

    public function test_enquiry_follow_up_and_daily_report(): void
    {
        $this->actingAs($this->user('receptionist'));
        $this->post(route('enquiries.store'), ['child_name' => 'Kabir', 'phone' => '9811122233', 'source' => 'phone'])->assertSessionHasNoErrors();
        $e = \App\Models\Enquiry::latest('id')->first();
        $this->assertSame('new', $e->status);

        $this->post(route('enquiries.followup', $e), ['note' => 'Will visit Saturday', 'next_follow_up' => now()->toDateString(), 'status' => 'follow_up'])
            ->assertSessionHas('success');
        $this->assertTrue($e->fresh()->followUpDue());
        $this->get(route('enquiries.index', ['view' => 'due']))->assertOk()->assertSee('Kabir');
        $this->get(route('enquiries.show', $e))->assertOk()->assertSee('Will visit Saturday');
        $this->get(route('enquiries.report'))->assertOk()->assertSee('Kabir')->assertSee('Will visit Saturday');

        $this->post(route('enquiries.followup', $e), ['note' => 'Took admission elsewhere', 'status' => 'not_interested'])->assertSessionHas('success');
        $this->assertSame('not_interested', $e->fresh()->status);
        $this->assertNull($e->fresh()->next_follow_up);
    }

    public function test_collect_fee_class_and_section_boxes(): void
    {
        $student = $this->admit();
        $pending = $this->admit(['first_name' => 'Waiting'], approve: false);
        $this->actingAs($this->user('accountant'));

        $this->get(route('payments.create'))->assertOk()->assertSee('Class 12')->assertSee('Awaiting admission fee')->assertSee('Waiting');
        $this->get(route('payments.create', ['class_id' => $student->school_class_id]))->assertOk()->assertSee('Section '.$student->section->name);
        $this->get(route('payments.create', ['class_id' => $student->school_class_id, 'section_id' => $student->section_id]))
            ->assertOk()->assertSee($student->full_name)->assertSee(inr($student->totalDue()));
        $this->get(route('payments.create', ['q' => 'Waiting']))->assertOk()->assertSee('Admission pending');
    }

    public function test_sections_can_be_added_in_bulk(): void
    {
        $this->actingAs($this->user());
        $class = \App\Models\SchoolClass::where('name', 'Class 3')->first();
        $this->post(route('sections.store', $class), ['name' => 'C-E'])->assertSessionHas('success');
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], $class->sections()->orderBy('name')->pluck('name')->all());

        $c9 = \App\Models\SchoolClass::where('name', 'Class 9')->first();
        $this->post(route('sections.bulk'), ['names' => 'C, D', 'class_ids' => [$class->id, $c9->id]])->assertSessionHas('success');
        $this->assertSame(4, $c9->sections()->count());
        $this->assertSame(5, $class->sections()->count());
    }

    public function test_school_settings_logo_signature_and_receipt(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->actingAs($this->user());
        $this->put(route('settings.update'), [
            'school_name' => 'Sunrise Public School', 'school_legal_name' => 'Sunrise Trust', 'school_state' => 'Uttar Pradesh',
            'signatory_name' => 'R. K. Verma', 'signatory_title' => 'Accountant', 'bank_account_no' => '123456789012', 'bank_ifsc' => 'sbin0001234',
            'upi_id' => 'sunrise@okaxis', 'receipt_terms' => "Fees are not refundable\nKeep this receipt", 'slip_terms' => 'Pay by 10th',
            'admission_prefix' => 'ADM', 'receipt_prefix' => 'RCPT', 'student_code_prefix' => 'sps', 'late_fine_amount' => 0, 'fee_due_day' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertSame('SBIN0001234', \App\Models\Setting::get('bank_ifsc'));
        $this->assertSame('Sunrise Public School', $this->school->fresh()->name);

        $this->post(route('settings.logo'), ['image' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 200)])->assertSessionHas('success');
        $this->post(route('settings.signature'), ['image' => \Illuminate\Http\UploadedFile::fake()->image('sign.png', 300, 80)])->assertSessionHas('success');
        $this->get(route('school.logo', $this->school->fresh()->slug))->assertOk();

        $student = $this->admit();
        $this->assertStringStartsWith('SPS', $student->student_code);
        $fee = $student->fees()->first();
        $this->actingAs($this->user('accountant'))->post(route('payments.store', $student), [
            'lines' => [$fee->id => ['amount' => $fee->balance()]], 'payment_date' => now()->toDateString(), 'method' => 'cash']);
        $this->get(route('payments.show', Payment::first()))->assertOk()
            ->assertSee('R. K. Verma')->assertSee('Keep this receipt')->assertSee('data:image/png;base64', false)->assertSee('Rupees');
        $this->get(route('students.feeslip', $student))->assertOk()->assertSee('SBIN0001234')->assertSee('upi://pay', false)->assertSee('Pay by 10th');

        // paid school: own name and logo in the menu, no TDS branding
        $this->get(route('dashboard'))->assertOk()->assertSee('Sunrise Public School')->assertDontSee('TDS School Management');
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
            route('payments.create', ['student_id' => $pending->id]), route('payments.create', ['class_id' => $student->school_class_id]),
            route('payments.create', ['class_id' => $student->school_class_id, 'section_id' => $student->section_id]),
            route('students.feeslip', $student), route('enquiries.index'), route('enquiries.create'), route('enquiries.report'), route('admissions.blank'),
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
