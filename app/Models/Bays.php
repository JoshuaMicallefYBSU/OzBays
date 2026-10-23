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
        'gate',
    ];

    // Gates this stand is reached from - "1,2" -> ['1', '2']
    public function gates(): array
    {
        return array_values(array_filter(array_map(fn ($gate) => strtoupper(trim($gate)), explode(',', (string) $this->gate)), 'strlen'));
    }

    // Every stand at an airport reached from an IRL gate (e.g. YBSU gate "2"). A terminal only narrows the
    // match when both the gate and the bay have one.
    public static function atGate(string $airport, ?string $gate, ?string $terminal = null)
    {
        $gate = strtoupper(trim((string) $gate));
        if ($gate === '') {
            return collect();
        }

        return self::where('airport', $airport)
            ->whereNotNull('gate')
            ->when($terminal, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('terminal')->orWhere('terminal', 'LIKE', '%'.$terminal.'%')))
            ->get()
            ->filter(fn (self $bay) => in_array($gate, $bay->gates(), true))
            ->values();
    }

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
