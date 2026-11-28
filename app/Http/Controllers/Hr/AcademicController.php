<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\Subject;
use App\Models\Hr\TeacherSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Support\Tenant;
use Illuminate\Http\Request;

/** Subjects, class teachers and who teaches what. */
class AcademicController extends Controller
{
    public function index()
    {
        return view('staff.academics', [
            'subjects' => Subject::withCount('teachers')->orderBy('name')->get(),
            'classes' => SchoolClass::ordered()->with(['sections.classTeacher'])->get(),
            'teachers' => Employee::active()->teachers()->orderBy('name')->get(),
            'assignments' => TeacherSubject::with(['employee', 'subject', 'schoolClass', 'section'])->get()
                ->sortBy(fn ($a) => [$a->schoolClass?->sort_order, $a->section?->name, $a->subject?->name])->values(),
        ]);
    }

    public function storeSubject(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Tenant::unique('subjects', 'name')], 'code' => ['nullable', 'string', 'max:15']]);
        Subject::create($data);

        return back()->with('success', 'Subject added.');
    }

    public function destroySubject(Subject $subject)
    {
        $subject->delete();

        return back()->with('success', 'Subject removed.');
    }

    public function classTeachers(Request $request)
    {
        $data = $request->validate(['ct' => ['array'], 'ct.*' => ['nullable', Tenant::exists('employees')]]);
        foreach ($data['ct'] ?? [] as $sectionId => $employeeId) {
            Section::whereKey($sectionId)->update(['class_teacher_id' => $employeeId ?: null]);
        }

        return back()->with('success', 'Class teachers saved.');
    }

    public function storeAssignment(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', Tenant::exists('employees')], 'subject_id' => ['required', Tenant::exists('subjects')],
            'school_class_id' => ['required', Tenant::exists('school_classes')], 'section_ids' => ['nullable', 'array'], 'section_ids.*' => [Tenant::exists('sections')],
            'periods_per_week' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);
        $sections = $data['section_ids'] ?? [null];
        foreach ($sections as $sid) {
            TeacherSubject::firstOrCreate(['employee_id' => $data['employee_id'], 'subject_id' => $data['subject_id'], 'school_class_id' => $data['school_class_id'], 'section_id' => $sid],
                ['periods_per_week' => $data['periods_per_week'] ?? null]);
        }

        return back()->with('success', 'Subject assigned.');
    }

    public function destroyAssignment(TeacherSubject $assignment)
    {
        $assignment->delete();

        return back()->with('success', 'Removed.');
    }
}
