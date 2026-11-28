<?php

namespace Database\Seeders;

use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\SchoolProvisioner;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/**
 * Local development data: the platform owner (super admin) and one demo school with an active plan.
 *   Super admin:  owner@tds.test / password   → /super
 *   School admin: admin@school.test / password → /dashboard
 * On a live server the installer creates the super admin instead; schools sign up themselves.
 */
class DatabaseSeeder extends Seeder
{
    public function run(SchoolProvisioner $provisioner): void
    {
        $owner = User::firstOrCreate(['email' => 'owner@tds.test'], ['name' => 'Platform Owner', 'role' => 'admin', 'is_active' => true, 'password' => 'password']);
        $owner->forceFill(['is_super_admin' => true])->save();

        if (User::where('email', 'admin@school.test')->exists()) {
            return;
        }
        $admin = $provisioner->create(
            ['name' => 'Sunrise Public School', 'phone' => '9876543210', 'email' => 'office@sunrise.test', 'city' => 'Kanpur', 'state' => 'Uttar Pradesh'],
            ['name' => 'Administrator', 'email' => 'admin@school.test', 'password' => 'password'],
        );
        $school = $admin->school;
        $school->forceFill(['trial_used' => true])->save();
        $school->extendUntil(now()->addMonth());

        // sample fee amounts for the demo school
        Tenant::run($school, function () {
            $session = AcademicSession::current();
            $heads = FeeHead::pluck('id', 'name');
            foreach (SchoolClass::ordered()->get() as $i => $class) {
                foreach (['Admission Fee' => 2000, 'Tuition Fee' => 800 + 100 * $i, 'Annual Charges' => 1500, 'Exam Fee' => 300 + 50 * max(0, $i - 2)] as $head => $amount) {
                    FeeStructure::firstOrCreate(
                        ['academic_session_id' => $session->id, 'school_class_id' => $class->id, 'fee_head_id' => $heads[$head]],
                        ['amount' => $amount]
                    );
                }
            }
        });
    }
}
