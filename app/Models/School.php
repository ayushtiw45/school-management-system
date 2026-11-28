<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class School extends Model
{
    protected $fillable = ['name', 'slug', 'phone', 'email', 'city', 'state'];

    protected function casts(): array
    {
        return [
            'trial_used' => 'boolean',
            'is_blocked' => 'boolean',
            'paid_until' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $s) {
            if (! $s->slug) {
                $base = Str::slug($s->name) ?: 'school';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                    $slug = $base.'-'.$i;
                }
                $s->slug = $slug;
            }
        });
    }

    /**
     * locked    = never paid (₹1 trial not started) → every function locked
     * active    = paid period running
     * expired   = trial / subscription ended → read-only
     * blocked   = blocked by the platform owner
     */
    public function accessState(): string
    {
        if ($this->is_blocked) {
            return 'blocked';
        }
        if (! $this->paid_until) {
            return 'locked';
        }

        return $this->paid_until->isFuture() ? 'active' : 'expired';
    }

    public function daysLeft(): ?int
    {
        return $this->paid_until && $this->paid_until->isFuture() ? (int) ceil(now()->diffInHours($this->paid_until) / 24) : null;
    }

    /** Extend access (never shortens). */
    public function extendUntil(\DateTimeInterface $until): void
    {
        if (! $this->paid_until || $this->paid_until->lt($until)) {
            $this->paid_until = $until;
        }
        $this->activated_at ??= now();
        $this->save();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    public function liveSubscription(): ?Subscription
    {
        return $this->subscriptions()->whereIn('status', ['authenticated', 'active', 'pending'])->first();
    }
}
