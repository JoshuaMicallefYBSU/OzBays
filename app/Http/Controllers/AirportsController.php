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
}
