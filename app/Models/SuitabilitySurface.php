<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SuitabilitySurface extends Model
{
    protected $table = 'suitability_surfaces';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'aoi_id',
        'run_id',
        'interpolation_method',
        'output_file_path',
        'file_format',
        'legend',
        'generated_at',
    ];

    protected $casts = [
        'id'           => 'string',
        'aoi_id'       => 'string',
        'run_id'       => 'string',
        'legend'       => 'array',
        'generated_at' => 'datetime',
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

    public function classificationRun()
    {
        return $this->belongsTo(ClassificationRun::class, 'run_id');
    }
}
