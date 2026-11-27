<?php

namespace App\Reports;

use App\Models\AcademicSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeHead;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** key => [title, description, filters, group] */
    public const REPORTS = [
        'daily' => ['Daily Fee Collection', 'All receipts of one day with mode-wise totals.', ['date'], 'Collection'],
        'monthly' => ['Monthly Fee Collection', 'Day-by-day collection for a month, split by payment mode.', ['month'], 'Collection'],
        'datewise' => ['Date-wise Payments', 'Every payment between two dates.', ['from', 'to', 'method', 'class'], 'Collection'],
        'classwise' => ['Class-wise Fee Collection', 'Fees assigned, collected and pending for each class.', ['session', 'from', 'to'], 'Collection'],
        'summary' => ['Total Fee Collection Summary', 'Session totals by fee category, payment mode and expenses.', ['session'], 'Collection'],
        'studentwise' => ['Student-wise Fee Report', 'Total fee, discount, paid and balance of each student.', ['session', 'class', 'section'], 'Dues'],
        'pending' => ['Pending Fees Report', 'Students with unpaid fees up to a date.', ['upto', 'class', 'section'], 'Dues'],
        'paid_unpaid' => ['Paid & Unpaid Fee Records', 'Each fee instalment with its status.', ['session', 'class', 'section', 'fee_head', 'fee_status', 'upto'], 'Dues'],
        'discount' => ['Discount Report', 'All discounts and concessions given.', ['session', 'class'], 'Dues'],
        'expenses' => ['Expense Register', 'All school expenses between two dates.', ['from', 'to', 'expense_category'], 'Expenses'],
        'expense_monthly' => ['Monthly Expense Report', 'Expenses by category for each month of the session, with income vs expense.', ['session'], 'Expenses'],
    ];

    public function build(string $type, Request $r): ReportData
    {
        $method = lcfirst(str_replace('_', '', ucwords($type, '_')));

        return $this->$method($r);
    }

    // ---------- filter helpers ----------

    private function session(Request $r): AcademicSession
    {
        return AcademicSession::find($r->session_id) ?? AcademicSession::current();
    }

    private function range(Request $r, ?AcademicSession $session = null): array
    {
        $from = $r->date('from') ?? ($session ? $session->start_date->copy() : now()->startOfMonth());
        $to = $r->date('to') ?? now();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    private function classLabel(Request $r): string
    {
        $parts = [];
        if ($r->class_id && $c = SchoolClass::find($r->class_id)) {
            $parts[] = $c->name;
        }
        if ($r->section_id && $s = \App\Models\Section::find($r->section_id)) {
            $parts[] = 'Section '.$s->name;
        }

        return $parts ? ' · '.implode(' ', $parts) : '';
    }

    private function filterStudents($q, Request $r, string $prefix = '')
    {
        return $q->when($r->class_id, fn ($q, $id) => $q->where($prefix.'school_class_id', $id))
            ->when($r->section_id, fn ($q, $id) => $q->where($prefix.'section_id', $id));
    }

    private function paymentRow(Payment $p): array
    {
        return [
            'date' => $p->payment_date,
            'receipt' => $p->receipt_no,
            'student' => $p->student->full_name,
            'adm' => $p->student->admission_no,
            'class' => $p->student->class_label,
            'mode' => $p->methodLabel(),
            'ref' => $p->reference_no,
            'by' => $p->receiver?->name,
            'amount' => (float) $p->amount,
        ];
    }

    private function modeSummary($payments): array
    {
        $out = [];
        foreach (Payment::METHODS as $k => $label) {
            $sum = $payments->where('method', $k)->sum('amount');
            if ($sum > 0) {
                $out[$label] = inr($sum).' ('.$payments->where('method', $k)->count().')';
            }
        }

        return $out;
    }

    // ---------- collection reports ----------

    private function daily(Request $r): ReportData
    {
        $date = $r->date('date') ?? now();
        $payments = Payment::with(['student.schoolClass', 'student.section', 'receiver'])
            ->whereDate('payment_date', $date)->orderBy('id')->get();
        $rows = $payments->map(fn ($p) => $this->paymentRow($p))->all();

        return new ReportData(
            'Daily Fee Collection',
            $date->format('l, d M Y'),
            [
                'receipt' => ['label' => 'Receipt No'], 'student' => ['label' => 'Student'], 'adm' => ['label' => 'Adm. No'],
                'class' => ['label' => 'Class'], 'mode' => ['label' => 'Mode'], 'ref' => ['label' => 'Reference'],
                'by' => ['label' => 'Received By'], 'amount' => ['label' => 'Amount', 'type' => 'money'],
            ],
            $rows,
            ['receipt' => 'Total ('.count($rows).' receipts)'] + ReportData::sum($rows, ['amount']),
            ['Total Collection' => inr($payments->sum('amount'))] + $this->modeSummary($payments),
        );
    }

    private function monthly(Request $r): ReportData
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $r->month) ? Carbon::createFromFormat('Y-m-d', $r->month.'-01') : now()->startOfMonth();
        $payments = Payment::whereBetween('payment_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->get();

        $cols = ['date' => ['label' => 'Date', 'type' => 'date'], 'count' => ['label' => 'Receipts', 'type' => 'int']];
        foreach (Payment::METHODS as $k => $l) {
            $cols[$k] = ['label' => $l, 'type' => 'money'];
        }
        $cols['total'] = ['label' => 'Total', 'type' => 'money'];

        $rows = [];
        foreach ($payments->groupBy(fn ($p) => $p->payment_date->toDateString())->sortKeys() as $day => $list) {
            $row = ['date' => Carbon::parse($day), 'count' => $list->count()];
            foreach (array_keys(Payment::METHODS) as $k) {
                $row[$k] = (float) $list->where('method', $k)->sum('amount');
            }
            $row['total'] = (float) $list->sum('amount');
            $rows[] = $row;
        }

        return new ReportData(
            'Monthly Fee Collection',
            $month->format('F Y'),
            $cols,
            $rows,
            ['date' => 'Total'] + ReportData::sum($rows, array_merge(['count'], array_keys(Payment::METHODS), ['total'])),
            ['Total Collection' => inr($payments->sum('amount')), 'Receipts' => (string) $payments->count(), 'Days with collection' => (string) count($rows)],
        );
    }

    private function datewise(Request $r): ReportData
    {
        [$from, $to] = $this->range($r);
        $payments = Payment::with(['student.schoolClass', 'student.section', 'receiver'])
            ->whereBetween('payment_date', [$from, $to])
            ->when($r->method, fn ($q, $m) => $q->where('method', $m))
            ->whereHas('student', fn ($q) => $this->filterStudents($q, $r))
            ->orderBy('payment_date')->orderBy('id')->get();
        $rows = $payments->map(fn ($p) => $this->paymentRow($p))->all();

        return new ReportData(
            'Date-wise Payment Report',
            $from->format('d M Y').' to '.$to->format('d M Y').($r->method ? ' · '.(Payment::METHODS[$r->method] ?? '') : '').$this->classLabel($r),
            [
                'date' => ['label' => 'Date', 'type' => 'date'], 'receipt' => ['label' => 'Receipt No'], 'student' => ['label' => 'Student'],
                'class' => ['label' => 'Class'], 'mode' => ['label' => 'Mode'], 'ref' => ['label' => 'Reference'], 'amount' => ['label' => 'Amount', 'type' => 'money'],
            ],
            $rows,
            ['date' => 'Total ('.count($rows).')'] + ReportData::sum($rows, ['amount']),
            ['Total' => inr($payments->sum('amount'))] + $this->modeSummary($payments),
        );
    }

    private function classwise(Request $r): ReportData
    {
        $session = $this->session($r);
        [$from, $to] = $this->range($r, $session);
        $today = now()->toDateString();

        $fees = StudentFee::query()
            ->join('students', 'students.id', '=', 'student_fees.student_id')
            ->where('student_fees.academic_session_id', $session->id)
            ->groupBy('students.school_class_id')
            ->selectRaw('students.school_class_id as class_id,
                SUM(student_fees.amount) as assigned, SUM(student_fees.discount) as discount, SUM(student_fees.fine) as fine,
                SUM(CASE WHEN student_fees.due_date <= ? THEN student_fees.amount - student_fees.discount + student_fees.fine - student_fees.paid ELSE 0 END) as overdue,
                SUM(student_fees.amount - student_fees.discount + student_fees.fine - student_fees.paid) as balance', [$today])
            ->get()->keyBy('class_id');

        $collected = Payment::join('students', 'students.id', '=', 'payments.student_id')
            ->whereBetween('payments.payment_date', [$from, $to])
            ->groupBy('students.school_class_id')
            ->selectRaw('students.school_class_id as class_id, SUM(payments.amount) as total, COUNT(*) as cnt')
            ->get()->keyBy('class_id');

        $rows = [];
        foreach (SchoolClass::ordered()->withCount(['students' => fn ($q) => $q->where('status', 'active')])->get() as $c) {
            $f = $fees->get($c->id);
            $rows[] = [
                'class' => $c->name,
                'students' => $c->students_count,
                'assigned' => (float) ($f->assigned ?? 0),
                'discount' => (float) ($f->discount ?? 0),
                'net' => (float) (($f->assigned ?? 0) - ($f->discount ?? 0) + ($f->fine ?? 0)),
                'collected' => (float) ($collected->get($c->id)->total ?? 0),
                'overdue' => (float) ($f->overdue ?? 0),
                'balance' => (float) ($f->balance ?? 0),
            ];
        }

        return new ReportData(
            'Class-wise Fee Collection',
            'Session '.$session->name.' · collected '.$from->format('d M Y').' to '.$to->format('d M Y'),
            [
                'class' => ['label' => 'Class'], 'students' => ['label' => 'Active Students', 'type' => 'int'],
                'assigned' => ['label' => 'Fee Assigned', 'type' => 'money'], 'discount' => ['label' => 'Discount', 'type' => 'money'],
                'net' => ['label' => 'Net Payable', 'type' => 'money'], 'collected' => ['label' => 'Collected', 'type' => 'money'],
                'overdue' => ['label' => 'Pending (due)', 'type' => 'money'], 'balance' => ['label' => 'Balance (full year)', 'type' => 'money'],
            ],
            $rows,
            ['class' => 'Total'] + ReportData::sum($rows, ['students', 'assigned', 'discount', 'net', 'collected', 'overdue', 'balance']),
        );
    }

    private function summary(Request $r): ReportData
    {
        $session = $this->session($r);
        $today = now()->toDateString();

        $byHead = StudentFee::where('academic_session_id', $session->id)
            ->groupBy('fee_head_id')
            ->selectRaw('fee_head_id, COUNT(*) as dues, SUM(amount) as assigned, SUM(discount) as discount, SUM(fine) as fine, SUM(paid) as paid,
                SUM(CASE WHEN due_date <= ? THEN amount - discount + fine - paid ELSE 0 END) as overdue,
                SUM(amount - discount + fine - paid) as balance', [$today])
            ->get();
        $heads = FeeHead::pluck('name', 'id');

        $rows = $byHead->map(fn ($h) => [
            'head' => $heads[$h->fee_head_id] ?? 'Other / Custom',
            'dues' => (int) $h->dues,
            'assigned' => (float) $h->assigned,
            'discount' => (float) $h->discount,
            'fine' => (float) $h->fine,
            'paid' => (float) $h->paid,
            'overdue' => (float) $h->overdue,
            'balance' => (float) $h->balance,
        ])->sortBy('head')->values()->all();

        $payments = Payment::whereBetween('payment_date', [$session->start_date, $session->end_date])->get();
        $expenses = (float) Expense::whereBetween('expense_date', [$session->start_date, $session->end_date])->sum('amount');
        $collected = (float) $payments->sum('amount');

        return new ReportData(
            'Total Fee Collection Summary',
            'Session '.$session->name.' ('.$session->start_date->format('d M Y').' to '.$session->end_date->format('d M Y').')',
            [
                'head' => ['label' => 'Fee Category'], 'dues' => ['label' => 'Instalments', 'type' => 'int'],
                'assigned' => ['label' => 'Assigned', 'type' => 'money'], 'discount' => ['label' => 'Discount', 'type' => 'money'],
                'fine' => ['label' => 'Fine', 'type' => 'money'], 'paid' => ['label' => 'Collected', 'type' => 'money'],
                'overdue' => ['label' => 'Pending (due)', 'type' => 'money'], 'balance' => ['label' => 'Balance (full year)', 'type' => 'money'],
            ],
            $rows,
            ['head' => 'Total'] + ReportData::sum($rows, ['dues', 'assigned', 'discount', 'fine', 'paid', 'overdue', 'balance']),
            ['Fees Collected' => inr($collected)] + $this->modeSummary($payments) + [
                'Expenses' => inr($expenses),
                'Net (Collected − Expenses)' => inr($collected - $expenses),
            ],
        );
    }

    // ---------- dues reports ----------

    private function studentwise(Request $r): ReportData
    {
        $session = $this->session($r);
        $scope = fn ($q) => $q->where('academic_session_id', $session->id);
        $today = now()->toDateString();

        $students = $this->filterStudents(Student::query(), $r)
            ->with(['schoolClass', 'section'])
            ->whereIn('status', ['active', 'inactive', 'left'])
            ->whereHas('fees', $scope)
            ->withSum(['fees as t_amount' => $scope], 'amount')
            ->withSum(['fees as t_discount' => $scope], 'discount')
            ->withSum(['fees as t_fine' => $scope], 'fine')
            ->withSum(['fees as t_paid' => $scope], 'paid')
            ->withSum(['fees as t_overdue' => fn ($q) => $scope($q)->where('status', '!=', 'paid')->whereDate('due_date', '<=', $today)], DB::raw(StudentFee::BALANCE_SQL))
            ->orderBy('school_class_id')->orderBy('section_id')->orderBy('first_name')
            ->get();

        $rows = $students->map(fn ($s) => [
            'adm' => $s->admission_no,
            'student' => $s->full_name.($s->status !== 'active' ? ' ('.$s->status.')' : ''),
            'class' => $s->class_label,
            'total' => (float) $s->t_amount,
            'discount' => (float) $s->t_discount,
            'fine' => (float) $s->t_fine,
            'paid' => (float) $s->t_paid,
            'balance' => round((float) $s->t_amount - (float) $s->t_discount + (float) $s->t_fine - (float) $s->t_paid, 2),
            'overdue' => (float) $s->t_overdue,
        ])->all();

        return new ReportData(
            'Student-wise Fee Report',
            'Session '.$session->name.$this->classLabel($r),
            [
                'adm' => ['label' => 'Adm. No'], 'student' => ['label' => 'Student'], 'class' => ['label' => 'Class'],
                'total' => ['label' => 'Total Fee', 'type' => 'money'], 'discount' => ['label' => 'Discount', 'type' => 'money'],
                'fine' => ['label' => 'Fine', 'type' => 'money'], 'paid' => ['label' => 'Paid', 'type' => 'money'],
                'balance' => ['label' => 'Balance', 'type' => 'money'], 'overdue' => ['label' => 'Overdue', 'type' => 'money'],
            ],
            $rows,
            ['adm' => 'Total ('.count($rows).' students)'] + ReportData::sum($rows, ['total', 'discount', 'fine', 'paid', 'balance', 'overdue']),
        );
    }

    private function pending(Request $r): ReportData
    {
        $upto = $r->date('upto') ?? now();
        $scope = fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto);

        $students = $this->filterStudents(Student::query(), $r)
            ->with(['schoolClass', 'section'])
            ->where('status', 'active')
            ->whereHas('fees', $scope)
            ->withSum(['fees as due_amount' => $scope], DB::raw(StudentFee::BALANCE_SQL))
            ->withCount(['fees as due_count' => $scope])
            ->withMin(['fees as oldest' => $scope], 'due_date')
            ->orderBy('school_class_id')->orderBy('section_id')->orderBy('first_name')
            ->get();

        $rows = $students->map(fn ($s) => [
            'adm' => $s->admission_no,
            'student' => $s->full_name,
            'class' => $s->class_label,
            'father' => $s->father_name,
            'phone' => $s->parent_phone,
            'count' => (int) $s->due_count,
            'oldest' => $s->oldest ? Carbon::parse($s->oldest) : null,
            'amount' => (float) $s->due_amount,
        ])->all();

        return new ReportData(
            'Pending Fees Report',
            'Fees due till '.$upto->format('d M Y').$this->classLabel($r),
            [
                'adm' => ['label' => 'Adm. No'], 'student' => ['label' => 'Student'], 'class' => ['label' => 'Class'],
                'father' => ['label' => 'Father'], 'phone' => ['label' => 'Mobile'], 'count' => ['label' => 'Dues', 'type' => 'int'],
                'oldest' => ['label' => 'Oldest Due', 'type' => 'date'], 'amount' => ['label' => 'Pending', 'type' => 'money'],
            ],
            $rows,
            ['adm' => 'Total ('.count($rows).' students)'] + ReportData::sum($rows, ['count', 'amount']),
        );
    }

    private function paidUnpaid(Request $r): ReportData
    {
        $session = $this->session($r);
        $status = $r->input('fee_status', 'all');

        $fees = StudentFee::with(['student.schoolClass', 'student.section'])
            ->where('academic_session_id', $session->id)
            ->whereHas('student', fn ($q) => $this->filterStudents($q, $r)->where('status', '!=', 'pending'))
            ->when($r->fee_head_id, fn ($q, $id) => $q->where('fee_head_id', $id))
            ->when($r->date('upto'), fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->when($status === 'outstanding', fn ($q) => $q->outstanding())
            ->when(in_array($status, ['paid', 'partial', 'unpaid'], true), fn ($q) => $q->where('status', $status))
            ->orderBy('due_date')->orderBy('student_id')
            ->limit(5000)->get();

        $rows = $fees->map(fn ($f) => [
            'student' => $f->student->full_name,
            'adm' => $f->student->admission_no,
            'class' => $f->student->class_label,
            'fee' => $f->title,
            'due' => $f->due_date,
            'net' => $f->netAmount(),
            'paid' => (float) $f->paid,
            'balance' => $f->balance(),
            'status' => ucfirst($f->status),
        ])->all();

        $statusLabel = ['all' => 'All', 'paid' => 'Paid', 'partial' => 'Partially paid', 'unpaid' => 'Unpaid', 'outstanding' => 'Unpaid + partial'][$status] ?? 'All';

        return new ReportData(
            'Paid & Unpaid Fee Records',
            'Session '.$session->name.' · '.$statusLabel.$this->classLabel($r).($r->upto ? ' · due till '.$r->date('upto')->format('d M Y') : ''),
            [
                'student' => ['label' => 'Student'], 'adm' => ['label' => 'Adm. No'], 'class' => ['label' => 'Class'], 'fee' => ['label' => 'Fee'],
                'due' => ['label' => 'Due Date', 'type' => 'date'], 'net' => ['label' => 'Net Amount', 'type' => 'money'],
                'paid' => ['label' => 'Paid', 'type' => 'money'], 'balance' => ['label' => 'Balance', 'type' => 'money'], 'status' => ['label' => 'Status'],
            ],
            $rows,
            ['student' => 'Total ('.count($rows).' records)'] + ReportData::sum($rows, ['net', 'paid', 'balance']),
            [
                'Paid' => (string) $fees->where('status', 'paid')->count(),
                'Partially paid' => (string) $fees->where('status', 'partial')->count(),
                'Unpaid' => (string) $fees->where('status', 'unpaid')->count(),
            ],
        );
    }

    private function discount(Request $r): ReportData
    {
        $session = $this->session($r);
        $fees = StudentFee::with(['student.schoolClass', 'student.section'])
            ->where('academic_session_id', $session->id)
            ->where('discount', '>', 0)
            ->whereHas('student', fn ($q) => $this->filterStudents($q, $r))
            ->orderBy('student_id')->orderBy('due_date')->get();

        $rows = $fees->map(fn ($f) => [
            'student' => $f->student->full_name,
            'adm' => $f->student->admission_no,
            'class' => $f->student->class_label,
            'concession' => (float) $f->student->concession_percent ? $f->student->concession_percent.'%' : '',
            'fee' => $f->title,
            'amount' => (float) $f->amount,
            'discount' => (float) $f->discount,
        ])->all();

        return new ReportData(
            'Discount Report',
            'Session '.$session->name.$this->classLabel($r),
            [
                'student' => ['label' => 'Student'], 'adm' => ['label' => 'Adm. No'], 'class' => ['label' => 'Class'],
                'concession' => ['label' => 'Concession %'], 'fee' => ['label' => 'Fee'],
                'amount' => ['label' => 'Fee Amount', 'type' => 'money'], 'discount' => ['label' => 'Discount', 'type' => 'money'],
            ],
            $rows,
            ['student' => 'Total'] + ReportData::sum($rows, ['amount', 'discount']),
            ['Total Discount' => inr($fees->sum('discount')), 'Students' => (string) $fees->pluck('student_id')->unique()->count()],
        );
    }

    // ---------- expense reports ----------

    private function expenses(Request $r): ReportData
    {
        [$from, $to] = $this->range($r);
        $list = Expense::with('category')
            ->whereBetween('expense_date', [$from, $to])
            ->when($r->expense_category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->orderBy('expense_date')->orderBy('id')->get();

        $rows = $list->map(fn ($e) => [
            'date' => $e->expense_date,
            'category' => $e->category->name,
            'title' => $e->title.($e->salary_month ? ' ('.Carbon::parse($e->salary_month.'-01')->format('M Y').')' : ''),
            'paid_to' => $e->paid_to,
            'mode' => $e->methodLabel(),
            'ref' => $e->reference_no,
            'amount' => (float) $e->amount,
        ])->all();

        $byCat = [];
        foreach ($list->groupBy(fn ($e) => $e->category->name)->sortKeys() as $name => $items) {
            $byCat[$name] = inr($items->sum('amount'));
        }

        return new ReportData(
            'Expense Register',
            $from->format('d M Y').' to '.$to->format('d M Y'),
            [
                'date' => ['label' => 'Date', 'type' => 'date'], 'category' => ['label' => 'Category'], 'title' => ['label' => 'Details'],
                'paid_to' => ['label' => 'Paid To'], 'mode' => ['label' => 'Mode'], 'ref' => ['label' => 'Reference'],
                'amount' => ['label' => 'Amount', 'type' => 'money'],
            ],
            $rows,
            ['date' => 'Total ('.count($rows).')'] + ReportData::sum($rows, ['amount']),
            ['Total Expenses' => inr($list->sum('amount'))] + $byCat,
        );
    }

    private function expenseMonthly(Request $r): ReportData
    {
        $session = $this->session($r);
        $months = $session->months();
        $expenses = Expense::whereBetween('expense_date', [$session->start_date, $session->end_date])->get();
        $payments = Payment::whereBetween('payment_date', [$session->start_date, $session->end_date])->get();

        $cols = ['category' => ['label' => 'Category']];
        foreach ($months as $m) {
            $cols[$m->format('Y-m')] = ['label' => $m->format('M y'), 'type' => 'money'];
        }
        $cols['total'] = ['label' => 'Total', 'type' => 'money'];

        $rows = [];
        foreach (ExpenseCategory::orderBy('name')->get() as $cat) {
            $items = $expenses->where('expense_category_id', $cat->id);
            if ($items->isEmpty()) {
                continue;
            }
            $row = ['category' => $cat->name];
            foreach ($months as $m) {
                $row[$m->format('Y-m')] = (float) $items->filter(fn ($e) => $e->expense_date->format('Y-m') === $m->format('Y-m'))->sum('amount');
            }
            $row['total'] = (float) $items->sum('amount');
            $rows[] = $row;
        }

        $monthKeys = array_map(fn ($m) => $m->format('Y-m'), $months);
        $totals = ['category' => 'Total Expenses'] + ReportData::sum($rows, array_merge($monthKeys, ['total']));

        // Extra lines: fee income and net per month
        $income = ['category' => 'Fee Collection'];
        $net = ['category' => 'Net (Income − Expense)'];
        foreach ($monthKeys as $k) {
            $income[$k] = (float) $payments->filter(fn ($p) => $p->payment_date->format('Y-m') === $k)->sum('amount');
            $net[$k] = round($income[$k] - $totals[$k], 2);
        }
        $income['total'] = (float) $payments->sum('amount');
        $net['total'] = round($income['total'] - $totals['total'], 2);
        $rows[] = $totals;
        $rows[] = $income;

        return new ReportData(
            'Monthly Expense Report',
            'Session '.$session->name,
            $cols,
            $rows,
            $net,
            ['Total Expenses' => inr($totals['total']), 'Fee Collection' => inr($income['total']), 'Net' => inr($net['total'])],
        );
    }
}
