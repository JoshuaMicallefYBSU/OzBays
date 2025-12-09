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
}
