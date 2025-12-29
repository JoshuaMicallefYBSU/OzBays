<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Airports;
use App\Models\Flights;
use App\Models\Bays;

class AirportsController extends Controller
{
    public function index()
    {
        $airports = Airports::all();

        // return $airports;

        return view('airport.index', compact('airports'));
    }

    public function airportLadder($icao)
    {
        $airport = Airports::where('icao', $icao)->first();

        if($airport == null){
            return redirect()->route('airportIndex')->with('info', "Airport ".$icao.' is not supported by OzBays. Please see all supported airports in the below table');
        }
        return view('airport.view', compact('airport'));
    }

    // Update Fucntion to rended the Ladder for the Airport
    public function updateLadder($icao)
    {
        $taxing = Flights::where('arr', $icao)->where('status', 'Arrived')->where('Online', 1)->with('mapBay')->orderBy('callsign', 'asc')->get();

        // return $taxing;

        $arrival = Flights::where('arr', $icao)->where('status', 'On Approach')->where('Online', 1)->with('mapBay')->orderBy('distance', 'asc')->get();

        $occupied_bays = Bays::where('airport', $icao)
            ->where('status', 2)->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')
                ->from('bays')
                ->where('status', 2)
                ->groupBy('callsign');
            })->orderBy('callsign', 'asc')->get();

        return view('airport.ladder', compact('icao', 'taxing', 'arrival', 'occupied_bays'))->render();
    }
}
