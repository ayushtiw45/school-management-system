<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Teacher X teaches subject Y in class Z (optionally one section). */
class TeacherSubject extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'employee_id', 'subject_id', 'school_class_id', 'section_id', 'periods_per_week'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SchoolClass::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Section::class);
    }
}
