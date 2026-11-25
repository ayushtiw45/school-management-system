<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SchoolTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
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
