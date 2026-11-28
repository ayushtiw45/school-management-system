<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'sort_order'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('name');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    public static function ordered()
    {
        return static::orderBy('sort_order')->orderBy('id');
    }
}
