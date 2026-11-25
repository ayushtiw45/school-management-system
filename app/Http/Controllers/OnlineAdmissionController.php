<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
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
    public function create()
    {
        $session = AcademicSession::current();
        abort_unless($session, 503, 'Online admissions are not open yet.');

        return view('admissions.apply', [
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
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

            return StudentForm::save($student, $data, $request);
        });

        return redirect()->route('apply.thanks', $student->admission_no);
    }

    public function thanks(string $admissionNo)
    {
        return view('admissions.thanks', compact('admissionNo'));
    }
}
