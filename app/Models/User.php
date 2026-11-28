<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Admin',
        'principal' => 'Principal',
        'receptionist' => 'Receptionist',
        'accountant' => 'Accounts',
        'transport' => 'Transport Manager',
    ];

    /** What each role is for (shown on the Users page). */
    public const ROLE_HELP = [
        'admin' => 'Everything: setup, users, settings, plan, fees and records.',
        'principal' => 'Sees everything incl. fees and reports; manages admissions, students, classes. Cannot collect fees or change accounts.',
        'receptionist' => 'Enquiries & follow-ups, new admissions, student records, own daily report. No fees or settings.',
        'accountant' => 'Fee collection, receipts, fee structure, pending fees, expenses and reports. Also transport costs and salaries.',
        'transport' => 'Transport only: vehicles, routes, drivers & conductors, trips, attendance, fuel, maintenance, students on buses, transport reports.',
    ];

    protected $fillable = ['school_id', 'name', 'email', 'phone', 'role', 'is_active', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Can collect fees and change anything in accounts. */
    public function canManageFees(): bool
    {
        return $this->hasRole('admin', 'accountant');
    }

    /** Can see fee, payment, expense and report screens (principal read-only). */
    public function canSeeAccounts(): bool
    {
        return $this->hasRole('admin', 'accountant', 'principal');
    }

    /** Can take enquiries and admissions. */
    public function canSeeTransport(): bool
    {
        return $this->hasRole('admin', 'transport', 'accountant', 'principal');
    }

    public function canManageTransport(): bool
    {
        return $this->hasRole('admin', 'transport', 'accountant');
    }

    public function canAdmit(): bool
    {
        return $this->hasRole('admin', 'receptionist', 'principal');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
