<?php

namespace App\Http\Controllers;

use App\Models\Flights;
use App\Models\Airports;
use App\Models\Bays;

class MapController extends Controller
{
    public function index()
    {
        return view('map.index');
    }

    public function embed()
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

        // JSON_HEX_TAG etc. stop any "</script>" inside data strings from
        // breaking out of the inline <script> block these are printed into.
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

        return view('map.embed', [
            'geojson' => json_encode([
                'type'     => 'FeatureCollection',
                'features' => $features,
            ], $jsonFlags),
            'airportsJson' => $airports->toJson($jsonFlags),
            'aircraftJson' => $flights->toJson($jsonFlags),
        ]);
    }
}
