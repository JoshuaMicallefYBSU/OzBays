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
            ->matched()
            ->whereNotNull('departure')
            ->with('bayInfo')
            ->get()
            ->keyBy(fn ($live) => $live->callsign.'|'.$live->departure.'|'.$live->airport);

        foreach ($flights as $flight) {
            $live = $liveBays->get($flight->callsign.'|'.strtoupper((string) $flight->dep).'|'.$flight->arr);

            $flight->setAttribute('irl_bay', $this->irlBayInfo($live, $flight->ac, $sizes));

            // Map icon for the aircraft's size group (icon, ignored, scale)
            $flight->setAttribute('map_icon', $sizes->icon($flight->ac));
        }

        return $flights;
    }

    // Real-world bay for the map: a single bay, or a gate group (fits when any stand behind the gate fits)
    private function irlBayInfo(?FlightLiveBays $live, ?string $aircraft, AircraftSizes $sizes): ?array
    {
        if ($live === null) {
            return null;
        }

        if ($live->isGateMatch()) {
            $stands = $live->gateBays();

            return $stands->isEmpty() ? null : [
                'bay'          => null,
                'gate'         => $live->gate,
                'display_name' => 'Gate '.$live->gate,
                'stands'       => $stands->pluck('bay')->all(),
                'fits'         => $stands->contains(fn ($bay) => $sizes->bayFitsAircraft($bay->aircraft, $aircraft)),
            ];
        }

        return $live->bayInfo === null ? null : [
            'bay'          => $live->bayInfo->bay,
            'display_name' => $live->bayInfo->display_name,
            'fits'         => $sizes->bayFitsAircraft($live->bayInfo->aircraft, $aircraft),
        ];
    }

    // Live Bays for all Airports
    public function liveBays()
    {
        $bays = Bays::with('arrivalSlots')->get();

        return $bays;
    }
}
