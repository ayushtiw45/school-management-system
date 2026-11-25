<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentFee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeService
{
    /**
     * Create the student's fee dues for a session from the class fee structure.
     * Instalments that end before the student joined are skipped. Existing dues are never duplicated.
     *
     * @return int number of dues created
     */
    public function generateForStudent(Student $student, ?AcademicSession $session = null, bool $includeOneTime = true): int
    {
        $session ??= $student->academicSession;
        $joinedMonth = $student->admission_date->copy()->startOfMonth();
        $dueDay = max(1, min(28, (int) Setting::get('fee_due_day')));
        $months = $session->months();
        $created = 0;

        $structures = FeeStructure::with('feeHead')
            ->where('academic_session_id', $session->id)
            ->where('school_class_id', $student->school_class_id)
            ->get();

        foreach ($structures as $structure) {
            $head = $structure->feeHead;

            if ($head->frequency === 'one_time') {
                if (! $includeOneTime) {
                    continue;
                }
                $due = $student->admission_date->max($session->start_date);
                $created += $this->addDue($student, $session, $head, $structure->amount, $head->name, $due);

                continue;
            }

            $count = FeeHead::INSTALMENTS[$head->frequency] ?? 12;
            $span = intdiv(count($months), $count) ?: 1;

            foreach (array_chunk($months, $span) as $i => $chunk) {
                if ($i >= $count) {
                    break;
                }
                $first = $chunk[0];
                $last = end($chunk);
                if ($last->lt($joinedMonth)) {
                    continue; // student joined after this period
                }

                $title = match ($head->frequency) {
                    'monthly' => $head->name.' - '.$first->format('M Y'),
                    'yearly' => $head->name.' - '.$session->name,
                    default => $head->name.' - '.$first->format('M').'-'.$last->format('M Y'),
                };

                $due = $first->copy()->day($dueDay)->max($student->admission_date);
                $created += $this->addDue($student, $session, $head, $structure->amount, $title, $due);
            }
        }

        return $created;
    }

    private function addDue(Student $student, AcademicSession $session, FeeHead $head, $amount, string $title, Carbon $due): int
    {
        $exists = StudentFee::where('student_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('fee_head_id', $head->id)
            ->where('title', $title)
            ->exists();

        if ($exists) {
            return 0;
        }

        $discount = $head->concession_applicable
            ? round((float) $amount * (float) $student->concession_percent / 100, 2)
            : 0;

        StudentFee::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'fee_head_id' => $head->id,
            'title' => $title,
            'due_date' => $due,
            'amount' => $amount,
            'discount' => $discount,
            'status' => $discount >= (float) $amount ? 'paid' : 'unpaid',
        ]);

        return 1;
    }

    /** Fine suggested when collecting an overdue fee that has no fine yet. */
    public function suggestedFine(StudentFee $fee): float
    {
        return $fee->isOverdue() && (float) $fee->fine == 0 ? (float) Setting::get('late_fine_amount') : 0;
    }

    /**
     * Record a payment against one or more dues of a student.
     *
     * @param  array<int, array{amount: float|string|null, fine?: float|string|null, discount?: float|string|null}>  $lines  keyed by student_fee id
     */
    public function collect(Student $student, array $lines, array $details, ?int $userId): Payment
    {
        return DB::transaction(function () use ($student, $lines, $details, $userId) {
            $fees = StudentFee::where('student_id', $student->id)
                ->whereIn('id', array_keys($lines))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $items = [];
            $total = 0;

            foreach ($lines as $feeId => $line) {
                $fee = $fees->get($feeId);
                $amount = round((float) ($line['amount'] ?? 0), 2);
                $fine = round((float) ($line['fine'] ?? 0), 2);
                $discount = round((float) ($line['discount'] ?? 0), 2);

                if (! $fee || ($amount <= 0 && $fine <= 0 && $discount <= 0)) {
                    continue;
                }
                if ($amount < 0 || $fine < 0 || $discount < 0) {
                    throw ValidationException::withMessages(['lines' => 'Amounts cannot be negative.']);
                }

                $fee->fine = (float) $fee->fine + $fine;
                $fee->discount = (float) $fee->discount + $discount;

                if ($amount > $fee->balance() + 0.001) {
                    throw ValidationException::withMessages([
                        'lines' => "Amount for \"{$fee->title}\" is more than its balance (₹".number_format($fee->balance(), 2).').',
                    ]);
                }

                $fee->paid = (float) $fee->paid + $amount;
                $fee->refreshStatus();
                $fee->save();

                $items[] = ['student_fee_id' => $fee->id, 'amount' => $amount, 'fine' => $fine, 'discount' => $discount];
                $total += $amount;
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['lines' => 'Enter an amount for at least one fee.']);
            }

            $payment = Payment::create([
                'receipt_no' => NumberGenerator::receiptNo(),
                'student_id' => $student->id,
                'payment_date' => $details['payment_date'] ?? now()->toDateString(),
                'amount' => round($total, 2),
                'method' => $details['method'],
                'reference_no' => $details['reference_no'] ?? null,
                'bank_name' => $details['bank_name'] ?? null,
                'cheque_date' => $details['cheque_date'] ?? null,
                'remarks' => $details['remarks'] ?? null,
                'received_by' => $userId,
            ]);
            $payment->items()->createMany($items);

            return $payment;
        });
    }

    /** Reverse a payment (admin only): dues go back to their earlier balance. */
    public function cancel(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            foreach ($payment->items()->with('studentFee')->get() as $item) {
                $fee = $item->studentFee;
                $fee->paid = max(0, (float) $fee->paid - (float) $item->amount);
                $fee->fine = max(0, (float) $fee->fine - (float) $item->fine);
                $fee->discount = max(0, (float) $fee->discount - (float) $item->discount);
                $fee->refreshStatus();
                $fee->save();
            }
            $payment->delete();
        });
    }
}
