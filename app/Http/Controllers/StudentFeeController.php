<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFee;
use App\Services\FeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentFeeController extends Controller
{
    /** Students with unpaid dues up to a date. */
    public function pending(Request $request)
    {
        $upto = $request->date('upto') ?? now();

        $rows = Student::query()
            ->with(['schoolClass', 'section'])
            ->where('status', 'active')
            ->when($request->class_id, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id))
            ->search($request->q)
            ->whereHas('fees', fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto))
            ->withSum(['fees as due_amount' => fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto)], DB::raw(StudentFee::BALANCE_SQL))
            ->withCount(['fees as due_count' => fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto)])
            ->orderByDesc('due_amount')
            ->paginate(30)
            ->withQueryString();

        $total = (float) StudentFee::outstanding()->whereDate('due_date', '<=', $upto)
            ->whereHas('student', fn ($q) => $q->where('status', 'active')
                ->when($request->class_id, fn ($q, $id) => $q->where('school_class_id', $id))
                ->when($request->section_id, fn ($q, $id) => $q->where('section_id', $id)))
            ->sum(DB::raw(StudentFee::BALANCE_SQL));

        return view('fees.pending', [
            'rows' => $rows,
            'total' => $total,
            'upto' => $upto,
            'classes' => SchoolClass::ordered()->with('sections')->get(),
        ]);
    }

    /** Add a one-off fee (custom / additional) to a student. */
    public function store(Request $request, Student $student)
    {
        $data = $request->validate([
            'fee_head_id' => ['nullable', 'exists:fee_heads,id'],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
            'due_date' => ['required', 'date'],
        ]);

        $fee = new StudentFee($data + [
            'student_id' => $student->id,
            'academic_session_id' => $student->academic_session_id,
            'discount' => $data['discount'] ?? 0,
        ]);
        $fee->refreshStatus();
        $fee->save();

        return back()->with('success', 'Fee added to student.');
    }

    public function generate(Request $request, Student $student, FeeService $fees)
    {
        $data = $request->validate(['session_id' => ['required', 'exists:academic_sessions,id']]);
        abort_unless($student->status === 'active', 422, 'Student is not active.');

        $created = $fees->generateForStudent($student, AcademicSession::find($data['session_id']));

        return back()->with('success', $created ? "{$created} fee due(s) assigned from the fee structure." : 'All fees from the structure are already assigned.');
    }

    /** Edit discount / fine / due date of a due. */
    public function update(Request $request, StudentFee $studentFee)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'fine' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
        ]);

        $studentFee->fill($data);
        if ($studentFee->netAmount() < (float) $studentFee->paid) {
            return back()->with('error', 'Net amount cannot be less than what is already paid (₹'.number_format($studentFee->paid, 2).').');
        }
        $studentFee->refreshStatus();
        $studentFee->save();

        return back()->with('success', 'Fee updated.');
    }

    public function destroy(StudentFee $studentFee)
    {
        if ((float) $studentFee->paid > 0) {
            return back()->with('error', 'A fee with payments cannot be deleted. Cancel the receipt first.');
        }
        $studentFee->delete();

        return back()->with('success', 'Fee removed.');
    }
}
