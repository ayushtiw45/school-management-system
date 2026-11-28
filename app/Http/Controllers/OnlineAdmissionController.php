<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\School;
use App\Support\Tenant;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\NumberGenerator;
use App\Support\StudentForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Public form parents can fill online. Creates a "pending" admission for staff to review. */
class OnlineAdmissionController extends Controller
{
    public function create(School $school)
    {
        $this->open($school);
        $session = AcademicSession::current();
        abort_unless($session, 503, 'Online admissions are not open yet.');

        return view('admissions.apply', [
            'school' => $school,
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
        ]);
    }

    public function store(Request $request, School $school)
    {
        $this->open($school);
        $session = AcademicSession::current();
        abort_unless($session, 503);

        $validator = Validator::make($request->all(), StudentForm::rules(public: true));
        $data = $validator->validate();
        unset($data['section_id']);

        $student = DB::transaction(function () use ($data, $request, $session) {
            $student = new Student([
                'admission_no' => NumberGenerator::admissionNo($session),
                'admission_type' => 'online',
                'admission_date' => now()->toDateString(),
                'academic_session_id' => $session->id,
                'status' => 'pending',
            ]);

            StudentForm::save($student, $data, $request);
            app(\App\Services\FeeService::class)->prepareAdmission($student->fresh());

            return $student;
        });

        return redirect()->route('apply.thanks', [$school, $student->admission_no]);
    }

    /** Blank form parents can print and fill by hand. */
    public function blankForm(School $school)
    {
        $this->open($school);

        return view('admissions.blank', ['classes' => SchoolClass::ordered()->get(), 'session' => AcademicSession::current(), 'school' => $school]);
    }

    public function thanks(School $school, string $admissionNo)
    {
        $this->open($school);
        return view('admissions.thanks', compact('admissionNo', 'school'));
    }

    /** The public form works as the school in the link, and only while its plan is active. */
    private function open(School $school): void
    {
        abort_unless($school->accessState() === 'active', 404);
        Tenant::set($school);
    }
}
