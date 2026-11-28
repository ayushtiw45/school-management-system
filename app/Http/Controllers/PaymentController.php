<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFee;
use App\Services\FeeService;
use App\Services\NotificationService;
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
            $matches = Student::with(['schoolClass', 'section'])->whereIn('status', ['pending', 'active', 'inactive', 'left'])
                ->search($request->q)->orderBy('first_name')->limit(20)->get();
            if ($matches->count() === 1) {
                $student = $matches->first();
            }
        }

        if ($student) {
            $fees->prepareAdmission($student);
            $dues = $student->fees()->with('feeHead')->outstanding()->get()
                ->each(fn ($f) => $f->suggested_fine = $fees->suggestedFine($f));
        }

        $browse = (! $student && ! $request->q) ? $this->browse($request) : null;

        return view('payments.create', compact('student', 'dues', 'matches', 'browse'));
    }

    /** Collect-fee home: boxes per class → per section → students with dues. */
    private function browse(Request $request): array
    {
        // admissions saved before v1.2.0 have no dues yet: create them so the admission fee shows
        $fees = app(FeeService::class);
        Student::where('status', 'pending')->whereDoesntHave('fees')->limit(50)->get()->each(fn ($st) => $fees->prepareAdmission($st));

        $due = 'COALESCE(SUM(student_fees.amount - student_fees.discount + student_fees.fine - student_fees.paid), 0)';
        $sub = fn (bool $overdue) => StudentFee::selectRaw($due)->whereColumn('student_fees.student_id', 'students.id')
            ->where('student_fees.status', '!=', 'paid')->when($overdue, fn ($q) => $q->whereDate('student_fees.due_date', '<', now()->toDateString()));

        $all = Student::whereIn('students.status', ['pending', 'active'])
            ->select('students.id', 'students.school_class_id', 'students.section_id', 'students.status')
            ->selectSub($sub(false), 'due')->selectSub($sub(true), 'overdue')
            ->get();

        $stats = fn ($rows) => ['students' => $rows->count(), 'pending' => $rows->where('due', '>', 0.001)->count(),
            'due' => (float) $rows->sum('due'), 'overdue' => (float) $rows->sum('overdue')];

        $classes = SchoolClass::ordered()->with('sections')->get();
        $byClass = $all->groupBy('school_class_id');
        $class = $request->class_id ? $classes->firstWhere('id', (int) $request->class_id) : null;

        $sections = null;
        $list = null;
        if ($class) {
            $rows = $byClass->get($class->id, collect());
            $bySection = $rows->groupBy(fn ($r) => $r->section_id ?: 'none');
            $sections = $class->sections->map(fn ($sec) => ['id' => $sec->id, 'name' => $sec->name] + $stats($bySection->get($sec->id, collect())));
            if ($bySection->has('none')) {
                $sections->push(['id' => 'none', 'name' => 'No section'] + $stats($bySection->get('none')));
            }

            $sectionId = $request->section_id ?? ($class->sections->isEmpty() ? 'none' : null);
            if ($sectionId !== null) {
                $ids = $rows->filter(fn ($r) => (string) ($r->section_id ?: 'none') === (string) $sectionId)
                    ->when(! $request->boolean('everyone'), fn ($c) => $c->where('due', '>', 0.001))->keyBy('id');
                $list = Student::with(['schoolClass', 'section'])->whereIn('id', $ids->keys())->orderBy('roll_no')->orderBy('first_name')->get()
                    ->each(function ($st) use ($ids) {
                        $st->due_total = (float) $ids[$st->id]->due;
                        $st->overdue_total = (float) $ids[$st->id]->overdue;
                    });
            }
        }

        $awaiting = Student::with(['schoolClass', 'section'])->where('status', 'pending')->latest('id')->limit(12)->get()
            ->each(fn ($st) => $st->due_total = (float) ($all->firstWhere('id', $st->id)->due ?? 0));

        return [
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'name' => $c->name] + $stats($byClass->get($c->id, collect()))),
            'class' => $class,
            'sections' => $sections,
            'section' => $request->section_id ?? ($class && $class->sections->isEmpty() ? 'none' : null),
            'list' => $list,
            'awaiting' => $awaiting,
            'awaitingCount' => $all->where('status', 'pending')->count(),
            'total' => $stats($all),
        ];
    }

    public function store(Request $request, Student $student, FeeService $fees, NotificationService $notifier)
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
        $notifier->paymentReceived($payment);
        $msg = "Payment recorded. Receipt No: {$payment->receipt_no}";
        if ($payment->confirmedAdmission) {
            $student->refresh();
            $notifier->admissionConfirmed($student);
            $msg .= ". Admission confirmed — Student ID {$student->student_code}.";
        }

        return redirect()->route('payments.show', $payment)->with('success', $msg);
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
