<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ExpenseCategory;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Base setup: admin login, Nursery → Class 8 with sections A & B, the current session,
 * common fee categories and a sample fee structure (edit amounts from the Fee Structure screen).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Desktop installs create the admin on the first-run setup screen instead.
        if (! config('license.enabled')) {
            User::firstOrCreate(
                ['email' => env('ADMIN_EMAIL', 'admin@school.test')],
                ['name' => 'Administrator', 'role' => 'admin', 'is_active' => true, 'password' => env('ADMIN_PASSWORD', 'password')]
            );
        }

        $year = now()->month >= 4 ? now()->year : now()->year - 1;
        $session = AcademicSession::firstOrCreate(
            ['name' => $year.'-'.substr((string) ($year + 1), 2)],
            ['start_date' => "$year-04-01", 'end_date' => ($year + 1).'-03-31']
        );
        $session->makeCurrent();

        $names = ['Nursery', 'LKG', 'UKG', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8'];
        foreach ($names as $i => $name) {
            $class = SchoolClass::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
            foreach (['A', 'B'] as $sec) {
                $class->sections()->firstOrCreate(['name' => $sec]);
            }
        }

        $heads = [
            ['Admission Fee', 'one_time', false],
            ['Tuition Fee', 'monthly', true],
            ['Annual Charges', 'yearly', false],
            ['Exam Fee', 'half_yearly', false],
        ];
        foreach ($heads as [$name, $freq, $concession]) {
            FeeHead::firstOrCreate(['name' => $name], ['frequency' => $freq, 'concession_applicable' => $concession]);
        }

        foreach ([['Staff Salary', true], ['Electricity & Utilities', false], ['Maintenance & Repairs', false], ['Stationery & Printing', false], ['Transport / Fuel', false], ['Other Expenses', false]] as [$name, $salary]) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_salary' => $salary]);
        }

        // Sample amounts — the school will change these
        if (! config('license.enabled') && ! FeeStructure::where('academic_session_id', $session->id)->exists()) {
            $admission = FeeHead::where('name', 'Admission Fee')->first();
            $tuition = FeeHead::where('name', 'Tuition Fee')->first();
            $annual = FeeHead::where('name', 'Annual Charges')->first();
            $exam = FeeHead::where('name', 'Exam Fee')->first();

            foreach (SchoolClass::ordered()->get() as $i => $class) {
                $rows = [
                    $admission->id => 2000,
                    $tuition->id => 800 + 100 * $i,
                    $annual->id => 1500,
                    $exam->id => 300 + 50 * max(0, $i - 2),
                ];
                foreach ($rows as $headId => $amount) {
                    FeeStructure::create([
                        'academic_session_id' => $session->id,
                        'school_class_id' => $class->id,
                        'fee_head_id' => $headId,
                        'amount' => $amount,
                    ]);
                }
            }
        }
    }
}
