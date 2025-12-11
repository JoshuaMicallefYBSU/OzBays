<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bays extends Model
{
    protected $casts = [
        'status' => 'integer',
    ];

    protected $fillable = [
        'airport',
        'bay',
        'lat',
        'lon',
        'aircraft',
        'pax_type',
        'status',
        'operators',
        'priority',
        'callsign',
        'clear',
        'check_exist'
    ];

    public function scopeForAirport($query, $icao)
    {
        return $query->where('airport', $icao);
    }

    // public function flight()
    // {
    //     return $this->hasOne(Flights::class, 'callsign', 'callsign');
    // }
}
