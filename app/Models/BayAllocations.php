<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BayAllocations extends Model
{
    protected $table = 'bay_allocation';    

    protected $fillable = [
        'airport',
        'bay',
        'callsign',
        'bay_core',
        'status', //PLANNED, LATE, OCCUPIED
        'eibt',
        'eobt',
    ];

    
}
