<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SamplingPoint extends Model
{
    protected $table = 'sampling_points';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'grid_id',
        'assigned_to',
        'latitude',
        'longitude',
        'priority_rank',
        'status',
        'transmitted_at',
        'collected_at',
        'notes',
    ];

    protected $casts = [
        'id'             => 'string',
        'grid_id'        => 'string',
        'assigned_to'    => 'string',
        'latitude'       => 'float',
        'longitude'      => 'float',
        'priority_rank'  => 'integer',
        'transmitted_at' => 'datetime',
        'collected_at'   => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function samplingGrid()
    {
        return $this->belongsTo(SamplingGrid::class, 'grid_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function soilReading()
    {
        return $this->hasOne(SoilReading::class, 'point_id');
    }
}
