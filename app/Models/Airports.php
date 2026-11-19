<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airports extends Model
{
    protected $fillable = [
        'icao',
        'lat',
        'lon',
        'name',
        'color',
        'status',
        'check_exist',
        'eibt_variable',
        'taxi_time',
        'live_bays',
        'live_update_times',
        'stats_ground',
        'stats_inbound',
        'stats_controllers',
        'stats_controller_positions',
        'stats_inbound_bay_assigned',
        'stats_ground_breakdown',
        'controller_prefix',
    ];

    protected $casts = [
        'stats_controller_positions' => 'array',
        'stats_ground_breakdown' => 'array',
    ];

    public function allBays()
    {
        return $this->hasMany(Bays::class, 'airport', 'icao');
    }

    public function occupiedBays()
    {
        return $this->hasMany(Bays::class, 'airport', 'icao');
    }
}
