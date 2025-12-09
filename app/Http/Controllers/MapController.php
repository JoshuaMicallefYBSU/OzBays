<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use App\Models\Flights;

class MapController extends Controller
{
    public function index()
    {
        $jsonPath = public_path('config/drome.json');
        $airports = json_decode(File::get($jsonPath), true);

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
                            'title'     => "{$airport['icao']} Stand {$bay}",
                            'terminal'  => $stand['Terminal'] ?? '',
                            'aircraft'  => $stand['AC'] ?? '',
                            'priority'  => $stand['Priority'] ?? '',
                            'type'      => 'parking',
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
            'geojson'        => json_encode([
                'type' => 'FeatureCollection',
                'features' => $features,
            ]),
            'airportsJson'   => json_encode($airports),
            'aircraftJson'   => $flights->toJson(),
        ]);
    }
}
