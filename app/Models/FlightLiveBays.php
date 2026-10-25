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
        'departure',
        'terminal',
        'gate',
        'match',
        'scheduled_bay',
    ];

    // 'bay' = the IRL gate is one OzBays bay (scheduled_bay), 'gate' = it's a gate covering several stands
    public const MATCH_BAY = 'bay';
    public const MATCH_GATE = 'gate';

    public function bayInfo()
    {
        return $this->hasOne(Bays::class, 'id', 'scheduled_bay');
    }

    public function isGateMatch(): bool
    {
        return $this->match === self::MATCH_GATE;
    }

    // Stands behind the IRL gate (gate matches only)
    public function gateBays()
    {
        return $this->isGateMatch() ? Bays::atGate($this->airport, $this->gate, $this->terminal) : collect();
    }

    // Rows that point at something - a single bay, or a gate group
    public function scopeMatched($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('scheduled_bay')->orWhere('match', self::MATCH_GATE));
    }
}
