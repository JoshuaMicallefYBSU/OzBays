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
    public static function record(string $airport, string $callsign, string $eventType, ?string $status = null, array $payload = [], $cid = null): self
    {
        return static::create([
            'airport' => strtoupper($airport),
            'callsign' => $callsign,
            'cid' => $cid !== null ? (int) $cid : null,
            'event_type' => $eventType,
            'status' => $status,
            'payload' => $payload,
        ]);
    }
}
