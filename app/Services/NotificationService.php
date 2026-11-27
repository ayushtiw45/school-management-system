<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentFee;
use Illuminate\Support\Collection;

class NotificationService
{
    /** Channels chosen in Settings (or an explicit one). */
    public function channels(?string $channel = null): array
    {
        $channel ??= Setting::get('notify_channel');

        return $channel === 'both' ? ['sms', 'whatsapp'] : [$channel === 'whatsapp' ? 'whatsapp' : 'sms'];
    }

    public function render(string $template, array $vars): string
    {
        $vars += ['school' => Setting::get('school_name')];

        return strtr($template, collect($vars)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    public function studentVars(Student $student): array
    {
        $student->loadMissing(['schoolClass', 'section']);

        return [
            'student' => $student->full_name,
            'class' => $student->class_label,
            'admission_no' => $student->admission_no,
            'father' => $student->father_name,
        ];
    }

    /**
     * Queue a message to a student's parent on each channel.
     *
     * @return int number of messages queued
     */
    public function toStudent(Student $student, string $type, string $message, ?string $channel = null, ?int $userId = null): int
    {
        $count = 0;
        foreach ($this->channels($channel) as $ch) {
            $to = MessageGateway::normalize($student->parent_phone, $ch);
            if (! $to) {
                continue;
            }
            $log = NotificationLog::create([
                'student_id' => $student->id,
                'channel' => $ch,
                'type' => $type,
                'recipient' => $to,
                'message' => $message,
                'status' => 'queued',
                'sent_by' => $userId,
            ]);
            SendNotification::dispatch($log->id)->afterCommit();
            $count++;
        }

        return $count;
    }

    public function paymentReceived(Payment $payment): void
    {
        if (Setting::get('notify_payment') !== '1') {
            return;
        }
        $student = $payment->student;
        $msg = $this->render(Setting::get('tpl_payment'), $this->studentVars($student) + [
            'amount' => number_format((float) $payment->amount, 2),
            'receipt' => $payment->receipt_no,
            'date' => $payment->payment_date->format('d-m-Y'),
            'balance' => number_format($student->totalDue(), 2),
        ]);
        $this->toStudent($student, 'payment', $msg, null, $payment->received_by);
    }

    public function admissionConfirmed(Student $student): void
    {
        if (Setting::get('notify_admission') !== '1') {
            return;
        }
        $this->toStudent($student, 'admission', $this->render(Setting::get('tpl_admission'), $this->studentVars($student)));
    }

    /** Overdue amount and oldest due date for a student, counting fees due on or before $upto. */
    public function overdue(Student $student, $upto): array
    {
        $fees = $student->fees()->outstanding()->whereDate('due_date', '<=', $upto)->get();

        return [round($fees->sum(fn (StudentFee $f) => $f->balance()), 2), $fees->min('due_date')];
    }

    /**
     * Send fee-due reminders.
     *
     * @param  Collection<int, Student>  $students
     * @param  int  $skipIfRemindedWithinDays  do not remind a student again within this many days (0 = always send)
     * @return int students reminded
     */
    public function feeDueReminders(Collection $students, $upto, ?string $channel = null, ?int $userId = null, int $skipIfRemindedWithinDays = 0): int
    {
        $tpl = Setting::get('tpl_fee_due');
        $reminded = 0;

        foreach ($students as $student) {
            if ($skipIfRemindedWithinDays > 0 && NotificationLog::where('student_id', $student->id)->where('type', 'fee_due')
                ->where('created_at', '>=', now()->subDays($skipIfRemindedWithinDays))->exists()) {
                continue;
            }

            [$amount, $oldest] = $this->overdue($student, $upto);
            if ($amount <= 0) {
                continue;
            }

            $msg = $this->render($tpl, $this->studentVars($student) + [
                'amount' => number_format($amount, 2),
                'due_date' => $oldest?->format('d-m-Y'),
            ]);
            if ($this->toStudent($student, 'fee_due', $msg, $channel, $userId)) {
                $reminded++;
            }
        }

        return $reminded;
    }

    /** WhatsApp click-to-chat link (free, opens WhatsApp on the staff member's phone / PC). */
    public static function whatsappLink(?string $phone, string $message): ?string
    {
        $to = MessageGateway::normalize($phone, 'whatsapp');

        return $to ? 'https://wa.me/'.$to.'?text='.rawurlencode($message) : null;
    }
}
