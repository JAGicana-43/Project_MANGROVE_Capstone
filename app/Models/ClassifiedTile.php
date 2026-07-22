<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassifiedTile extends Model
{
    protected $table = 'classified_tiles';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'tile_x',
        'tile_y',
        'land_cover_type',
        'confidence',
        'center_latitude',
        'center_longitude',
        'tile_bounds',
        'created_at',
    ];

    protected $casts = [
        'id'               => 'string',
        'run_id'           => 'string',
        'tile_x'           => 'integer',
        'tile_y'           => 'integer',
        'confidence'       => 'float',
        'center_latitude'  => 'float',
        'center_longitude' => 'float',
        'tile_bounds'      => 'array',
        'created_at'       => 'datetime',
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
}
