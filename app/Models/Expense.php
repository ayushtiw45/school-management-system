<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 
        'expense_category_id', 'expense_date', 'amount', 'title', 'paid_to', 'salary_month',
        'payment_method', 'reference_no', 'bill_path', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function methodLabel(): string
    {
        return Payment::METHODS[$this->payment_method] ?? $this->payment_method;
    }
}
