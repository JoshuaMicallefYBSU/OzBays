<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use App\Models\Flights;
use App\Models\Airports;
use App\Models\Bays;

class MapController extends Controller
{
    public function index()
{
    // $jsonPath = public_path('config/drome.json');

    // $rawJson  = json_decode(File::get($jsonPath), true);
    // $airports = $rawJson['Airports'] ?? [];

    $airports = Airports::all();
    $parking = Bays::all();

    $flights = Flights::where('online', 1)->get();
    $features = [];

    foreach ($airports as $airport) {

        // Airport marker
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'title' => $airport['icao'],
                'icao'  => $airport['icao'],
                'type'  => 'airport',
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    $airport['lon'],
                    $airport['lat'],
                ],
            ],
        ];

        // Parking bays
        if (!empty($airport['parking'])) {
            foreach ($airport['parking'] as $bay => $stand) {
                $features[] = [
                    'type' => 'Feature',
                    'properties' => [
                        'title'    => "{$airport['icao']} Stand {$bay}",
                        'aircraft' => "Max {$stand['AC']}" ?? '',
                        'type'     => 'parking',
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            $stand['lon'],
                            $stand['lat'],
                        ],
                    ],
                ];
            }
        }
    }

    return view('map.index', [
        'geojson'      => json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]),
        'airportsJson' => json_encode($airports),
        'aircraftJson' => $flights->toJson(),
    ]);
}

}
