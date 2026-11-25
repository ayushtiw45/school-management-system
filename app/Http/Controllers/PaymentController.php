<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Student;
use App\Services\FeeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $query = Payment::with(['student.schoolClass', 'student.section', 'receiver'])
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->when($request->method, fn ($q, $m) => $q->where('method', $m))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q->where('receipt_no', 'like', "%{$term}%")
                ->orWhereHas('student', fn ($s) => $s->search($term))));

        $summary = (clone $query)->selectRaw('method, COUNT(*) as cnt, SUM(amount) as total')->groupBy('method')->get();
        $payments = $query->latest('payment_date')->latest('id')->paginate(30)->withQueryString();

        return view('payments.index', compact('payments', 'summary', 'from', 'to'));
    }

    /** Collect fee screen: search a student, then show their dues. */
    public function create(Request $request, FeeService $fees)
    {
        $student = null;
        $dues = collect();
        $matches = collect();

        if ($request->student_id) {
            $student = Student::with(['schoolClass', 'section'])->findOrFail($request->student_id);
        } elseif ($request->q) {
            $matches = Student::with(['schoolClass', 'section'])->whereIn('status', ['active', 'inactive', 'left'])
                ->search($request->q)->orderBy('first_name')->limit(20)->get();
            if ($matches->count() === 1) {
                $student = $matches->first();
            }
        }

        if ($student) {
            $dues = $student->fees()->with('feeHead')->outstanding()->get()
                ->each(fn ($f) => $f->suggested_fine = $fees->suggestedFine($f));
        }

        return view('payments.create', compact('student', 'dues', 'matches'));
    }

    public function store(Request $request, Student $student, FeeService $fees)
    {
        $data = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.fine' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference_no' => [Rule::requiredIf(in_array($request->method, ['upi', 'bank', 'cheque', 'online'])), 'nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'cheque_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], ['reference_no.required' => 'Enter the transaction / cheque number.']);

        $payment = $fees->collect($student, $data['lines'], $data, $request->user()->id);

        return redirect()->route('payments.show', $payment)->with('success', "Payment recorded. Receipt No: {$payment->receipt_no}");
    }

    public function show(Payment $payment)
    {
        $payment->load(['student.schoolClass', 'student.section', 'items.studentFee', 'receiver']);
        $balance = $payment->student->totalDue();

        return view('payments.receipt', compact('payment', 'balance'));
    }

    public function destroy(Payment $payment, FeeService $fees)
    {
        $student = $payment->student;
        $fees->cancel($payment);

        return redirect()->route('students.show', $student)->with('success', "Receipt {$payment->receipt_no} cancelled and dues restored.");
    }
}
