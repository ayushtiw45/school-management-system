<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders {--force : Send even if automatic reminders are switched off}';

    protected $description = 'Send fee-due reminders to parents of students with overdue fees (at most once a week per student).';

    public function handle(NotificationService $notifier): int
    {
        if (Setting::get('notify_fee_due_auto') !== '1' && ! $this->option('force')) {
            $this->info('Automatic reminders are off (Notification Settings).');

            return self::SUCCESS;
        }

        $upto = now()->subDays((int) Setting::get('reminder_days_after_due'));
        $students = Student::with(['schoolClass', 'section'])->where('status', 'active')
            ->whereHas('fees', fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto))
            ->get();

        $count = $notifier->feeDueReminders($students, $upto, null, null, skipIfRemindedWithinDays: 7);
        $this->info("Reminders queued for {$count} student(s).");

        return self::SUCCESS;
    }
}
