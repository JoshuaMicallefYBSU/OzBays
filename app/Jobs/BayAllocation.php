<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Services\DiscordClient;
use App\Services\HoppieClient;
use Carbon\Carbon;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\BayAllocations;
use App\Models\BayConflicts;
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
            // JSON Aircraft File
            $jsonPath = public_path('config/aircraft.json');
            $rawJson  = json_decode(File::get($jsonPath), true);

            $aircraftJSON = [];
            $priorityIndex = 0;

            foreach ($rawJson as $groupKey => $types) {

                // Skip allocation metadata
                if (str_starts_with($groupKey, 'AllocationInfo_')) {
                    continue;
                }

                // Ensure numeric indexing and preserve order
                $aircraftJSON[$priorityIndex] = array_values(array_unique($types));

                $priorityIndex++;
            }

            // dd($aircraftJSON);

            // dd($aircraftJSON);
            
            // Initialise all Variables
            $allFlights = Flights::with('assignedBay')->get(); //Used to check for any slots that were assigned, and then the aircraft diverted.
            $flights = Flights::where('online', 1)->get();
            $airports = Airports::all()->keyBy('icao');
            $bays = Bays::all();

            if(env('APP_DEBUG') == true){
                $discordChannel = config('services.discord.OzBays_Local');
            } else {
                $discordChannel = config('services.discord.OzBays');
            }

            $occupiedBays = []; //List of all bays currently with an Aircraft parked in them
            $baysInside = [];
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

                        // Find all bays within the 
                        if ($distance <= 30) {

                            $baysInside[] = $bay->bay;

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

                            // Mark bay as occupied
                            $acBays->each(function ($bay) use ($ac) {
                                $bay->update([
                                    'callsign' => $ac->callsign,
                                    'status'   => 2,
                                    'clear'    => 0,
                                ]);
                            });

                            // break;
                        }

                    }
                }

                // Does an arrival aircraft require bay assignment?
                if((empty($ac->assignedBay) || $ac->assignedBay->isEmpty()) && $ac->speed > 80 && $ac->distance < 200 && $ac->status !== "Arrived" && $airports->has($ac->arr) && $ac->eibt !== null){
                    $unscheduledArrivals[] = ['cs' => $ac->callsign, 'cs_id' => $ac->id, 'arr' => $ac->arr, 'ac' => $ac->ac, 'elt' => $ac->elt, 'eibt' => $ac->eibt, 'ac_model' => $ac];
                }
            }

            ## Bays that were blocked, but are now free from any aircraft --- Clear Bay & Delete AC Slot
            $clearBays = Bays::where('status', 2)->where('clear', 1)->get();
            foreach($clearBays as $bay){

                // Remove all slots for Departure - They have now left the gate
                $slotsClear = BayAllocations::where('callsign', $bay->FlightInfo->id)->get();
                foreach($slotsClear as $slot){
                    $slot->delete();
                }

                // // Update the bay as available
                $bay->status = null;
                $bay->callsign = null;
                $bay->save();
            }


            // Bays with planned arrivals. Check Slots and update status as required
            $bookedBays = Bays::where('status', 1)->get();
            foreach($bookedBays as $bay){
                // Any slot allocations? if not, then set bay as available
                if(empty($bay->arrivalSlots) || $bay->arrivalSlots->isEmpty()){
                    $bay->status = null;
                    $bay->callsign = null;
                    $bay->save();
                }
            }


            // Check and see if a airport Airport does not match the FlightPlan Arrival. If that is the case, delete the slot
            foreach ($allFlights as $flight) {

            if (!$flight->relationLoaded('assignedBay') || $flight->assignedBay->isEmpty()) {
                continue;
            }

            foreach ($flight->assignedBay as $bay) {
                if ($bay->airport !== $flight->arr && $flight->arr !== null && $bay->status == 'PLANNED') {
                    echo "Found invalid bay for {$flight->callsign}\n";

                    $bay->delete();

                    $discord = new DiscordClient();
                    $discord->sendMessageWithEmbed($discordChannel, "Aircraft Diversion / Refile | ".$flight->callsign, "Aircraft has diverted to another aerodrome, or reconnected with a different destination. Bay ".$bay->bay_core." at ".$bay->airport." has now been marked as available.", 'fc1c03');
                    break;
                }
            }
        }
        }

        ############ 2. Update Slot Infromation for Aircraft on the Ground!
        {
            // Slot Allocation - Check it exists for the aircraft at the bay 
            ### - Allows for Scheduler to understand what aircraft actually are on the ground, and not slot any arrivals on that bay inside the alotted slot time.
            foreach($occupiedBays as $bayInfo){

                // Look if there is a slot for the aircraft
                $slot = BayAllocations::where('bay', $bayInfo['bay_id'])->where('callsign', $bayInfo['callsign_id'])->get();

                // dd($slot);

                if($slot->isEmpty()){
                    
                    $eobt = $this->bayTimeCalcs($bayInfo['type']);

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

        // dd($occupiedBays);

        ############ 3. Check Planned Slots for Bay Conflicts & Reassignment
        {
            ### - ENR Aircraft wait 3mins before reassignment

            // Loop through each $occupiedBays and see if there are any slots that do not match the Aircraft
                // - If there are, we need to add the aircraft to the bay_conficts table, and later on do some reassignment.
            $data = [];

            foreach($occupiedBays as $departure){
                // dd($departure);
                
                // Grab all planned future slots for the Aircraft that has now entered an occupied bay
                $futureSlots = BayAllocations::where('status', 'PLANNED')->where('callsign', $departure['callsign_id'])->with('BayInfo')->get();
                foreach($futureSlots as $slots){

                    $data[] = $slots;

                    ## Check Slot Status & Update accordingly.
                    if($slots->bay_core == $departure['bay_core']){
                        // Nothing needed if correct bay, thank the lord himself - Update the slot to be a OCCUPIED slot instead :)
                        echo "OMG {{$departure['callsign']}} went to the correct bay!";

                        $slots->status = "OCCUPIED";
                        $slots->save();

                    } else {
                        ##### - Clear old assigned bay, and lookup if a aircraft was scheduled to be on the new bay the aircraft has arrived on.
                        echo "damn it, wrong bay {{$departure['callsign']}}<br>";

                        ##### - Delete the planned slot & clear the bay. Aircraft has not used it.
                        // Clear the bay status
                        $bay = $slots->BayInfo;
                        $bay->callsign = null;
                        $bay->status = null;
                        $bay->save();

                        // Delete the slot
                        $slots->delete();

                        ##### - Check that no other aircraft is planned via the bay this aircraft has parked on.
                        $conflictingSlot = BayAllocations::where('status', 'PLANNED')->where('bay_core', $departure['bay_core'])->get();
                        foreach($conflictingSlot as $slot){
                            $bay = BayConflicts::updateorCreate(['bay' => $slot['bay'], 'callsign' => $slot['callsign']]);
                        }
                    }
                }
            }


            // Loop through the reassignment aircraft. Waits for min 3 mins before checking
                // - Does conflict still exist (e.g. is bay occupied) - Yes, reassign | No, delete entry and continue.
                // - Set assigned bay to null, and


                $conflicts = BayConflicts::where('created_at', '<=', now()->subMinutes(4))->with('SlotInfo')->with('FlightInfo')->get();
                $info2 = [];
                foreach($conflicts as $conflict){

                    // Find all entries which are not the same as the Aircraft on the ground
                    $conflict_bay = BayAllocations::where('status', 'PLANNED')
                        ->where('bay', $conflict->bay)
                        ->with('FlightInfo')
                        ->first();

                    // Loop through each conflict
                    if($conflict_bay !== null){

                            // Time to generate the $cs file
                            $info2[$conflict_bay->FlightInfo->callsign] = [
                                'cs' => $conflict_bay->FlightInfo->callsign,
                                'cs_id' => $conflict_bay->FlightInfo->id,
                                'arr' => $conflict_bay->FlightInfo->arr,
                                'ac' => $conflict_bay->FlightInfo->ac,
                                'elt' => $conflict_bay->FlightInfo->elt,
                                'eibt' => $conflict_bay->FlightInfo->eibt,
                                'OLD_BAY' => $conflict_bay->BayInfo->bay,
                                'ac_model' => $conflict_bay->FlightInfo,
                            ];

                            $conflict_bay->delete();
                            $conflict->delete();
                    }
                }

                // dd($info2);

                foreach($info2 as $reschedule){
                    // dd($reschedule);

                    // Assign a bay to the Aircraft--
                    $initialAssignment = false;
                    $bay = $this->assignBay($info2, $aircraftJSON, $initialAssignment, $discordChannel);
                }
        }
        
        ############ 4. Generate New Slots for Aircraft that are yet to have one
        {
            $assignedBays = [];

            foreach($unscheduledArrivals as $cs){
                $initialAssignment = true;
                // Assign a bay to the Aircraft
                $bay = $this->assignBay($cs, $aircraftJSON, $initialAssignment, $discordChannel);

                // If assigning fails, skip and continue loop
                if ($bay === null) {
                    Log::channel('bays')->error("Failed to assign bay for {$cs['cs']}({$cs['ac']}) — skipping");
                    continue;
                }

                $assignedBays[] = [
                    'cs' => $cs,
                    'id' => $bay];
            }
        }

        

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
        ####### TO BE REWRITTEN
        // Needs to prioritise all Company Specific Bays over Non-Specific.
        // E.g. Priority 5 JST Bay trumps Priority 1 NULL Bay.


        // Need to also ensure that the system doesn't give a stupid bay before 
        $info = Flights::where('callsign', $cs)->first();

        $operator = substr($info->callsign, 0, 3); // Cuts off the Callsign
        // dd($operator);

        // Index the AC so it can 
        $aircraftIndex = null;

        foreach ($aircraftJSON as $index => $types) {
            if (in_array($info->ac, $types, true)) {
                $aircraftIndex = $index;
                break;
            }
        }

        echo $info->ac;

        $allowedGroups = array_slice($aircraftJSON, $aircraftIndex);


        $allowedTypes = array_values(array_unique(array_merge(...$allowedGroups)));

        if (!in_array($info->ac, $allowedTypes, true)) {
            Log::channel('aircraft')->error($info->ac . ' type does not exist');
            $ac = 'B738';
        } else {
            $ac = $info->ac;
        }

        // dd($priorityOrder);

        ### - Preferred Bay Assignment Check can go Here - Pull data from online sources?
            // - TBC in future building
        {

        }

        ##### - AIRCRAFT CASE EXPRESSION
        $aircraftPriorityParts = [];

        foreach ($allowedTypes as $i => $type) {
            $aircraftPriorityParts[] =
                "IF(FIND_IN_SET('$type', REPLACE(aircraft, '/', ',')) > 0, $i, -1)";
        }

        $aircraftPrioritySql = "GREATEST(" . implode(", ", $aircraftPriorityParts) . ")";

        // dd($aircraftPrioritySql);


        $availableBays = Bays::where('airport', $info->arr)
            ->whereNull('callsign')
            ->whereRaw("(pax_type = ? OR pax_type IS NULL OR pax_type = 'FRT')", [$info->type])

            ->orderByRaw("
                CASE
                    WHEN pax_type = ? THEN 1
                    WHEN pax_type IS NULL THEN 2
                    WHEN pax_type = 'FRT' THEN 3
                    ELSE 4
                END
            ", [$info->type])

            // Order by Bay Prioriies (1=most, 9=never?)
            ->orderBy('priority', 'asc')

            // Order bays by Aircraft Closeness to 
            ->where(function ($q) use ($allowedTypes) {
                foreach ($allowedTypes as $type) {
                    $q->orWhereRaw(
                        "aircraft REGEXP CONCAT('(^|/)', ?, '(/|$)')",
                        [$type]
                    );
                }
            })

            ->where(function ($q) use ($operator) {
                $q->whereRaw("FIND_IN_SET(?, REPLACE(operators, ' ', ''))", [$operator])
                ->orWhereNull('operators');
            })

            ->orderByRaw($aircraftPrioritySql)

            // Operator Order (QFA, QLK v QLK, QFA assignment priority)
            ->orderByRaw("
                CASE 
                    WHEN operators IS NULL THEN 4
                    ELSE FIND_IN_SET(?, REPLACE(operators, ' ', ''))
                END
            ", [$operator])

            ->orderByRaw("RAND()")
            
        ->get();
        // dd($availableBays)

        ####### - Oh No, The Harder Rule returned no options!!!!!!!  We need to find something, so lets do a relaxed version.......
        if($availableBays !== null){

        }

        // 
        $candidates = $availableBays->take(7);
        $selectedBay = $candidates->random();
        echo $availableBays."<br><br><br>";

        // Randomise selection within top 7 - Wamt it to be a bit random over time :)
        $selectedBay = $availableBays->first();

        return $selectedBay;
    }

    private function assignBay($cs, $aircraftJSON, $initial, $discordChannel)
    {

        $info = collect($cs);
        // dd($info);

        // dd($info['eibt']);

        try {
            $value = $this->selectBay($cs, $aircraftJSON);

            // dd($value);
            
            $eobt = $this->bayTimeCalcs($info['eibt']);
            $core = $this->bayCore($value->bay);

            // Find all bays to block off
            $findCoreBays = Bays::where('airport', $info['arr'])
                                ->whereRaw(
                                    'bay REGEXP ?',
                                    ['^' . $core . '(?!\\d)([A-Z])?$']
                                )->get();

            // dd($findCoreBays);

            // Scehdule each bay as blocked
            foreach($findCoreBays as $bayID){
                $newBay = BayAllocations::create([
                            'airport'   => $bayID['airport'],
                            'bay'       => $bayID['id'],
                            'bay_core'  => $core,
                            'callsign'  => $info['cs_id'],
                            'status'    => "PLANNED",
                            'eibt'      => $info['eibt'],
                            'eobt'      => $eobt,
                ]);

                $markBay = Bays::where('id', $bayID->id)->first();

                $markBay->status = 1;
                $markBay->callsign = $info['cs'];
                $markBay->save();
            }

            // dd($findCoreBays);

            // Record Scheduled Bay in Flights Table
            if($initial == true) {
                #### - Initial Bay Assignment
                // Update scheduled_bay to the assigned bay
                $aircraftBay = Flights::find($info['cs_id']);
                $aircraftBay->scheduled_bay = $value->id;
                $aircraftBay->save();


                // Send Discord Embed Message
                $discord = new DiscordClient();
                $discord->sendMessageWithEmbed($discordChannel, "Bay Assigned | ".$info['cs'].", ".$info['ac'], " ".$value->bay." inbound ".$bayID['airport']."\n\nEIBT ".Carbon::parse($info['eibt'])->format('Hi')."z", '27F58B');


                // Hoppie CPDLC Message
                $version = 1;
                $flight = $aircraftBay->callsign;
                $dep = $aircraftBay->dep;
                $arr = $aircraftBay->arr;
                $bayType = $aircraftBay->type;
                $arrBay = $value->bay;
                $telex = $this->HoppieFunction($version, $flight, $dep, $arr, $bayType, $arrBay);

            } else {
                // Update scheduled_bay to the assigned bay
                $aircraftBay = Flights::find($info['cs_id']);
                $aircraftBay->scheduled_bay = $value->id;
                $aircraftBay->save();


                // Send Discord Embed Message
                $discord = new DiscordClient();
                $discord->sendMessageWithEmbed($discordChannel, "Bay Re-Assignment | ".$info['cs'].", ".$info['ac'], " Bay ".$info['OLD_BAY'].' now occupied. Reassigning ACFT '.$value->bay." inbound ".$bayID['airport']."\n\nEIBT ".Carbon::parse($info['eibt'])->format('Hi')."z", 'fca503');


                // Hoppie CPDLC Message
                $version = 2;
                $flight = $aircraftBay->callsign;
                $dep = $aircraftBay->dep;
                $arr = $aircraftBay->arr;
                $bayType = $aircraftBay->type;
                $arrBay = $value->bay;
                $telex = $this->HoppieFunction($version, $flight, $dep, $arr, $bayType, $arrBay);
            }

            return $value;
        } catch (\Throwable $e) {
            Log::channel('bays')->error("assignBay() failed for {$cs['cs']}: {$e->getMessage()}");
            return null; // <-- This prevents the outer loop from crashing
        }
    }

    private function HoppieFunction($version, $flight, $dep, $arr, $bayType, $arrBay)
    {
        $hoppie = app(HoppieClient::class);

        $Uplink = $this->BuildCPDLCMessage($version, $flight, $dep, $arr, $bayType, $arrBay);

        if ($hoppie->isConnected($flight, $arr)) {
            if(env('HOPPIE_ACTIVE')  == "yes"){
                $hoppie->sendTelex($arr, $flight, $Uplink);

                $discord = new DiscordClient();
                $discord->sendMessageWithEmbed($discordChannel, $flight." | CPDLC UPLINK", $Uplink, '808080');
            }
        }
        
        return $Uplink;

    }

    private function BuildCPDLCMessage($version, $flight, $dep, $arr, $bayType, $arrBay): string
    {
        if($version == 1){
            // Initial 
            $messageLines = [
                "{$dep} ARRIVAL INFO \ ",
                "{$flight}, {$dep}-{$arr} \ ",
                "ARR BAY: {$bayType}, {$arrBay} \\ ",
                'IF UNABLE ADVISE GND FOR ALTN BAY ON FIRST CTC \ ',
                "RMK/ AUTO BAY ASSIGNMENT SENT FROM OZBAYS.XYZ \ ",
                "RMK/ ACK NOT REQUIRED WITH ATC",
                'END BAY UPLINK'
            ];

        } elseif($version == 2){
            // REVISED BAY ()
            $messageLines = [
                "{$dep} ARRIVAL UPDATE \ ",
                "{$flight}, {$dep}-{$arr} \ ",
                "ARR BAY: {$bayType}, {$arrBay} \ ",
                'IF UNABLE ADVISE GND FOR ALTN BAY ON FIRST CTC \ ',
                "RMK/ BAY CHANGED DUE OTHER AC ON ASSIGNED BAY \ ",
                "RMK/ ACK NOT REQUIRED",
                'END BAY UPLINK'
            ];
        }
        

        return implode("\n", $messageLines);
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
