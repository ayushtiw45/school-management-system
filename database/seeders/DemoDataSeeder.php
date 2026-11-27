<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
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

        // Expenses for each month so far
        $cats = ExpenseCategory::pluck('id', 'name');
        foreach ($session->months() as $m) {
            if ($m->isFuture()) {
                break;
            }
            foreach (['Anita Verma', 'Suresh Kumar', 'Meena Joshi'] as $teacher) {
                Expense::create(['expense_category_id' => $cats['Staff Salary'], 'expense_date' => $m->copy()->endOfMonth()->min(now()), 'amount' => 15000,
                    'title' => 'Teacher salary', 'paid_to' => $teacher, 'salary_month' => $m->format('Y-m'), 'payment_method' => 'bank', 'created_by' => $admin?->id]);
            }
            Expense::create(['expense_category_id' => $cats['Electricity & Utilities'], 'expense_date' => $m->copy()->day(15)->min(now()), 'amount' => rand(3000, 6000),
                'title' => 'Electricity bill', 'paid_to' => 'State Electricity Board', 'payment_method' => 'upi', 'created_by' => $admin?->id]);
            if (rand(0, 1)) {
                Expense::create(['expense_category_id' => $cats['Maintenance & Repairs'], 'expense_date' => $m->copy()->day(20)->min(now()), 'amount' => rand(500, 4000),
                    'title' => 'Classroom repairs', 'paid_to' => 'Local contractor', 'payment_method' => 'cash', 'created_by' => $admin?->id]);
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
