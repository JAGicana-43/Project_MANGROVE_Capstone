<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'role_id',
        'name',
        'avatar_path',
        'email',
        'password',
        'must_change_password',
        'is_active',
        'status',
        'approved_by',
        'approved_at',
        'last_login_at',
        'onboarding_completed_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'avatar_url',
    ];

    protected $casts = [
        'id'            => 'string',
        'role_id'       => 'string',
        'is_active'     => 'boolean',
        'must_change_password' => 'boolean',
        'approved_by'   => 'string',
        'approved_at'   => 'datetime',
        'last_login_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function projectMemberships()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function soilReadings()
    {
        return $this->hasMany(SoilReading::class, 'collected_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? asset('storage/' . $this->avatar_path) : null;
    }

    // Helpers
    public function hasRole(string $role): bool
    {
        return $this->role->name === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function needsWelcomeTour(): bool
    {
        return $this->hasRole('researcher') && is_null($this->onboarding_completed_at);
    }
}
