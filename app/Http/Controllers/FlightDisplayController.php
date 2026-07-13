<?php

namespace App\Http\Controllers;

use App\Services\AirportFlightDisplayData;

class FlightDisplayController extends Controller
{
    public function show(string $icao, string $callsign, AirportFlightDisplayData $displayData)
    {
        $display = $displayData->forAirportAndCallsign($icao, $callsign);
        abort_unless($display !== null, 404);

        return view('airport.flight-display', compact('display'));
    }

    public function partial(string $icao, string $callsign, AirportFlightDisplayData $displayData)
    {
        $display = $displayData->forAirportAndCallsign($icao, $callsign);
        abort_unless($display !== null, 404);

        return view('partials.flight-display', compact('display'))->render();
    }
}
