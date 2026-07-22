<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoginAttempt extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'email',
        'ip_address',
        'success',
        'attempted_at',
    ];

    protected $casts = [
        'id'           => 'string',
        'user_id'      => 'string',
        'success'      => 'boolean',
        'attempted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper: count recent failed attempts for lockout logic
    public static function recentFailedCount(string $email, int $minutes = 15): int
    {
        return static::where('email', $email)
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes($minutes))
            ->count();
    }
}
