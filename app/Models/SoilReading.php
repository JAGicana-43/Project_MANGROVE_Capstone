<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SoilReading extends Model
{
    protected $table = 'soil_readings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'point_id',
        'species',
        'collected_by',
        'ph_avg',
        'salinity_avg',
        'moisture_avg',
        'nitrogen_avg',
        'phosphorus_avg',
        'potassium_avg',
        'reading_count',
        'suitability_rating',
        'remarks',
        'is_synced',
        'synced_at',
        'collected_at',
    ];

    protected $casts = [
        'id'                 => 'string',
        'point_id'           => 'string',
        'collected_by'       => 'string',
        'ph_avg'             => 'float',
        'salinity_avg'       => 'float',
        'moisture_avg'       => 'float',
        'nitrogen_avg'       => 'float',
        'phosphorus_avg'     => 'float',
        'potassium_avg'      => 'float',
        'reading_count'      => 'integer',
        'is_synced'          => 'boolean',
        'synced_at'          => 'datetime',
        'collected_at'       => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn($model) => $model->id = (string) Str::uuid());
    }

    // Relationships
    public function samplingPoint()
    {
        return $this->belongsTo(SamplingPoint::class, 'point_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
