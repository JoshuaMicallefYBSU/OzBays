<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\BayAllocations;
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
        ############ 1. Check Bay Status = Either Occupied, Or Empty
        {
            // Initialise all Variables
            $flights = Flights::where('online', 1)->get();
            $airports = Airports::all()->keyBy('icao');
            $bays = Bays::all();

            $occupiedBays = []; //List of all bays currently with an Aircraft parked in them

            ## BAY OCCUPANCY CHECKER - This needs to be done first everytime.
            // Set all bays as clear (will be propogated through shortly)
            foreach($bays as $bay){
                $bay->clear = 1;
                $bay->save();
            }

            // Get all the Bay Data from All Flights
            foreach($flights as $ac){

                $dist = $this->airportDistance($ac->lat, $ac->lon, $airports);
                // dd($dist);

                // Aircraft must be stationary to be occupying a bay
                if(($dist['YBBN'] < 3 || $dist['YSSY'] < 3 || $dist['YMML'] < 3 || $dist['YPPH'] < 3) && $ac->groundspeed < 5){

                    // Search through every single bay to see if there are any presently being occupied.
                    foreach($bays as $bay){

                        // Only do calculations for bays at the airport of interest
                        if($bay->airport !== $dist['ICAO']){
                            continue;
                        }
                        
                        // Calculate Aircraft Distance from all bays at the airport
                        $distance = $this->BayDistanceChecker(
                            $ac->lat, $ac->lon, $bay->lat, $bay->lon
                        );

                        if ($distance <= 30) {

                            $core = $this->bayCore($bay->bay);

                            $acBays = Bays::where('airport', $dist['ICAO'])
                                ->whereRaw(
                                    'bay REGEXP ?',
                                    ['^' . $core . '(?!\\d)([A-Z])?$']
                                )
                            ->get();

                            foreach($acBays as $acb){
                                $occupiedBays[$acb->id] = [
                                    'callsign_id'   => $ac->id, //ID
                                    'callsign'      => $ac->callsign, //ID
                                    'bay_id'        => $acb->id,
                                    'bay_core'      => $core,
                                    'type'          => $ac->type,
                                    'airport'       => $dist['ICAO'],
                                ];
                            }

                            // Update them
                            $acBays->each(function ($bay) use ($ac) {
                                $bay->update([
                                    'callsign' => $ac->callsign,
                                    'status'   => 2,
                                    'clear'    => 0,
                                ]);
                            });

                            break;
                        }

                    }
                }
            }

            // Bays where they where blocked, but are now free from any aircraft --- Clear Bay & Delete AC Slot
            $clearBays = Bays::where('status', 2)->where('clear', 1)->get();

            foreach($clearBays as $bay){
                // Remove all slots for Departure - They have now left the gate
                $slotsClear = BayAllocations::where('callsign', $bay->currentAircraft->id)->get();
                foreach($slotsClear as $slot){
                    $slot->delete();
                }

                // Update the bay as available
                $bay->status = null;
                $bay->callsign = null;
                $bay->save();
            }
        }

        ############ 2. Update Slot Infromation for Aircraft on the Ground!
        {
            // Slot Allocation - Check it exists for the aircraft at the bay 
            // - Allows for Scheduler to understand what aircraft actually are on the ground, and not slot anyone on that bay inside the alotted slot time.
            foreach($occupiedBays as $bayInfo){
                // Look if there is a slot for the aircraft
                $slot = BayAllocations::where('bay', $bayInfo['bay_id'])->get();

                // dd($slot);

                if($slot->isEmpty()){
                    
                    if($bayInfo['type'] == "INTL" || $bayInfo['type'] == null){
                        $eobt = Carbon::now()->addMinutes(60);
                    } else {
                        $eobt = Carbon::now()->addMinutes(45);
                    }

                    BayAllocations::create([
                        'airport'   => $bayInfo['airport'],
                        'bay'       => $bayInfo['bay_id'],
                        'bay_core'  => $bayInfo['bay_core'],
                        'callsign'  => $bayInfo['callsign_id'],
                        'status'    => "OCCUPIED",
                        'eibt'      => Carbon::now(),
                        'eobt'      => $eobt,
                    ]);

                }
            }
        }

        ############ 3. Check Planned Slots 
        {

        }

        ############ 5.
        {
            
        }

        ############ 5.
        {
            
        }
        

            // dd($occupiedBays);

        {

        }


        ### END OF THE JOB - THIS IS WHERE END DATA LIVES BITCHES
        // dd($bayChecker);
        // dd($occupiedBays);
    }

    // PRIVATE FUNCTIONS - YOLO AND HOPE FOR A PRAYER BOIS THIS STUFF IS CONFUSING
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

    function bayCore(string $bay): string
    {
        preg_match('/^[A-Za-z]*\d+/', $bay, $m);
        return $m[0];
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
