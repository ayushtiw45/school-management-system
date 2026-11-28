<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentItem extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'payment_id', 'student_fee_id', 'amount', 'fine', 'discount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fine' => 'decimal:2', 'discount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function studentFee(): BelongsTo
    {
        return $this->belongsTo(StudentFee::class);
    }
}
