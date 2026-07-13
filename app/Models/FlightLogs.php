<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\BayAllocations;

class FlightLogs extends Model
{
    protected $fillable = [
        'callsign',
        'airline',
        'arrival',
        'type',
        'aircraft',
        'user_id',
    ];
}
