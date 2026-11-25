<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeHead extends Model
{
    public const FREQUENCIES = [
        'one_time' => 'One Time (at admission)',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'half_yearly' => 'Half-Yearly',
        'yearly' => 'Yearly / Annual',
    ];

    /** Number of instalments in one session for each frequency. */
    public const INSTALMENTS = [
        'monthly' => 12,
        'quarterly' => 4,
        'half_yearly' => 2,
        'yearly' => 1,
    ];

    protected $fillable = ['name', 'frequency', 'concession_applicable', 'description'];

    protected function casts(): array
    {
        return ['concession_applicable' => 'boolean'];
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? $this->frequency;
    }

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
