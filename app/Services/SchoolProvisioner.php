<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ExpenseCategory;
use App\Models\FeeHead;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Setting;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/** Creates a new school with its admin login and ready-to-use defaults. */
class SchoolProvisioner
{
    public function create(array $school, array $admin): User
    {
        return DB::transaction(function () use ($school, $admin) {
            $s = School::create($school);
            $user = User::create($admin + ['school_id' => $s->id, 'role' => 'admin', 'is_active' => true]);
            $this->defaults($s);

            return $user;
        });
    }

    public function defaults(School $school): void
    {
        Tenant::run($school, function () use ($school) {
            foreach (['school_name' => $school->name, 'school_phone' => (string) $school->phone, 'school_email' => (string) $school->email,
                'school_address' => trim(($school->city ?? '').', '.($school->state ?? ''), ', ')] as $k => $v) {
                Setting::put($k, $v);
            }

            $year = now()->month >= 4 ? now()->year : now()->year - 1;
            $session = AcademicSession::firstOrCreate(
                ['name' => $year.'-'.substr((string) ($year + 1), 2)],
                ['start_date' => "$year-04-01", 'end_date' => ($year + 1).'-03-31']
            );
            $session->makeCurrent();

            foreach (['Nursery', 'LKG', 'UKG', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 10', 'Class 11', 'Class 12'] as $i => $name) {
                $class = SchoolClass::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
                foreach (['A', 'B'] as $sec) {
                    $class->sections()->firstOrCreate(['name' => $sec]);
                }
            }

            foreach ([['Admission Fee', 'one_time', false], ['Tuition Fee', 'monthly', true], ['Annual Charges', 'yearly', false], ['Exam Fee', 'half_yearly', false]] as [$name, $freq, $c]) {
                FeeHead::firstOrCreate(['name' => $name], ['frequency' => $freq, 'concession_applicable' => $c]);
            }

            foreach ([['Staff Salary', true], ['Electricity & Utilities', false], ['Maintenance & Repairs', false], ['Stationery & Printing', false], ['Transport / Fuel', false], ['Other Expenses', false]] as [$name, $salary]) {
                ExpenseCategory::firstOrCreate(['name' => $name], ['is_salary' => $salary]);
            }
            StaffService::seedDefaults($school->id);
        });
    }
}
