<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'payload',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected $casts = [
        'id'        => 'string',
        'user_id'   => 'string',
        'payload'   => 'array',
        'logged_at' => 'datetime',
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

    // Helper: easily log any action from anywhere in the app
    public static function record(
        string $action,
        ?string $tableName = null,
        ?string $recordId = null,
        ?array $payload = null
    ): void {
        static::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'table_name' => $tableName,
            'record_id'  => $recordId,
            'payload'    => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'logged_at'  => now(),
        ]);
    }
}
