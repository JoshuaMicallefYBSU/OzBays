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
        'taxi_time'
    ];

    public function allBays()
    {
        return $this->hasMany(Bays::class, 'airport', 'icao');
    }
}
