<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Bays;
use App\Models\BayAllocations;
use App\Models\Flights;

class BayConflicts extends Model
{
    protected $table = 'bay_conflict';    

    protected $fillable = [
        'callsign',
        'bay',
    ];

    public function FlightInfo()
    {
        return $this->belongsTo(Flights::class, 'callsign', 'id');
    }

    public function BayInfo()
    {
        return $this->belongsTo(Bays::class, 'bay', 'id');
    }

    public function SlotInfo()
    {
        return $this->hasMany(BayAllocations::class, 'bay', 'bay');
    }
}