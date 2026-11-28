<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\School;
use App\Support\Tenant;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SchoolTestCase extends TestCase
{
    use RefreshDatabase;

    /** The demo school created by DatabaseSeeder (active plan). */
    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::set(null);
        $this->seed(DatabaseSeeder::class);
        $this->school = School::where('email', 'office@sunrise.test')->firstOrFail();
        Tenant::set($this->school);
    }

    protected function user(string $role = 'admin', ?School $school = null): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true, 'school_id' => ($school ?? $this->school)->id]);
    }

    protected function admissionData(array $overrides = []): array
    {
        $class = SchoolClass::where('name', 'Class 1')->first();

        return array_merge([
            'admission_date' => AcademicSession::current()->start_date->toDateString(),
            'school_class_id' => $class->id,
            'section_id' => $class->sections->first()->id,
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'gender' => 'male',
            'dob' => '2019-05-10',
            'father_name' => 'Rakesh Sharma',
            'father_phone' => '9876543210',
            'confirm_now' => 1,
        ], $overrides);
    }

    protected function admit(array $overrides = []): Student
    {
        $this->actingAs($this->user('admission_staff'))
            ->post(route('admissions.store'), $this->admissionData($overrides))
            ->assertSessionHasNoErrors();

        return Student::latest('id')->first();
    }
}
