<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\FeeService;
use App\Services\NumberGenerator;
use Illuminate\Database\Seeder;

/** Optional sample students and payments for a demo: php artisan db:seed --class=DemoDataSeeder */
class DemoDataSeeder extends Seeder
{
    public function run(FeeService $fees): void
    {
        $session = AcademicSession::current();
        $admin = User::where('role', 'admin')->first();
        $faker = fake('en_IN');

        User::firstOrCreate(['email' => 'accounts@school.test'], ['name' => 'Accountant', 'role' => 'accountant', 'password' => 'password']);
        User::firstOrCreate(['email' => 'office@school.test'], ['name' => 'Admission Desk', 'role' => 'admission_staff', 'password' => 'password']);

        foreach (SchoolClass::with('sections')->get() as $class) {
            foreach (range(1, 4) as $n) {
                $gender = $faker->randomElement(['male', 'female']);
                $student = Student::create([
                    'admission_no' => NumberGenerator::admissionNo($session),
                    'admission_date' => $session->start_date->copy()->addDays(rand(0, 20)),
                    'admission_type' => 'manual',
                    'status' => 'active',
                    'confirmed_at' => now(),
                    'academic_session_id' => $session->id,
                    'school_class_id' => $class->id,
                    'section_id' => $class->sections->random()->id,
                    'roll_no' => $n,
                    'first_name' => $faker->firstName($gender),
                    'last_name' => $faker->lastName(),
                    'gender' => $gender,
                    'dob' => now()->subYears(3 + $class->sort_order)->subDays(rand(0, 360)),
                    'father_name' => $faker->name('male'),
                    'father_phone' => '9'.$faker->numerify('#########'),
                    'mother_name' => $faker->name('female'),
                    'city' => $faker->city(),
                    'state' => $faker->state(),
                ]);
                $fees->generateForStudent($student);

                // Pay the first few dues for some students
                $due = $student->fees()->outstanding()->whereDate('due_date', '<=', now())->take(rand(0, 4))->get();
                if ($due->count()) {
                    $fees->collect($student, $due->mapWithKeys(fn ($f) => [$f->id => ['amount' => $f->balance()]])->all(), [
                        'method' => $faker->randomElement(['cash', 'upi', 'cash']),
                        'reference_no' => null,
                        'payment_date' => now()->subDays(rand(0, 30))->toDateString(),
                    ], $admin?->id);
                }
            }
        }

        // A couple of online applications waiting for review
        foreach (range(1, 2) as $n) {
            Student::create([
                'admission_no' => NumberGenerator::admissionNo($session),
                'admission_date' => now(),
                'admission_type' => 'online',
                'status' => 'pending',
                'academic_session_id' => $session->id,
                'school_class_id' => SchoolClass::inRandomOrder()->first()->id,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'gender' => 'female',
                'dob' => now()->subYears(6),
                'father_name' => $faker->name('male'),
                'father_phone' => '9'.$faker->numerify('#########'),
            ]);
        }
    }
}
