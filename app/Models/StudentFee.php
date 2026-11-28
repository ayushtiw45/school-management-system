<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFee extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 
        'student_id', 'academic_session_id', 'fee_head_id', 'title', 'due_date',
        'amount', 'discount', 'fine', 'paid', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'fine' => 'decimal:2',
            'paid' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function paymentItems(): HasMany
    {
        return $this->hasMany(PaymentItem::class);
    }

    public function netAmount(): float
    {
        return round((float) $this->amount - (float) $this->discount + (float) $this->fine, 2);
    }

    public function balance(): float
    {
        return max(0, round($this->netAmount() - (float) $this->paid, 2));
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function refreshStatus(): void
    {
        $this->status = $this->balance() <= 0 ? 'paid' : ((float) $this->paid > 0 ? 'partial' : 'unpaid');
    }

    public function scopeOutstanding(Builder $q): Builder
    {
        return $q->where('status', '!=', 'paid');
    }

    /** Outstanding balance expression for SQL aggregates. */
    public const BALANCE_SQL = '(amount - discount + fine - paid)';
}
