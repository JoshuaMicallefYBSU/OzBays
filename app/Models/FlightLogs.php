<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightLogs extends Model
{
    protected $fillable = [
        'callsign',
        'airline',
        'departure',
        'arrival',
        'type',
        'aircraft',
        'assigned_bay',
        'parked_bay',
        'user_id',
    ];

    // Airline ICAO from an airline-format callsign - 3 letters then a flight number (QFA204, JST511A).
    // Registrations and other callsigns (N26KR, VHOQA, RSCU1) have no airline, so return null.
    public static function airlineFromCallsign(?string $callsign): ?string
    {
        $callsign = strtoupper(trim((string) $callsign));

        return preg_match('/^([A-Z]{3})\d[A-Z0-9]*$/', $callsign, $match) ? $match[1] : null;
    }

    public function airlineInfo()
    {
        return $this->belongsTo(Airline::class, 'airline', 'icao');
    }
}
