<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use BelongsToSchool;

    public const STATUSES = [
        'pending' => 'Pending',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'left' => 'Left / Transferred',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'dob' => 'date',
            'leaving_date' => 'date',
            'confirmed_at' => 'datetime',
            'concession_percent' => 'decimal:2',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getParentPhoneAttribute(): ?string
    {
        return $this->father_phone ?: ($this->mother_phone ?: $this->guardian_phone);
    }

    public function getClassLabelAttribute(): string
    {
        return trim(($this->schoolClass?->name ?? '').' '.($this->section ? '- '.$this->section->name : ''));
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(StudentFee::class)->orderBy('due_date')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('payment_date')->latest('id');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (! $term) {
            return $q;
        }

        return $q->where(function ($q) use ($term) {
            $q->where('admission_no', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('father_name', 'like', "%{$term}%")
                ->orWhere('father_phone', 'like', "%{$term}%")
                ->orWhere('mother_phone', 'like', "%{$term}%");
        });
    }

    public function totalDue(): float
    {
        return (float) $this->fees()->get()->sum(fn (StudentFee $f) => $f->balance());
    }
}
