<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendance;
use App\Models\Hr\EmployeeLeave;
use App\Models\Hr\Payroll;
use App\Models\Hr\TimetableEntry;
use App\Models\Section;
use App\Services\StaffService;

class DashboardController extends Controller
{
    public function __invoke(StaffService $staff)
    {
        $active = Employee::active()->get();
        $today = EmployeeAttendance::with('employee')->whereDate('date', now())->get();
        $month = now()->format('Y-m');

        return view('staff.dashboard', [
            'counts' => ['teachers' => $active->where('type', 'teaching')->count(), 'others' => $active->where('type', 'non_teaching')->count(),
                'present' => $today->whereIn('status', ['present', 'late', 'half_day'])->count(), 'away' => $today->whereIn('status', ['absent', 'leave'])->count(),
                'unmarked' => max(0, $active->count() - $today->count()), 'pending_leaves' => EmployeeLeave::where('status', 'pending')->count()],
            'away' => $today->whereIn('status', ['absent', 'leave'])->values(),
            'subNeeds' => $staff->substitutionNeeds(now()),
            'reminders' => $staff->reminders(30),
            'noClassTeacher' => Section::with('schoolClass')->whereNull('class_teacher_id')->get(),
            'payroll' => ['monthly' => $active->sum(fn ($e) => $e->gross()), 'paid' => (float) Payroll::where('month', $month)->where('status', 'paid')->sum('net'),
                'drafts' => Payroll::where('month', $month)->where('status', 'draft')->count()],
            'timetableDone' => TimetableEntry::distinct()->count('section_id'),
            'sections' => Section::count(),
        ]);
    }
}
