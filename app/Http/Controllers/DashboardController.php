<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $session = AcademicSession::current();
        $today = now()->toDateString();

        $stats = [
            'students' => Student::where('status', 'active')->count(),
            'admissions' => Student::when($session, fn ($q) => $q->where('academic_session_id', $session->id))
                ->whereIn('status', ['active', 'inactive', 'left'])->count(),
            'pending_admissions' => Student::where('status', 'pending')->count(),
            'total_collection' => (float) Payment::when($session, fn ($q) => $q->whereBetween('payment_date', [$session->start_date, $session->end_date]))->sum('amount'),
            'today_collection' => (float) Payment::whereDate('payment_date', $today)->sum('amount'),
            'today_receipts' => Payment::whereDate('payment_date', $today)->count(),
            'pending_fees' => (float) StudentFee::outstanding()->whereDate('due_date', '<=', $today)
                ->whereHas('student', fn ($q) => $q->where('status', 'active'))
                ->sum(DB::raw(StudentFee::BALANCE_SQL)),
        ];

        $stats['month_expenses'] = (float) Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');
        $stats['month_collection'] = (float) Payment::whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount');

        $classStats = SchoolClass::ordered()
            ->withCount([
                'students as active_count' => fn ($q) => $q->where('status', 'active'),
                'students as boys' => fn ($q) => $q->where('status', 'active')->where('gender', 'male'),
                'students as girls' => fn ($q) => $q->where('status', 'active')->where('gender', 'female'),
            ])->get();

        $recentAdmissions = Student::with(['schoolClass', 'section'])->latest()->take(6)->get();
        $recentPayments = Payment::with('student.schoolClass')->latest()->take(6)->get();

        return view('dashboard', compact('session', 'stats', 'classStats', 'recentAdmissions', 'recentPayments'));
    }
}
