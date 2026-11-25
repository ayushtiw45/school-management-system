<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FeeService;
use App\Services\NumberGenerator;
use App\Support\StudentForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdmissionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $students = Student::with(['schoolClass', 'section'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status === 'confirmed' ? '!=' : '=', 'pending'))
            ->when($request->type, fn ($q, $t) => $q->where('admission_type', $t))
            ->search($request->q)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admissions.index', compact('students', 'status'));
    }

    public function create()
    {
        $session = AcademicSession::current();
        if (! $session) {
            return redirect()->route('sessions.index')->with('error', 'Please create an academic session first.');
        }

        return view('admissions.create', [
            'session' => $session,
            'classes' => SchoolClass::ordered()->with('sections')->get(),
            'nextNo' => NumberGenerator::admissionNo($session),
        ]);
    }

    public function store(Request $request, FeeService $fees)
    {
        $session = AcademicSession::current();
        abort_unless($session, 422, 'No academic session.');

        $validator = Validator::make($request->all(), StudentForm::rules());
        $validator->after(fn ($v) => StudentForm::checkSection($v, $request));
        $data = $validator->validate();

        $student = DB::transaction(function () use ($data, $request, $session, $fees) {
            $student = new Student([
                'admission_no' => NumberGenerator::admissionNo($session),
                'admission_type' => 'manual',
                'academic_session_id' => $session->id,
                'status' => 'pending',
            ]);
            StudentForm::save($student, $data, $request);

            if ($request->boolean('confirm_now')) {
                $this->doConfirm($student, $fees);
            }

            return $student;
        });

        return redirect()->route('students.show', $student)
            ->with('success', "Admission saved. Admission No: {$student->admission_no}");
    }

    public function confirm(Student $student, FeeService $fees)
    {
        abort_if($student->status !== 'pending', 422, 'Admission is already confirmed.');

        if (! $student->section_id && $student->schoolClass->sections()->exists()) {
            return back()->with('error', 'Assign a section before confirming (Edit student).');
        }

        DB::transaction(fn () => $this->doConfirm($student, $fees));

        return back()->with('success', "Admission confirmed for {$student->full_name}. Fees have been assigned.");
    }

    private function doConfirm(Student $student, FeeService $fees): void
    {
        $student->update(['status' => 'active', 'confirmed_at' => now()]);
        $fees->generateForStudent($student->fresh());
    }

    public function reject(Student $student)
    {
        abort_if($student->status !== 'pending', 422, 'Only pending admissions can be deleted.');

        foreach ($student->documents as $doc) {
            Storage::delete($doc->path);
        }
        if ($student->photo) {
            Storage::delete($student->photo);
        }
        $student->delete();

        return redirect()->route('admissions.index')->with('success', 'Admission application deleted.');
    }

    public function print(Student $student)
    {
        $student->load(['schoolClass', 'section', 'academicSession', 'fees', 'payments']);

        return view('admissions.print', compact('student'));
    }
}
