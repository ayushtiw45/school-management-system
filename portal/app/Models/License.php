<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends Model
{
    protected $fillable = ['user_id', 'installation_id', 'school_name', 'plan', 'starts_on', 'expires_on', 'license_key', 'source', 'issued_by', 'notes'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'expires_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return ! $this->expires_on || $this->expires_on->endOfDay()->isFuture();
    }
}
