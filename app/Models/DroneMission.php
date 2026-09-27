<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DroneMission extends Model
{
    protected $table = 'drone_missions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'aoi_id',
        'mission_request_id',
        'created_by',
        'altitude_m',
        'overlap_pct',
        'speed_mps',
        'kmz_file_path',
        'status',
        'notes',
    ];

    protected $casts = [
        'id'          => 'string',
        'aoi_id'      => 'string',
        'created_by'  => 'string',
        'altitude_m'  => 'float',
        'overlap_pct' => 'float',
        'speed_mps'   => 'float',
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

    public function orthomosaics()
    {
        return $this->hasMany(Orthomosaic::class, 'mission_id');
    }

    public function missionRequest()
    {
        return $this->belongsTo(MissionRequest::class);
    }
}
