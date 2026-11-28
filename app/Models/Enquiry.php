<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    use BelongsToSchool;

    public const SOURCES = ['walk_in' => 'Walk-in', 'phone' => 'Phone call', 'website' => 'Website / online', 'reference' => 'Reference', 'social' => 'Social media', 'banner' => 'Banner / pamphlet', 'other' => 'Other'];

    public const STATUSES = ['new' => 'New', 'follow_up' => 'Follow-up', 'converted' => 'Admission done', 'not_interested' => 'Not interested'];

    protected $fillable = ['school_id', 'child_name', 'parent_name', 'phone', 'alt_phone', 'email', 'school_class_id', 'dob', 'previous_school',
        'source', 'notes', 'status', 'next_follow_up', 'student_id', 'created_by'];

    protected function casts(): array
    {
        return ['dob' => 'date', 'next_follow_up' => 'date'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(EnquiryFollowUp::class)->latest('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['new', 'follow_up'], true);
    }

    public function followUpDue(): bool
    {
        return $this->isOpen() && $this->next_follow_up && ! $this->next_follow_up->isFuture();
    }
}
