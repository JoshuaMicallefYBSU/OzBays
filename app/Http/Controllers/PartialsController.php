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

    public function updateAirportStats()
    {
        $stats_ground = Airports::whereIn('status', ['testing', 'active'])->where('stats_ground', '>', 0)->orderBy('stats_ground', 'desc')->limit(3)->get();
        $stats_inbound = Airports::whereIn('status', ['testing', 'active'])->where('stats_inbound', '>', 0)->orderBy('stats_inbound', 'desc')->limit(3)->get();

        return view('partials.airport-stats', compact('stats_ground','stats_inbound'))->render();
    }
}
