<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BayRequest extends Model
{
    // Most bays a pilot can request for one flight
    public const MAX_REQUESTS = 3;

    protected $fillable = [
        'user_id',
        'callsign',
        'airport',
        'bay_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function bay()
    {
        return $this->belongsTo(Bays::class, 'bay_id', 'id');
    }

    // The requests that belong to this flight - same pilot, callsign and arrival airport - first choice first
    public function scopeForFlight($query, Flights $flight)
    {
        return $query->where('user_id', (int) $flight->cid)
            ->where('callsign', $flight->callsign)
            ->where('airport', $flight->arr)
            ->orderBy('position');
    }
}
