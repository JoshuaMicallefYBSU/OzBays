<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;

class BayAllocation implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Initialise all Variables
        $flights = Flights::where('online', 1)->get();
        $airports = Airports::all()->keyBy('icao');
        $bays = Bays::all();

        $aircraftBays = [];

        ## BAY OCCUPANCY CHECKER - This needs to be done first everytime.
        // Set all bays as clear (will be propogated through shortly)
        foreach($bays as $bay){
            $bay->clear = 1;
            $bay->save();
        }

        foreach($flights as $ac){

            $dist = $this->airportDistance($ac->lat, $ac->lon, $airports);

            // Aircraft must be stationary to be occupying a bay
            if(($dist['YBBN'] < 3 || $dist['YSSY'] < 3 || $dist['YMML'] < 3 || $dist['YPPH'] < 3) && $ac->groundspeed < 5){
                // Search through every single bay to see if there are any presently being occupied.
                foreach($bays as $bay){
                    $distance = $this->BayDistanceChecker(
                        $ac->lat, $ac->lon, $bay->lat, $bay->lon
                    );

                    if($distance <= 30) {
                        $OccupyBay = Bays::where('bay', $bay->bay)->where('airport', $dist['ICAO'])->first();
                        $OccupyBay->callsign = $ac->callsign;
                        $OccupyBay->status = 2;
                        $OccupyBay->clear = 0;
                        $OccupyBay->save();
                    }
                }
            }
        }

        // Bays where they where blocked, but are now free from any aircraft
        $clearBays = Bays::where('status', 2)->where('clear', 1)->get();
        foreach($clearBays as $bay){
            $bay->status = null;
            $bay->callsign = null;
            $bay->save();
        }
        // dd($bayChecker);

        // dd($aircraftBays);
    }

    // PRIVATE FUNCTIONS
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadiusNm = 3440.065; // Radius of Earth in nautical miles
    
        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);
    
        // Calculate the differences
        $latDifference = $lat2Rad - $lat1Rad;
        $lonDifference = $lon2Rad - $lon1Rad;
    
        // Apply Haversine formula
        $a = sin($latDifference / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($lonDifference / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceNm = $earthRadiusNm * $c;
        return $distanceNm;
    }

    public function airportDistance($lat, $lon, $airports){
        $airportDistance = [];

        // Check if Aircraft are close enough to trigger a bay check
        $airportDistance['YBBN'] = $this->calculateDistance($lat, $lon, $airports['YBBN']->lat, $airports['YBBN']->lon);
        $airportDistance['YSSY'] = $this->calculateDistance($lat, $lon, $airports['YSSY']->lat, $airports['YSSY']->lon);
        $airportDistance['YMML'] = $this->calculateDistance($lat, $lon, $airports['YMML']->lat, $airports['YMML']->lon);
        $airportDistance['YPPH'] = $this->calculateDistance($lat, $lon, $airports['YPPH']->lat, $airports['YPPH']->lon);

        asort($airportDistance);
        $closestICAO = null;
        $closestDist = reset($airportDistance);
        if($closestDist < 3){
            $closestICAO = key($airportDistance);
        }
        $airportDistance['ICAO'] = $closestICAO;

        return $airportDistance;
    }

    private function BayDistanceChecker(float $lat1, float $lon1, float $lat2, float $lon2): 
        float {
            $earthRadius = 6371000; // meters

            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);

            $a =
                sin($dLat / 2) * sin($dLat / 2) +
                cos(deg2rad($lat1)) *
                cos(deg2rad($lat2)) *
                sin($dLon / 2) * sin($dLon / 2);

            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

            return $earthRadius * $c;
        }

}
