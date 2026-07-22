<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Orthomosaic extends Model
{
    protected $table = 'orthomosaics';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'aoi_id',
        'mission_id',
        'uploaded_by',
        'file_path',
        'file_format',
        'resolution_cm',
        'bounds_coordinates',
        'coordinate_system',
        'status',
        'captured_at',
        'uploaded_at',
    ];

    protected $casts = [
        'id'                 => 'string',
        'aoi_id'             => 'string',
        'mission_id'         => 'string',
        'uploaded_by'        => 'string',
        'bounds_coordinates' => 'array',
        'resolution_cm'      => 'float',
        'captured_at'        => 'datetime',
        'uploaded_at'        => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function areaOfInterest()
    {
        return $this->belongsTo(AreaOfInterest::class, 'aoi_id');
    }

    public function mission()
    {
        return $this->belongsTo(DroneMission::class, 'mission_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function classificationRuns()
    {
        return $this->hasMany(ClassificationRun::class);
    }
}
