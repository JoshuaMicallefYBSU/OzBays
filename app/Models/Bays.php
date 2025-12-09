<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bays extends Model
{
    protected $fillable = [
        'airport',
        'bay',
        'lat',
        'lon',
        'aircraft',
        'type',
        'status',
        'operators',
        'booking1_start',
        'booking1_end',
        'booking1_start',
        'booking2_end',
        'check_exist'
    ];
}
