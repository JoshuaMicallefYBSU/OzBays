<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\BayAllocations;

class Flights extends Model
{
    protected $fillable = [
        'callsign',
        'cid',
        'dep',
        'arr',
        'ac',
        'hdg',
        'type',
        'lat',
        'lon',
        'speed',
        'alt',
        'distance',
        'elt',
        'eibt',
        'status',
        'online',
        'current_bay',
        'scheduled_bay',
    ];

    public function assignedBay()
    {
        return $this->hasMany(BayAllocations::class, 'callsign', 'id');
    }

    public function mapBay()
    {
        return $this->hasOne(Bays::class, 'id', 'scheduled_bay');
    }

    public function bayConflict()
    {
        return $this->hasOne(BayConflicts::class, 'id', 'callsign');
    }

    public function arrivalAirport()
    {
        return $this->hasOne(Airports::class, 'icao', 'arr');
    }
}
