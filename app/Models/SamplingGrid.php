<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SamplingGrid extends Model
{
    protected $table = 'sampling_grids';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'grid_x',
        'grid_y',
        'dominant_cover',
        'suitability_score',
        'cell_bounds',
        'center_latitude',
        'center_longitude',
        'computed_at',
    ];

    protected $casts = [
        'id'                => 'string',
        'run_id'            => 'string',
        'grid_x'            => 'integer',
        'grid_y'            => 'integer',
        'suitability_score' => 'float',
        'cell_bounds'       => 'array',
        'center_latitude'   => 'float',
        'center_longitude'  => 'float',
        'computed_at'       => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function classificationRun()
    {
        return $this->belongsTo(ClassificationRun::class, 'run_id');
    }

    public function samplingPoints()
    {
        return $this->hasMany(SamplingPoint::class, 'grid_id');
    }
}
