<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\FeeService;
use App\Support\StudentForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'active');

        $students = Student::with(['schoolClass', 'section'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->class_id, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id))
            ->when($request->gender, fn ($q, $g) => $q->where('gender', $g))
            ->search($request->q)
            ->orderBy('school_class_id')->orderBy('section_id')->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'status' => $status,
            'classes' => SchoolClass::ordered()->with('sections')->get(),
        ]);
    }

    public function show(Student $student)
    {
        $student->load(['schoolClass', 'section', 'academicSession', 'documents', 'fees.feeHead', 'fees.academicSession', 'payments.receiver']);

        return view('students.show', [
            'student' => $student,
            'feeHeads' => FeeHead::orderBy('name')->get(),
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
        ]);
    }

    public function edit(Student $student)
    {
        return view('students.edit', [
            'student' => $student,
            'classes' => SchoolClass::ordered()->with('sections')->get(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $validator = Validator::make($request->all(), StudentForm::rules());
        $validator->after(fn ($v) => StudentForm::checkSection($v, $request));
        $data = $validator->validate();

        if ($request->hasFile('photo') && $student->photo) {
            Storage::delete($student->photo);
        }
        StudentForm::save($student, $data, $request);

        return redirect()->route('students.show', $student)->with('success', 'Student details updated.');
    }

    public function updateStatus(Request $request, Student $student)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        abort_if($student->status === 'pending', 422, 'Confirm the admission first.');

        $student->update($data + ($data['status'] === 'active' ? ['leaving_date' => null, 'leaving_reason' => null] : []));

        return back()->with('success', 'Student status changed to '.Student::STATUSES[$data['status']].'.');
    }

    public function photo(Student $student)
    {
        abort_unless($student->photo && Storage::exists($student->photo), 404);

        return Storage::response($student->photo);
    }

    public function storeDocument(Request $request, Student $student)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);

        $student->documents()->create([
            'title' => $data['title'],
            'path' => $request->file('file')->store('students/documents'),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function showDocument(Student $student, StudentDocument $document)
    {
        abort_unless($document->student_id === $student->id && Storage::exists($document->path), 404);

        return Storage::response($document->path);
    }

    public function destroyDocument(Student $student, StudentDocument $document)
    {
        abort_unless($document->student_id === $student->id, 404);
        Storage::delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function idCard(Student $student)
    {
        $student->load(['schoolClass', 'section', 'academicSession']);

        return view('students.idcards', ['students' => collect([$student])]);
    }

    public function idCards(Request $request)
    {
        $request->validate(['class_id' => ['required', \App\Support\Tenant::exists('school_classes')]]);

        $students = Student::with(['schoolClass', 'section', 'academicSession'])
            ->where('status', 'active')
            ->where('school_class_id', $request->class_id)
            ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id))
            ->orderBy('section_id')->orderBy('first_name')
            ->get();

        return view('students.idcards', compact('students'));
    }

    public function leaveForm(Student $student)
    {
        $student->load(['schoolClass', 'section']);

        return view('students.leave', ['student' => $student, 'due' => $student->totalDue()]);
    }

    public function leave(Request $request, Student $student)
    {
        $data = $request->validate([
            'leaving_date' => ['required', 'date'],
            'leaving_reason' => ['required', 'string', 'max:255'],
            'tc_number' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'cancel_future_fees' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($student, $data, $request) {
            $student->update([
                'status' => 'left',
                'leaving_date' => $data['leaving_date'],
                'leaving_reason' => $data['leaving_reason'],
                'remarks' => $data['remarks'] ?? $student->remarks,
            ]);

            if ($request->boolean('cancel_future_fees')) {
                // Remove unpaid dues that fall after the leaving date
                $student->fees()->where('due_date', '>', $data['leaving_date'])
                    ->where('paid', 0)->delete();
            }
        });

        return redirect()->route('students.show', $student)->with('success', 'Student marked as left / transferred.');
    }

    public function promoteForm(Request $request)
    {
        $classes = SchoolClass::ordered()->with('sections')->get();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $students = collect();

        if ($request->from_class_id) {
            $students = Student::with('section')
                ->where('status', 'active')
                ->where('school_class_id', $request->from_class_id)
                ->when($request->from_section_id, fn ($q, $id) => $q->where('section_id', $id))
                ->orderBy('first_name')->get();
            $students->each(fn ($s) => $s->due = $s->totalDue());
        }

        return view('students.promote', compact('classes', 'sessions', 'students'));
    }

    public function promote(Request $request, FeeService $fees)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', \App\Support\Tenant::exists('students')],
            'to_session_id' => ['required', \App\Support\Tenant::exists('academic_sessions')],
            'to_class_id' => ['required', \App\Support\Tenant::exists('school_classes')],
            'to_section_id' => ['nullable', \App\Support\Tenant::exists('sections')->where('school_class_id', $request->to_class_id)],
            'generate_fees' => ['nullable', 'boolean'],
        ]);

        $session = AcademicSession::findOrFail($data['to_session_id']);

        $count = DB::transaction(function () use ($data, $session, $fees, $request) {
            $students = Student::whereIn('id', $data['student_ids'])->where('status', 'active')->get();
            foreach ($students as $student) {
                $student->update([
                    'academic_session_id' => $session->id,
                    'school_class_id' => $data['to_class_id'],
                    'section_id' => $data['to_section_id'] ?? null,
                    'roll_no' => null,
                ]);
                if ($request->boolean('generate_fees')) {
                    $fees->generateForStudent($student->fresh(), $session, includeOneTime: false);
                }
            }

            return $students->count();
        });

        $class = SchoolClass::find($data['to_class_id']);

        return redirect()->route('students.promote')->with('success', "{$count} student(s) promoted to {$class->name} ({$session->name}).");
    }
}
