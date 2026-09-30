<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bays extends Model
{
    protected $casts = [
        'status' => 'integer',
    ];

    // Included in JSON so the map/API can show the full bay name
    protected $appends = ['display_name'];

    protected $fillable = [
        'airport',
        'bay',
        'long_name',
        'lat',
        'lon',
        'aircraft',
        'pax_type',
        'status',
        'operators',
        'priority',
        'callsign',
        'clear',
        'check_exist',
        'terminal',
    ];

    // Pilot/map friendly name - e.g. "Domestic 55A (D55A)", or just "D55A" when there is no long name
    public function getDisplayNameAttribute(): string
    {
        return $this->long_name ? "{$this->long_name} ({$this->bay})" : (string) $this->bay;
    }

    public function scopeForAirport($query, $icao)
    {
        return $query->where('airport', $icao);
    }

    // For BayAllocations Job - Checking if there are duplicate entries.
    public function arrivalSlots()
    {
        return $this->hasMany(BayAllocations::class, 'bay', 'id');
    }

    // Get the Callsign ID for the aircraft planned/occupying the bay
    public function FlightInfo()
    {
        return $this->hasOne(Flights::class, 'callsign', 'callsign');
    }
}
