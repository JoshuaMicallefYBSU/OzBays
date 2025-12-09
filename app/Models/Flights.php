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
        'distance',
        'elt',
        'eibt',
        'status',
        'online'
    ];
}
