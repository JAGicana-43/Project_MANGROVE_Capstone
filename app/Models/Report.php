<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Report extends Model
{
    protected $table = 'reports';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'project_id',
        'generated_by',
        'title',
        'report_type',
        'file_path',
        'file_format',
        'download_count',
        'generated_at',
    ];

    protected $casts = [
        'id'             => 'string',
        'project_id'     => 'string',
        'generated_by'   => 'string',
        'download_count' => 'integer',
        'generated_at'   => 'datetime',
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

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // Helper
    public function incrementDownload(): void
    {
        $this->increment('download_count');
    }
}
