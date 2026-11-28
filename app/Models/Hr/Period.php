<?php

namespace App\Models\Hr;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'start_time', 'end_time', 'sort_order', 'is_break'];

    protected function casts(): array
    {
        return ['is_break' => 'boolean'];
    }

    public function timeLabel(): string
    {
        return $this->start_time ? substr($this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5) : '';
    }

    public static function ordered()
    {
        return static::orderBy('sort_order')->orderBy('start_time');
    }
}
