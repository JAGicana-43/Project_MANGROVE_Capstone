<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    // Cap on projects one researcher can own (archived ones included --
    // they still hold orthomosaics on disk). Delete a project to free a slot.
    public const MAX_PER_USER = 8;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'created_by',
        'name',
        'description',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'id'         => 'string',
        'created_by' => 'string',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function areasOfInterest()
    {
        return $this->hasMany(AreaOfInterest::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }
}
