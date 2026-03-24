<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightLiveBays extends Model
{
    protected $table = 'flight_live_bay';

    protected $fillable = [
        'id',
        'callsign',
        'airport',
        'terminal',
        'gate',
        'scheduled_bay',
    ];

    public function bayInfo()
    {
        return $this->hasOne(Bays::class, 'id', 'scheduled_bay');
    }
}
