<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Flights;
use App\Models\Bays;

class APIController extends Controller
{
    /**
     * Display the specified resource.
     */


    #### EXAMPLE FOR REDUCING API OUTPUT
    // $flights = Flights::select(['id', 'callsign', 'dep', 'arr', 'lat', 'lon'])
    //     ->where('online', 1)
    //     ->with([
    //         'mapBay:id,bay,airport' // limit related model fields
    //     ])
    //     ->get();

    // OzStrips API
    public function OzStrips()
    {
        $flights = Flights::select(['callsign', 'arr', 'distance', 'scheduled_bay'])
        ->where('online', 1)
        ->whereIn('arr', ['YBBN', 'YSSY', 'YMML', 'YPPH'])
        // ->where('distance', '<', '35')
        ->orderBy('distance', 'asc')
        ->get();

        return $flights;
    }
    
    public function liveFlights()
    {
        $flights = Flights::where('online', 1)->with('mapBay')->get();

        return $flights;
    }

    public function liveBays()
    {
        $bays = Bays::with('arrivalSlots')->get();

        return $bays;
    }
}
