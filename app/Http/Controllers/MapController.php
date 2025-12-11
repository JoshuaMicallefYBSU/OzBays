<?php

namespace App\Http\Controllers;

use App\Models\Flights;
use App\Models\Airports;
use App\Models\Bays;

class MapController extends Controller
{
    public function index()
    {
        $airports = Airports::all();
        $bays     = Bays::all();
        $flights  = Flights::where('online', 1)->with('mapBay')->get();

        // dd($flights);

        $features = [];

        foreach ($airports as $airport) {

            // dd($airport);

            /* -------------------------------------------------
             * Airport marker
             * ------------------------------------------------ */
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'title' => $airport->icao,
                    'name'  => $airport->name,
                    'type'  => 'airport',
                    'color' => $airport->color ?? null,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $airport->lon,
                        (float) $airport->lat,
                    ],
                ],
            ];

            // dd($features);
        }

        /* -------------------------------------------------
             * Parking bays
             * ------------------------------------------------ */
                foreach ($bays as $stand) {
                    // dd($stand);

                    $color = "green";
                    $status = "Available";

                    if($stand->status == 1){
                        $color = "orange";
                        $status = "Booked";
                    } elseif($stand->status == 2){
                        $color = "red";
                        $status = "Occupied";
                    }

                    $features[] = [
                        'type' => 'Feature',
                        'properties' => [
                            'icao'     => $stand->airport,
                            'bay'      => $stand->bay,
                            'status'   => $status,
                            'color'    => $color,
                            'type'     => 'parking',
                        ],
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [
                                (float) $stand->lon,
                                (float) $stand->lat,
                            ],
                        ],
                    ];
                }

        return view('map.index', [
            'geojson' => json_encode([
                'type'     => 'FeatureCollection',
                'features' => $features,
            ]),
            'airportsJson' => $airports->toJson(),
            'aircraftJson' => $flights->toJson(),
        ]);
    }
}
