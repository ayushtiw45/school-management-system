<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\MessageGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $logId) {}

    public function handle(MessageGateway $gateway): void
    {
        $log = NotificationLog::find($this->logId);
        if (! $log || ! in_array($log->status, ['queued', 'failed'], true)) {
            return;
        }

        $log->update($gateway->send($log->channel, $log->recipient, $log->message));
    }
}
