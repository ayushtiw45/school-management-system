<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdmissionTest extends SchoolTestCase
{
    public function test_confirmed_admission_gets_number_and_full_year_of_fees(): void
    {
        $student = $this->admit();

        $this->assertSame('active', $student->status);
        $this->assertMatchesRegularExpression('/^ADM\d{4}-0001$/', $student->admission_no);

        // Admission fee (1) + tuition (12 months) + annual (1) + exam (2 half-years)
        $this->assertSame(16, $student->fees()->count());
        $this->assertSame(1, $student->fees()->where('title', 'like', 'Tuition Fee - Apr%')->count());
    }

    public function test_mid_year_admission_skips_past_months(): void
    {
        $session = \App\Models\AcademicSession::current();
        $student = $this->admit(['admission_date' => $session->start_date->copy()->addMonths(6)->toDateString()]);

        // Joined in October: Oct–Mar tuition (6) + admission + annual + 2nd half exam
        $this->assertSame(6, $student->fees()->where('title', 'like', 'Tuition%')->count());
        $this->assertSame(1, $student->fees()->where('title', 'like', 'Exam%')->count());
    }

    public function test_concession_applies_only_to_eligible_heads(): void
    {
        $student = $this->admit(['concession_percent' => 50]);

        $tuition = $student->fees()->where('title', 'like', 'Tuition%')->first();
        $this->assertEquals($tuition->amount / 2, $tuition->discount);
        $this->assertEquals(0, $student->fees()->where('title', 'Admission Fee')->first()->discount);
    }

    public function test_pending_admission_has_no_fees_until_confirmed(): void
    {
        $student = $this->admit(['confirm_now' => 0]);
        $this->assertSame('pending', $student->status);
        $this->assertSame(0, $student->fees()->count());

        $this->post(route('admissions.confirm', $student))->assertSessionHas('success');
        $this->assertSame('active', $student->fresh()->status);
        $this->assertSame(16, $student->fees()->count());
    }

    public function test_photo_and_documents_are_stored_privately(): void
    {
        Storage::fake('local');
        $student = $this->admit([
            'photo' => UploadedFile::fake()->image('me.jpg'),
            'documents' => [['title' => 'Birth Certificate', 'file' => UploadedFile::fake()->create('bc.pdf', 100, 'application/pdf')]],
        ]);

        Storage::disk('local')->assertExists($student->photo);
        $this->assertSame('Birth Certificate', $student->documents->first()->title);
        $this->get(route('students.photo', $student))->assertOk();
    }

    public function test_section_must_belong_to_class(): void
    {
        $otherSection = \App\Models\SchoolClass::where('name', 'Class 5')->first()->sections->first();
        $this->actingAs($this->user())
            ->post(route('admissions.store'), $this->admissionData(['section_id' => $otherSection->id]))
            ->assertSessionHasErrors('section_id');
    }

    public function test_online_application_creates_pending_admission(): void
    {
        $data = collect($this->admissionData())->except(['admission_date', 'section_id', 'confirm_now'])->all();

        $this->post(route('apply.store', $this->school), $data)->assertRedirect();

        $s = Student::latest('id')->first();
        $this->assertSame('online', $s->admission_type);
        $this->assertSame('pending', $s->status);
        $this->assertNull($s->section_id);
    }

    public function test_promotion_moves_class_and_adds_new_session_fees_without_admission_fee(): void
    {
        $student = $this->admit();
        $next = \App\Models\AcademicSession::create(['name' => 'next', 'start_date' => now()->addYear()->startOfYear()->month(4), 'end_date' => now()->addYears(2)->startOfYear()->month(3)->endOfMonth()]);
        $class2 = \App\Models\SchoolClass::where('name', 'Class 2')->first();
        foreach (\App\Models\FeeHead::all() as $h) {
            \App\Models\FeeStructure::create(['academic_session_id' => $next->id, 'school_class_id' => $class2->id, 'fee_head_id' => $h->id, 'amount' => 1000]);
        }

        $this->actingAs($this->user())->post(route('students.promote.store'), [
            'student_ids' => [$student->id],
            'to_session_id' => $next->id,
            'to_class_id' => $class2->id,
            'generate_fees' => 1,
        ])->assertSessionHas('success');

        $student->refresh();
        $this->assertSame($class2->id, $student->school_class_id);
        $this->assertSame(15, $student->fees()->where('academic_session_id', $next->id)->count());
    }
}
