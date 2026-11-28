<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToSchool;

    public const METHODS = [
        'cash' => 'Cash',
        'upi' => 'UPI',
        'bank' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'online' => 'Online Gateway',
    ];

    /** set by FeeService::collect when this payment confirmed a pending admission */
    public bool $confirmedAdmission = false;

    protected $fillable = ['school_id', 
        'receipt_no', 'student_id', 'payment_date', 'amount', 'method', 'reference_no',
        'bank_name', 'cheque_date', 'remarks', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'cheque_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
