<?php

use Illuminate\Support\Facades\Schedule;

/*
| Add ONE cron entry on the server (cPanel → Cron Jobs), every minute:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/

// Automatic fee-due reminders (only if switched on in Notification Settings)
Schedule::command('fees:send-reminders')->dailyAt('10:00')->withoutOverlapping();

// Process queued SMS / WhatsApp messages (works on shared hosting without a daemon)
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')->everyMinute()->withoutOverlapping();
