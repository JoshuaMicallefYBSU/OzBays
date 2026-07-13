<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightLiveMissingBays extends Model
{
    protected $table = 'flight_live_missing_bays';

    protected $fillable = [
        'id',
        'callsign',
        'airport',
        'terminal',
        'bay',
        'aircraft',
    ];

    public function bayInfo()
    {
        return $this->hasOne(Bays::class, 'id', 'scheduled_bay');
    }
}
