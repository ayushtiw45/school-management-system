<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Class-wise fee structure: a grid of class × fee category amounts for one session. */
class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $session = $sessions->firstWhere('id', $request->session_id) ?? AcademicSession::current();
        if (! $session) {
            return redirect()->route('sessions.index')->with('error', 'Please create an academic session first.');
        }

        $grid = FeeStructure::where('academic_session_id', $session->id)->get()
            ->groupBy('school_class_id')
            ->map(fn ($rows) => $rows->pluck('amount', 'fee_head_id'));

        return view('fees.structure', [
            'sessions' => $sessions,
            'session' => $session,
            'classes' => SchoolClass::ordered()->get(),
            'heads' => FeeHead::orderBy('name')->get(),
            'grid' => $grid,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'amounts' => ['array'],
            'amounts.*' => ['array'],
            'amounts.*.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['amounts'] ?? [] as $classId => $heads) {
                foreach ($heads as $headId => $amount) {
                    $key = ['academic_session_id' => $data['session_id'], 'school_class_id' => $classId, 'fee_head_id' => $headId];
                    if ($amount === null || $amount === '' || (float) $amount == 0) {
                        FeeStructure::where($key)->delete();
                    } else {
                        FeeStructure::updateOrCreate($key, ['amount' => $amount]);
                    }
                }
            }
        });

        return back()->with('success', 'Fee structure saved.');
    }

    /** Assign fees from the structure to every active student of the session (new heads / missed students). */
    public function generate(Request $request, FeeService $fees)
    {
        $data = $request->validate([
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'class_id' => ['nullable', 'exists:school_classes,id'],
        ]);
        $session = AcademicSession::findOrFail($data['session_id']);

        $created = 0;
        Student::where('academic_session_id', $session->id)
            ->where('status', 'active')
            ->when($data['class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
            ->each(function (Student $s) use ($fees, $session, &$created) {
                $created += $fees->generateForStudent($s, $session);
            });

        return back()->with('success', "{$created} new fee due(s) assigned to students.");
    }
}
