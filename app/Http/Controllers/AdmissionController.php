<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Enquiry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FeeService;
use App\Services\NotificationService;
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

    public function create(Request $request)
    {
        $session = AcademicSession::current();
        if (! $session) {
            return redirect()->route(auth()->user()->hasRole('admin', 'principal') ? 'sessions.index' : 'dashboard')
                ->with('error', 'Please ask the admin to create an academic session first.');
        }
        $enquiry = $request->enquiry ? Enquiry::find($request->enquiry) : null;

        return view('admissions.create', [
            'session' => $session,
            'classes' => SchoolClass::ordered()->with('sections')->get(),
            'nextNo' => NumberGenerator::admissionNo($session),
            'enquiry' => $enquiry,
        ]);
    }

    /** Empty admission form to print and give to parents. */
    public function blankForm()
    {
        return view('admissions.blank', ['classes' => SchoolClass::ordered()->get(), 'session' => AcademicSession::current()]);
    }

    public function store(Request $request, FeeService $fees, NotificationService $notifier)
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
            $fees->prepareAdmission($student->fresh());

            if ($request->enquiry_id && $enquiry = Enquiry::find($request->enquiry_id)) {
                $enquiry->update(['status' => 'converted', 'student_id' => $student->id, 'next_follow_up' => null]);
            }

            return $student;
        });

        return redirect()->route('students.show', $student)
            ->with('success', "Admission form saved (No. {$student->admission_no}). It will be confirmed when the admission fee is paid at the accounts desk.");
    }

    public function confirm(Student $student, FeeService $fees, NotificationService $notifier)
    {
        abort_if($student->status !== 'pending', 422, 'Admission is already confirmed.');

        if (! $student->section_id && $student->schoolClass->sections()->exists()) {
            return back()->with('error', 'Assign a section before confirming (Edit student).');
        }

        DB::transaction(fn () => $fees->confirmAdmission($student));
        $student->refresh();
        $notifier->admissionConfirmed($student);

        return back()->with('success', "Admission approved for {$student->full_name}. Student ID: {$student->student_code}.");
    }

    public function reject(Student $student)
    {
        abort_if($student->status !== 'pending', 422, 'Only pending admissions can be deleted.');
        abort_if($student->payments()->exists(), 422, 'This admission already has a payment.');

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
