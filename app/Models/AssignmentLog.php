<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentLog extends Model
{
    // Append-only audit log - there's nothing to update after the fact.
    const UPDATED_AT = null;

    protected $fillable = [
        'airport',
        'callsign',
        'cid',
        'event_type',
        'status',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    // Convenience helper used throughout BayAllocation/FlightData so call sites
    // don't have to repeat the airport-uppercasing/array-casting boilerplate.
    //
    // Enforced here rather than at each call site: a flight's `arr` field on the
    // Flights table reflects whatever it's currently filed to, which for an
    // aircraft parked at an OzBays airport about to depart is its real-world
    // (often overseas) destination, not an OzBays-tracked ICAO. Checking here
    // once guarantees the log can never pick up an airport the system doesn't
    // actually monitor, however a future call site ends up sourcing it.
    public static function record(string $airport, string $callsign, string $eventType, ?string $status = null, array $payload = [], $cid = null): ?self
    {
        $airport = strtoupper($airport);

        if (! Airports::where('icao', $airport)->exists()) {
            return null;
        }

        return static::create([
            'airport' => $airport,
            'callsign' => $callsign,
            'cid' => $cid !== null ? (int) $cid : null,
            'event_type' => $eventType,
            'status' => $status,
            'payload' => $payload,
        ]);
    }
}
