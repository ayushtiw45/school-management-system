<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'is_salary'];

    protected function casts(): array
    {
        return ['is_salary' => 'boolean'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
