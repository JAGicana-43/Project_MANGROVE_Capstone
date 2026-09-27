<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassificationRun extends Model
{
    protected $table = 'classification_runs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'orthomosaic_id',
        'model_version',
        'confidence_threshold',
        'total_tiles',
        'processed_tiles',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'id'                   => 'string',
        'orthomosaic_id'       => 'string',
        'confidence_threshold' => 'float',
        'total_tiles'          => 'integer',
        'processed_tiles'      => 'integer',
        'started_at'           => 'datetime',
        'completed_at'         => 'datetime',
        'cancelled_at'         => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function orthomosaic()
    {
        return $this->belongsTo(Orthomosaic::class);
    }

    public function classifiedTiles()
    {
        return $this->hasMany(ClassifiedTile::class, 'run_id');
    }

    public function samplingGrids()
    {
        return $this->hasMany(SamplingGrid::class, 'run_id');
    }

    public function suitabilitySurfaces()
    {
        return $this->hasMany(SuitabilitySurface::class, 'run_id');
    }

    // Helper
    public function progressPercent(): float
    {
        if ($this->total_tiles === 0) return 0;
        return round(($this->processed_tiles / $this->total_tiles) * 100, 1);
    }
}
