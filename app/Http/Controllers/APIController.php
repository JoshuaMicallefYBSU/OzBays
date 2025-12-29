<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;

class APIController extends Controller
{
    /**
     * Display the specified resource.
     */

    // OzStrips API
    public function OzStrips()
    {
        $airports = Airports::where('status', 'active')->pluck('icao');

        $flights = Flights::select(['callsign', 'arr', 'distance', 'scheduled_bay'])
            ->where('online', 1)
            ->whereIn('arr', $airports)
            ->where('distance', '<', 150)
            ->with(['mapBay:id,bay'])
            ->orderBy('distance', 'asc')
            ->get()
            ->map(function ($flight) {
                $flight->scheduled_bay = $flight->mapBay->bay ?? null;
                unset($flight->mapBay);

                if ($flight->scheduled_bay !== null) {
                    $flight->scheduled_bay = Str::substr($flight->scheduled_bay, 0, 4);
                }
                return $flight;
            });

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
