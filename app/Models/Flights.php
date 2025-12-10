<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'online'
    ];

    public function assignedBay()
    {
        return $this->hasOne(Bays::class, 'callsign', 'callsign');
    }
}
