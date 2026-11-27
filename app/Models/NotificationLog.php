<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    public const TYPES = [
        'fee_due' => 'Fee Due Reminder',
        'payment' => 'Payment Confirmation',
        'admission' => 'Admission Confirmation',
        'announcement' => 'Announcement',
    ];

    protected $fillable = ['student_id', 'channel', 'type', 'recipient', 'message', 'status', 'response', 'sent_by'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
