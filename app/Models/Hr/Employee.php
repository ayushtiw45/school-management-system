<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A teacher or non-teaching staff member. */
class Employee extends Model
{
    use BelongsToSchool;

    public const TYPES = ['teaching' => 'Teaching', 'non_teaching' => 'Non-teaching'];

    public const EMPLOYMENT = ['permanent' => 'Permanent', 'probation' => 'Probation', 'contract' => 'Contract', 'part_time' => 'Part-time', 'guest' => 'Guest / visiting'];

    public const STATUSES = ['active' => 'Working', 'resigned' => 'Resigned', 'terminated' => 'Terminated', 'retired' => 'Retired'];

    public const DESIGNATIONS = ['Principal', 'Vice Principal', 'Headmistress', 'Coordinator', 'PGT', 'TGT', 'PRT', 'NTT / Pre-primary Teacher', 'Sports Teacher',
        'Music Teacher', 'Art Teacher', 'Computer Teacher', 'Librarian', 'Lab Assistant', 'Counsellor', 'Office Assistant', 'Clerk', 'Accountant', 'Receptionist',
        'Peon', 'Aaya / Helper', 'Guard', 'Sweeper', 'Gardener', 'Electrician'];

    public const EARNINGS = ['basic' => 'Basic', 'hra' => 'HRA', 'da' => 'DA', 'conveyance' => 'Conveyance', 'other_allowance' => 'Other allowance'];

    public const DEDUCTIONS = ['pf' => 'PF', 'esi' => 'ESI', 'tds' => 'TDS / tax', 'other_deduction' => 'Other deduction'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dob' => 'date', 'joining_date' => 'date', 'contract_end' => 'date', 'leaving_date' => 'date', 'experience_years' => 'decimal:1'];
    }

    public function isTeacher(): bool
    {
        return $this->type === 'teaching';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function gross(): float
    {
        return round(array_sum(array_map(fn ($k) => (float) $this->$k, array_keys(self::EARNINGS))), 2);
    }

    public function deductions(): float
    {
        return round(array_sum(array_map(fn ($k) => (float) $this->$k, array_keys(self::DEDUCTIONS))), 2);
    }

    /** Experience before joining + years in this school. */
    public function totalExperience(): float
    {
        $here = $this->joining_date ? $this->joining_date->diffInDays($this->leaving_date ?? now()) / 365.25 : 0;

        return round((float) $this->experience_years + $here, 1);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeTeachers($q)
    {
        return $q->where('type', 'teaching');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->latest('id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class)->latest('date');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class)->latest('from_date');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class)->latest('month');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class)->latest('given_on');
    }

    public function timetable(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function classTeacherOf(): HasMany
    {
        return $this->hasMany(\App\Models\Section::class, 'class_teacher_id');
    }
}
