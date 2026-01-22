<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Airports;
use App\Models\Bays;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }

    public function airportList()
    {
        $airports = Airports::all();

        return view('dashboard.admin.airport-list', compact('airports'));
    }

    public function airportView($icao)
    {
        $airport = Airports::where('icao', $icao)->first();

        if($airport == null){
            return redirect()->route('dashboard.admin.airport.all')->with('error', 'No airport definition has been made for '.$icao.'. Please select from the below airport options.');
        }

        return view('dashboard.admin.airport-view', compact('airport'));
    }

    public function bayView($icao, $bay_url)
    {
        $bay = Bays::where('bay', $bay_url)->where('airport', $icao)->first();

        if($bay == null){
            return redirect()->route('dashboard.admin.airport.view', [$icao])->with('error', 'Bay '.$bay.' does not exist at '.$icao.'. Please select from the below bay options.');
        }

        return view('dashboard.admin.bay-view', compact('bay'));
    }
}
