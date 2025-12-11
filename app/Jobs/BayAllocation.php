<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
        $test = [];


        
        ############ 1. Check Bay Status = Either Occupied, Or Empty
        {
            // All Aircraft Types
            $jsonPath = public_path('config/aircraft.json');
            $rawJson = json_decode(File::get($jsonPath), true);
            $aircraftJSON = array_flip($rawJson);
            
            // $jsonPath = public_path('config/new-aircraft.json');
            // $rawJson = json_decode(File::get($jsonPath), true);
            
            // $flat = [];

            // foreach ($rawJson as $groupKey => $types) {
            //     // Extract group-level codes: "A320/B738" → ["A320", "B738"]
            //     $outerCodes = explode('/', $groupKey);

            //     // 1. Add outer codes first (avoid duplicates)
            //     foreach ($outerCodes as $outer) {
            //         if (!in_array($outer, $flat)) {
            //             $flat[] = $outer;
            //         }
            //     }

            //     // 2. Add nested items after
            //     foreach ($types as $t) {
            //         if (!in_array($t, $flat)) {
            //             $flat[] = $t;
            //         }
            //     }
            // }

            // $aircraftJSON = array_flip($flat);

            // dd($aircraftJSON);
            
            // Initialise all Variables
            $flights = Flights::where('online', 1)->get();
            $airports = Airports::all()->keyBy('icao');
            $bays = Bays::all();

            $occupiedBays = []; //List of all bays currently with an Aircraft parked in them
            $unscheduledArrivals = []; //List of all Arrivals within 300NM with no gate assigned
            $recomputeAircraft = []; //All Aircraft requiring Bay Recompute

            ## BAY OCCUPANCY CHECKER - This needs to be done first everytime.
            // Set all bays as clear (will be propogated through shortly)
            foreach($bays as $bay){
                $bay->clear = 1;
                $bay->save();
            }

            // Get all the Bay Data from All Flights
            foreach($flights as $ac){

                $dist = $this->airportDistance($ac->lat, $ac->lon, $airports);

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

                            // Mark bays as 
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

                // dd($ac->assignedBay);

                // Does aircraft require bay assignment?
                if($ac->speed > 80 && $ac->distance < 200 && $ac->status !== "Arrived" && $airports->has($ac->arr) && $ac->eibt !== null){
                    $unscheduledArrivals[] = ['cs' => $ac->callsign, 'cs_id' => $ac->id, 'arr' => $ac->arr, 'ac' => $ac->ac, 'elt' => $ac->elt, 'eibt' => $ac->eibt, 'ac_model' => $ac];
                }
            }

            // Bays where they where blocked, but are now free from any aircraft --- Clear Bay & Delete AC Slot
            $clearBays = Bays::where('status', 2)->where('clear', 1)->get();

            foreach($clearBays as $bay){
                // Remove all slots for Departure - They have now left the gate

                $slotsClear = BayAllocations::where('callsign', $bay->callsign)->get();
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

                    // dd($bayInfo);
                    
                    $eobt = $this->bayTimeCalcs($bayInfo['type']);

                    // dd($eobt);

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

        ############ 3. Check Planned Slots for Bay Conflicts
        {

        }
        
        // dd($unscheduledArrivals);
        ############ 4. Generate New Slots for Aircraft that do not have one yet
        {
            $assignedBays = [];

            foreach($unscheduledArrivals as $cs){

                // Assign a bay to the Aircraft
                $bay = $this->assignBay($cs, $aircraftJSON);

                $assignedBays[] = [
                    'cs' => $cs,
                    'id' => $bay];
            }
        }

        dd($assignedBays);

        

        ############ 5.
        {
            
        }


        ### END OF THE JOB - THIS IS WHERE END DATA LIVES BITCHES
        // dd($bayChecker);
        // dd($occupiedBays);
        dd($unscheduledArrivals);
    }

    ###########################
    # PRIVATE FUNCTIONS - YOLO AND HOPE FOR A PRAYER BOIS THIS STUFF IS CONFUSING
    ###########################

    # Assign a bay to aircraft (Either Reassign or Initial)
    private function selectBay($cs, $aircraftJSON)
    {
        $info = Flights::where('callsign', $cs)->first();

        $operator = substr($info->callsign, 0, 3); // Cuts off the Callsign

        // Does the Aircraft Code exist? If not, assume it a B738 for symplicity.
        if(!isset($aircraftJSON[$info->ac])){
            Log::channel('aircraft')->error($info->ac . ' type does not exist');
            $ac = 'B738';
        } else {
            $ac = $info->ac;
        }

        // Index the AC so
        $aircraftIndex = $aircraftJSON[$ac];
        $allowedTypes = array_slice($aircraftJSON, 0, $aircraftIndex + 1);
        $priorityOrder = array_reverse(array_keys($allowedTypes));

        $availableBays = Bays::where('airport', $info->arr)
            ->where(function ($q) use ($allowedTypes) {
                foreach (array_keys($allowedTypes) as $type) {
                    $q->orWhereRaw(
                        "aircraft REGEXP CONCAT('(^|/)', ?, '(/|$)')",
                        [$type]
                    );
                }
            })
            ->orderByRaw("FIELD(
                SUBSTRING_INDEX(aircraft, '/', 1),
                '" . implode("','", $priorityOrder) . "'
            )")
            ->get();

        // echo $availableBays;
        // dd($availableBays);

        // Sort by Priority Number
        $grouped = $availableBays->groupBy(function ($bay) {
            return explode('/', $bay->aircraft)[0];
        });

        $grouped = $grouped->map(function ($group) {
            return $group->sortBy('priority');
        });

        $addPriority = $grouped->flatten(1);

        // echo $addPriority;
        // dd($addPriority);

        // Remove non-operator specific bays (e.g. JST doesn't see VOZ bays)
        $finalBays = $addPriority->filter(function ($bay) use ($operator) {

            // Null or empty string = unrestricted
            if (empty($bay->operators)) {
                return true;
            }

            // Convert "VOZ, FCA, RXA" → ["VOZ", "FCA", "RXA"]
            $ops = array_map('trim', explode(',', $bay->operators));

            // Keep bay only if operator matches
            return in_array($operator, $ops, true);
        })->values();

        if($finalBays->first() == null ){
            Log::channel('bays')->error($info->ac . ' - No bay found at ' . $info->arr);
            // return;
        }

        // echo $finalBays;
        // dd($finalBays);

        $bestAircraft = explode('/', $finalBays->first()->aircraft)[0];
        $bestPriority = $finalBays->first()->priority;
        // dd($bestAircraft);

        

        // Strict best matches
        $strictCandidates = $finalBays->filter(function ($bay) use ($bestAircraft, $bestPriority) {
            return explode('/', $bay->aircraft)[0] === $bestAircraft
                && $bay->priority === $bestPriority;
        });

        // dd($strictCandidates);

        $topCandidates = collect();

        foreach ($finalBays as $bay) {
            if ($topCandidates->count() >= 7) {
                break;
            }

            // Always include strict best matches first
            if (
                explode('/', $bay->aircraft)[0] === $bestAircraft &&
                $bay->priority === $bestPriority
            ) {
                $topCandidates->push($bay);
                continue;
            }

            // Then allow next-best options
            if (!$topCandidates->contains('id', $bay->id)) {
                $topCandidates->push($bay);
            }
        }

        echo $topCandidates;

        // Randomise selection within top 7 - Wamt it to be a bit random over time :)
        $selectedBay = $topCandidates->random();
        
        // dd($selectedBay);

        return $selectedBay;
    }

    private function assignBay($cs, $aircraftJSON)
    {
        $value = $this->selectBay($cs, $aircraftJSON);
        $eobt = $this->bayTimeCalcs($cs['eibt']);
        
        // dd($cs);

        $core = $this->bayCore($value->bay);

        // dd($core);

        $newBay = BayAllocations::create([
                        'airport'   => $cs['arr'],
                        'bay'       => $value['id'],
                        'bay_core'  => $core,
                        'callsign'  => $cs['cs_id'],
                        'status'    => "PLANNED",
                        'eibt'      => $cs['eibt'],
                        'eobt'      => $eobt,
        ]);

        $markBay = Bays::find($value['id']);
        $markBay->status = 1;
        $markBay->callsign = $cs['cs'];
        $markBay->save();

        return $value;
    }

    private function bayTimeCalcs($type)
    {
        if($type == "INTL" || $type == null){
            $eobt = Carbon::now()->addMinutes(60);
        } else {
            $eobt = Carbon::now()->addMinutes(45);
        }

        return $eobt;
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2) 
    {
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

    private function bayCore(string $bay): string
    {
        preg_match('/^[A-Za-z]*\d+/', $bay, $m);
        return $m[0];
    }

    private function airportDistance($lat, $lon, $airports)
    {
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
