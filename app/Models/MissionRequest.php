<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MissionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'aoi_polygon',
        'drone_key',
        'altitude',
        'overlap_percent',
        'gimbal_angle',
        'waypoint_speed_mps',
        'finish_action',
        'execute_rc_lost_action',
        'transitional_speed_mps',
        'waypoint_count',
        'line_spacing_meters',
        'total_distance_meters',
        'waypoints',
        'status',
        'kmz_path',
        'error_message',
        'processed_at',
    ];

    protected $casts = [
        'aoi_polygon' => 'array',
        'waypoints' => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
