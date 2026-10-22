<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\Flights;
use App\Services\AircraftSizes;

class APIController extends Controller
{
    /**
     * Display the specified resource.
     */

    // OzStrips API Endpoint
    public function OzStrips()
    {
        $airports = Airports::where('status', 'active')->pluck('icao');

        $flights = Flights::select(['callsign', 'dep', 'arr', 'distance', 'scheduled_bay'])
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

    public function OzStripsCDM()
    {
        return response()->json(
            Airports::where('status', 'active')
                ->with(['occupiedBays' => function ($query) {
                    $query->where('status', 1)
                        ->select('airport', 'bay', 'callsign');
                }])
                ->get()
                ->keyBy('icao')
                ->map(function ($airport) {
                    return [
                        'occupied_bays' => $airport->occupiedBays
                            ->groupBy('callsign')
                            ->map(function ($bays) {
                                $bay = $bays->first();
                                return [
                                    'callsign' => $bay->callsign,
                                    'bay' => $bay->bay,
                                ];
                            })
                            ->values(),
                    ];
                })
        );
    }
    
    // Current Flights Recorded by the OzBays Server
    public function liveFlights(AircraftSizes $sizes)
    {
        $flights = Flights::where('online', 1)->with('mapBay')->get();

        // Real-world (IRL) bay for each flight, matched the same way BayAllocation does - callsign + departure + arrival airport
        $liveBays = FlightLiveBays::whereIn('callsign', $flights->pluck('callsign'))
            ->whereNotNull('scheduled_bay')
            ->whereNotNull('departure')
            ->with('bayInfo')
            ->get()
            ->keyBy(fn ($live) => $live->callsign.'|'.$live->departure.'|'.$live->airport);

        foreach ($flights as $flight) {
            $irlBay = $liveBays->get($flight->callsign.'|'.strtoupper((string) $flight->dep).'|'.$flight->arr)?->bayInfo;

            $flight->setAttribute('irl_bay', $irlBay === null ? null : [
                'bay'          => $irlBay->bay,
                'display_name' => $irlBay->display_name,
                'fits'         => $sizes->bayFitsAircraft($irlBay->aircraft, $flight->ac),
            ]);

            // Map icon for the aircraft's size group (icon, ignored, scale)
            $flight->setAttribute('map_icon', $sizes->icon($flight->ac));
        }

        return $flights;
    }

    // Live Bays for all Airports
    public function liveBays()
    {
        $bays = Bays::with('arrivalSlots')->get();

        return $bays;
    }
}
