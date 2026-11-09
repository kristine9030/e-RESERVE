<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id', 'role', 'member_type'];   // role and member type are never mass-assigned

    protected $hidden = ['password', 'remember_token', 'verify_token_hash'];

    protected function casts(): array
    {
        return [
            'password'          => 'hashed',
            'must_change_password' => 'boolean',
            'email_verified_at' => 'datetime',
            'verify_expires_at' => 'datetime',
            'verify_sent_at'    => 'datetime',
            'last_login_at'     => 'datetime',
        ];
    }

    /** Branded reset email (instead of Laravel's plain default), sent through the queue. */
    public function sendPasswordResetNotification($token): void
    {
        \Illuminate\Support\Facades\Mail::to($this->email, $this->name)->queue(new \App\Mail\ResetPasswordMail(
            $this->name,
            route('password.reset', ['token' => $token, 'email' => $this->email]),
            (int) config('auth.passwords.users.expire', 60),
        ));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Gate staff: may scan tickets, nothing else. */
    public function isScanner(): bool
    {
        return $this->role === 'scanner';
    }

    /** Faculty, instructor, staff or administration: no documents, a confirmed email is enough. */
    public function isEmployee(): bool
    {
        return $this->role === 'student' && ($this->member_type ?? 'student') !== 'student';
    }

    /** Admin or scanner — anyone who may open the gate check-in. */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isScanner();
    }

    /** Where this person lands after signing in. */
    public function homeRoute(): string
    {
        return match (true) {
            $this->isAdmin()   => route('admin.dashboard'),
            $this->isScanner() => route('admin.checkin'),
            default            => route('dashboard'),
        };
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->isAdmin() || $this->email_verified_at !== null;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /** The shape every page expects from current_user(). */
    public function toSessionArray(): array
    {
        return [
            'id'         => (int) $this->id,
            'role'       => $this->role,
            'name'       => $this->name,
            'email'      => $this->email,
            'member_type' => $this->member_type ?? 'student',
            'is_employee' => $this->isEmployee(),
            'student_no' => $this->student_no ?? ('STAFF-' . strtoupper(substr(md5($this->email), 0, 5))),
            'program'    => $this->program ?? 'Supreme Student Council',
            'year'       => $this->year_level ?? 'Staff',
            'college'    => $this->college_code ?: 'ARASOF-Nasugbu Campus',
            'contact'    => $this->mobile ?? '',
            'initials'   => $this->initials ?: strtoupper(mb_substr($this->name, 0, 2)),
        ];
    }
}
