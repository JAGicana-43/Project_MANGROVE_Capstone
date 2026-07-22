<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SurveyComparison extends Model
{
    protected $table = 'survey_comparisons';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'aoi_id',
        'orthomosaic_a_id',
        'orthomosaic_b_id',
        'generated_by',
        'change_summary',
        'report_file_path',
        'notes',
        'compared_at',
    ];

    protected $casts = [
        'id'               => 'string',
        'aoi_id'           => 'string',
        'orthomosaic_a_id' => 'string',
        'orthomosaic_b_id' => 'string',
        'generated_by'     => 'string',
        'change_summary'   => 'array',
        'compared_at'      => 'datetime',
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

    public function orthomosaicA()
    {
        return $this->belongsTo(Orthomosaic::class, 'orthomosaic_a_id');
    }

    public function orthomosaicB()
    {
        return $this->belongsTo(Orthomosaic::class, 'orthomosaic_b_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
