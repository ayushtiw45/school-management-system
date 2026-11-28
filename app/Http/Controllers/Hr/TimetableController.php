<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\Period;
use App\Models\Hr\Subject;
use App\Models\Hr\Substitution;
use App\Models\Hr\TeacherSubject;
use App\Models\Hr\TimetableEntry;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\StaffService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimetableController extends Controller
{
    /** Class timetable grid (days × periods) for one section. */
    public function index(Request $request)
    {
        $classes = SchoolClass::ordered()->with('sections')->get();
        $section = $request->section_id ? Section::with('schoolClass', 'classTeacher')->find($request->section_id) : null;
        $grid = $section ? TimetableEntry::with(['subject', 'employee'])->where('section_id', $section->id)->get()->keyBy(fn ($e) => $e->day.'-'.$e->period_id) : collect();
        // suggested teacher for each subject of this class/section
        $suggest = $section ? TeacherSubject::where('school_class_id', $section->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $section->id))->get()->groupBy('subject_id')->map(fn ($g) => $g->first()->employee_id) : collect();

        return view('staff.timetable.index', [
            'classes' => $classes, 'section' => $section, 'grid' => $grid, 'suggest' => $suggest,
            'periods' => Period::ordered()->get(), 'subjects' => Subject::orderBy('name')->get(), 'teachers' => Employee::active()->teachers()->orderBy('name')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'section_id' => ['required', Tenant::exists('sections')], 'day' => ['required', 'integer', 'between:1,6'], 'period_id' => ['required', Tenant::exists('periods')],
            'subject_id' => ['nullable', Tenant::exists('subjects')], 'employee_id' => ['nullable', Tenant::exists('employees')], 'room' => ['nullable', 'string', 'max:20'],
        ]);
        $key = ['section_id' => $data['section_id'], 'day' => $data['day'], 'period_id' => $data['period_id']];
        if (empty($data['subject_id']) && empty($data['employee_id'])) {
            TimetableEntry::where($key)->delete();

            return back()->with('success', 'Period cleared.');
        }
        if (! empty($data['employee_id']) && ($c = StaffService::clash((int) $data['employee_id'], (int) $data['day'], (int) $data['period_id'], (int) $data['section_id']))) {
            return back()->with('error', Employee::find($data['employee_id'])->name.' already teaches '.$c->section->schoolClass->name.' '.$c->section->name.' in this period.');
        }
        TimetableEntry::updateOrCreate($key, ['subject_id' => $data['subject_id'] ?? null, 'employee_id' => $data['employee_id'] ?? null, 'room' => $data['room'] ?? null]);

        return back()->with('success', 'Saved.');
    }

    /** Copy Monday's plan to the other days (empty cells only). */
    public function copyDay(Request $request)
    {
        $data = $request->validate(['section_id' => ['required', Tenant::exists('sections')], 'from' => ['required', 'integer', 'between:1,6']]);
        $source = TimetableEntry::where('section_id', $data['section_id'])->where('day', $data['from'])->get();
        $n = 0;
        $skipped = 0;
        foreach (range(1, 6) as $day) {
            if ($day === (int) $data['from']) {
                continue;
            }
            foreach ($source as $e) {
                if (TimetableEntry::where(['section_id' => $e->section_id, 'day' => $day, 'period_id' => $e->period_id])->exists()) {
                    continue;
                }
                if ($e->employee_id && StaffService::clash($e->employee_id, $day, $e->period_id, $e->section_id)) {
                    $skipped++;

                    continue;
                }
                TimetableEntry::create(['section_id' => $e->section_id, 'day' => $day, 'period_id' => $e->period_id, 'subject_id' => $e->subject_id, 'employee_id' => $e->employee_id, 'room' => $e->room]);
                $n++;
            }
        }

        return back()->with('success', "{$n} period(s) copied.".($skipped ? " {$skipped} skipped because the teacher is busy elsewhere." : ''));
    }

    public function teacher(Request $request)
    {
        $teachers = Employee::active()->teachers()->orderBy('name')->get();
        $teacher = $request->employee_id ? Employee::find($request->employee_id) : $teachers->first();
        $grid = $teacher ? TimetableEntry::with(['section.schoolClass', 'subject'])->where('employee_id', $teacher->id)->get()->keyBy(fn ($e) => $e->day.'-'.$e->period_id) : collect();
        $load = TimetableEntry::whereNotNull('employee_id')->groupBy('employee_id')->selectRaw('employee_id, COUNT(*) as c')->pluck('c', 'employee_id');

        return view('staff.timetable.teacher', ['teachers' => $teachers, 'teacher' => $teacher, 'grid' => $grid, 'periods' => Period::ordered()->get(), 'load' => $load]);
    }

    public function periods(Request $request)
    {
        $data = $request->validate(['periods' => ['array'], 'periods.*.name' => ['required', 'string', 'max:30'], 'periods.*.start_time' => ['nullable', 'date_format:H:i'],
            'periods.*.end_time' => ['nullable', 'date_format:H:i'], 'periods.*.sort_order' => ['required', 'integer', 'min:1', 'max:30'],
            'periods.*.delete' => ['nullable'], 'new.name' => ['nullable', 'string', 'max:30'], 'new.start_time' => ['nullable', 'date_format:H:i'], 'new.end_time' => ['nullable', 'date_format:H:i']]);
        foreach ($data['periods'] ?? [] as $id => $p) {
            $period = Period::find($id);
            if (! $period) {
                continue;
            }
            if (! empty($p['delete'])) {
                $period->delete();

                continue;
            }
            $period->update(['name' => $p['name'], 'start_time' => $p['start_time'] ?? null, 'end_time' => $p['end_time'] ?? null, 'sort_order' => $p['sort_order'],
                'is_break' => ! empty($request->input("periods.$id.is_break"))]);
        }
        if (! empty($data['new']['name'])) {
            Period::create(['name' => $data['new']['name'], 'start_time' => $data['new']['start_time'] ?? null, 'end_time' => $data['new']['end_time'] ?? null,
                'sort_order' => (int) Period::max('sort_order') + 1, 'is_break' => $request->boolean('new.is_break')]);
        }

        return back()->with('success', 'Periods saved.');
    }

    /** Arrangement for absent teachers on a date. */
    public function substitutions(Request $request, StaffService $staff)
    {
        $date = $request->date('date') ?? now();
        $needs = $staff->substitutionNeeds($date);
        $free = $needs->pluck('entry.period_id')->unique()->mapWithKeys(fn ($pid) => [$pid => $staff->freeTeachers($date, $pid)]);

        return view('staff.timetable.substitutions', ['date' => $date, 'needs' => $needs, 'free' => $free,
            'done' => Substitution::with(['period', 'section.schoolClass', 'absent', 'substitute'])->whereDate('date', $date)->get()->sortBy('period.sort_order')]);
    }

    public function assignSubstitute(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'], 'period_id' => ['required', Tenant::exists('periods')], 'section_id' => ['required', Tenant::exists('sections')],
            'absent_employee_id' => ['nullable', Tenant::exists('employees')], 'substitute_employee_id' => ['nullable', Tenant::exists('employees')], 'note' => ['nullable', 'string', 'max:200'],
        ]);
        Substitution::updateOrCreate(['date' => \Carbon\Carbon::parse($data['date'])->startOfDay(), 'period_id' => $data['period_id'], 'section_id' => $data['section_id']],
            ['absent_employee_id' => $data['absent_employee_id'] ?? null, 'substitute_employee_id' => $data['substitute_employee_id'] ?? null, 'note' => $data['note'] ?? null]);

        return back()->with('success', 'Substitute assigned.');
    }
}
