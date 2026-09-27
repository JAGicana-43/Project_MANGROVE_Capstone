<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AreaOfInterest extends Model
{
    protected $table = 'areas_of_interest';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'boundary_coordinates',
        'boundary_polygon',
        'center_latitude',
        'center_longitude',
        'area_sqm',
        'status',
        'notes',
    ];

    protected $casts = [
        'id'                    => 'string',
        'project_id'            => 'string',
        'created_by'            => 'string',
        'boundary_coordinates'  => 'array',
        'center_latitude'       => 'float',
        'center_longitude'      => 'float',
        'area_sqm'              => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function droneMissions()
    {
        return $this->hasMany(DroneMission::class, 'aoi_id');
    }

    public function orthomosaics()
    {
        return $this->hasMany(Orthomosaic::class, 'aoi_id');
    }

    public function suitabilitySurfaces()
    {
        return $this->hasMany(SuitabilitySurface::class, 'aoi_id');
    }

    public function surveyComparisons()
    {
        return $this->hasMany(SurveyComparison::class, 'aoi_id');
    }
}
