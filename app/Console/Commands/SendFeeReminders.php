<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\Setting;
use App\Models\Student;
use App\Services\NotificationService;
use App\Support\Tenant;
use Illuminate\Console\Command;

class SendFeeReminders extends Command
{
    protected $signature = 'fees:send-reminders {--force : Send even if automatic reminders are switched off}';

    protected $description = 'Send fee-due reminders to parents of students with overdue fees (at most once a week per student).';

    public function handle(NotificationService $notifier): int
    {
        $total = 0;
        // every school with an active plan, each with its own settings and students
        School::where('is_blocked', false)->where('paid_until', '>', now())->each(function (School $school) use ($notifier, &$total) {
            $total += Tenant::run($school, function () use ($notifier) {
                if (Setting::get('notify_fee_due_auto') !== '1' && ! $this->option('force')) {
                    return 0;
                }
                $upto = now()->subDays((int) Setting::get('reminder_days_after_due'));
                $students = Student::with(['schoolClass', 'section'])->where('status', 'active')
                    ->whereHas('fees', fn ($q) => $q->outstanding()->whereDate('due_date', '<=', $upto))
                    ->get();

                return $notifier->feeDueReminders($students, $upto, null, null, skipIfRemindedWithinDays: 7);
            });
        });
        $this->info("Reminders queued for {$total} student(s).");

        return self::SUCCESS;
    }
}
