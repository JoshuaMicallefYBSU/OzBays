<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;

class PartialsController extends Controller
{
    ############## -------------------------------- ##############
    //  All Site Endpoints that update data over time
    ############## -------------------------------- ##############

    // Render Ladder for Airport
    public function updateLadder($icao)
    {
        $taxing = Flights::where('arr', $icao)->where('status', 'Arrived')->where('Online', 1)->with('mapBay')->orderBy('callsign', 'asc')->get();

        $arrival = Flights::where('arr', $icao)->where('status', 'On Approach')->where('Online', 1)->with('mapBay')->orderBy('distance', 'asc')->get();

        $occupied_bays = Bays::where('airport', $icao)
            ->where('status', 2)->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')
                ->from('bays')
                ->where('status', 2)
                ->groupBy('callsign');
            })->orderBy('callsign', 'asc')->get();

        return view('partials.arrival-ladder', compact('icao', 'taxing', 'arrival', 'occupied_bays'))->render();
    }

    // Render FlightInfo on Dashboard
    public function updateFlights()
    {
        return view('partials.dashboard-flight')->render();
    }
}
