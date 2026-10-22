<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirportLocation extends Model
{
    protected $fillable = [
        'icao',
        'name',
        'lat',
        'lon',
    ];
}
