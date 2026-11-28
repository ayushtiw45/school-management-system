<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryFollowUp extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'enquiry_id', 'note', 'next_follow_up', 'user_id'];

    protected function casts(): array
    {
        return ['next_follow_up' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
