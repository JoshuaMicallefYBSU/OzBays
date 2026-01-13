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
use App\Models\Airline;

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
            $flights = Flights::where('online', 1)->orderBy('distance', 'desc')->get();
            $airports = Airports::all()->keyBy('icao');
            $bays = Bays::all();
            

            if(env('APP_DEBUG') == true){
                $discordChannel = config('services.discord.OzBays_Local');
            } else {
                $discordChannel = config('services.discord.OzBays');
            }

            $initialAssignment = false;
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

            // dd($flights);

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
                                    'bay_name'      => $acb->bay,
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
                        }

                    }
                }

                // Does an arrival aircraft require bay assignment?
                if((empty($ac->assignedBay) || $ac->assignedBay->isEmpty()) && $ac->speed > 80 &&$ac->status == "On Approach"){
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
                if ($bay->airport !== $flight->arr && $flight->arr !== null && $bay->status == 'PLANNED'  && $flight->id == $bay->callsign) {
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




        ############ 3. Check Planned Slots for Bay Conflicts & Reassignment
        {
            ### - ENR Aircraft wait 3mins before reassignment

            // Loop through each $occupiedBays and see if there are any slots that do not match the Aircraft
                // - If there are, we need to add the aircraft to the bay_conficts table, and later on do some reassignment.
            $data = [];

            foreach($occupiedBays as $departure){

                // dd($occupiedBays);
                
                $futureSlots = BayAllocations::where('status', 'PLANNED')->where('bay_core', $departure['bay_core'])->with('BayInfo')->get();
                if(empty($futureSlots) || $futureSlots->isEmpty()){
                    
                    // Aircraft has parked at bay that is either their scheduled, or a unscheduled bay
                    echo "No Planned Slots for ".$departure['airport'].", ".$departure['bay_name']. " where ". $departure['callsign'] ." is parked <br>";

                    // Check for any Old Planned Slots, and delete them if so
                    $oldSlots = BayAllocations::where('status', 'PLANNED')->where('callsign', $departure['callsign_id'])->get();
                    if(empty($oldSlots) || $oldSlots->isEmpty()){
                        // Don't need to do anything. They parked and do not have an arrival slot
                    } else {
                        echo " - Checked: Aicraft has arrived and parked on incorrect bay <br>";
                        foreach($oldSlots as $slots){
                            $slots->delete();
                        }
                        Log::channel('allocations')->error($departure['callsign']." has parked at an incorrect bay at ".$departure['airport']);
                    }

                } else {

                    // Bay Aircraft is parked at has planned aircraft in the database
                    echo "Planned Slots Exist on ".$departure['bay_name']." where ".$departure['callsign']." has spawned/parked <br>";

                    foreach($futureSlots as $slot){

                        if($slot['callsign'] == $departure['callsign_id']){
                            Echo " - Aircraft is parked on the correct bay! <br> ";

                            Log::channel('allocations')->error($departure['callsign']." parked correctly  at ".$departure['airport']."! Fuck yeah");

                            $slot->status = "OCCUPIED";
                            $slot->save();
                        } else {
                            Echo " - Aircraft has caused a conflict with an arrival aircraft! Add to be checked in 4 Minutes <br> ";
                            $data[] = $slot;
                            $bay = BayConflicts::updateorCreate(['bay' => $slot['bay'], 'callsign' => $slot['callsign']]);
                        }
                    }
                }
                // Search through all
            }

            // dd($data);


            // Loop through the reassignment aircraft. Waits for min 4 mins before checking
                // - Does conflict still exist (e.g. is bay occupied) - Yes, reassign | No, delete entry and continue.
                // - Set assigned bay to null, and


                $conflicts = BayConflicts::where('created_at', '<=', now()->subMinutes(4))->with('SlotInfo')->with('FlightInfo')->get();
                $info2 = [];
                foreach($conflicts as $conflict){

                    // dd($conflict);

                    // Find all entries which are not the same as the Aircraft on the ground
                    $conflict_bay = BayAllocations::where('status', 'PLANNED')
                        ->where('bay', $conflict->bay)
                        ->with('FlightInfo')
                        ->first();

                    // Loop through each conflict
                    if($conflict_bay !== null){

                            // Time to generate the $cs variable for the assignment Script
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
                    }
                }


                // dd($info2);
                echo "<br><br>";

                foreach($info2 as $reschedule){
                    // dd($reschedule);

                    // Find all potential bay conflicts - Change each bay to unscheduled, and then delete the slot & conflict.
                    $slots = BayAllocations::where('callsign', $reschedule['cs_id'])->get();
                    foreach ($slots as $slot){
                        // Delete the BayAllocations table
                        $slot->delete();

                        // Delete the BayConflicts Entry
                        $conflict = BayConflicts::where('bay', $slot['bay'])->first();
                        $conflict->delete();
                    }

                    // Assign a bay to the Aircraft--\
                    echo "4 Minutes has elapsed. Reassigning ENR Aircraft ".$reschedule['cs']." new bay";

                    $bay = $this->assignBay($reschedule, $aircraftJSON, 2, $discordChannel);
                    // dd($bay);

                    // dd($info2);
                }
        }
        
        ############ 4. Generate New Slots for Aircraft that are yet to have one
        {
            $assignedBays = [];

            foreach($unscheduledArrivals as $cs){
                // Assign a bay to the Aircraft
                $bay = $this->assignBay($cs, $aircraftJSON, 1, $discordChannel);

                // If assigning fails, skip and continue loop
                if ($bay === null) {
                    Log::channel('bays')->error("Failed to assign bay for {$cs['cs']}({$cs['ac']}) at {$cs['arr']} — skipping");
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
        // dd($unscheduledArrivals);
        }

    ###########################
    # PRIVATE FUNCTIONS - YOLO AND HOPE FOR A PRAYER BOIS THIS STUFF IS CONFUSING
    ###########################

    # Assign a bay to aircraft (Either Reassign or Initial)
    private function selectBay($cs, $aircraftJSON, $discordChannel)
    {
        ####### TO BE REWRITTEN
        // Needs to prioritise all Company Specific Bays over Non-Specific.
        // E.g. Priority 5 JST Bay trumps Priority 1 NULL Bay.


        // Need to also ensure that the system doesn't give a stupid bay before 
        $callsign = is_array($cs) ? ($cs['cs'] ?? null) : $cs;
        $info = $callsign !== null ? Flights::where('callsign', $callsign)->first() : null;

        if ($info === null) {
            return null;
        }

        $operator = substr($info->callsign, 0, 3); // Cuts off the Callsign
        // dd($operator);

        $isFreight = Airline::isFreightCallsign($info->callsign);

        // Index the AC so it can 
        $aircraftIndex = null;

        foreach ($aircraftJSON as $index => $types) {
            if (in_array($info->ac, $types, true)) {
                $aircraftIndex = $index;
                break;
            }
        }

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


        $availableBaysQuery = Bays::where('airport', $info->arr)
            ->whereNull('callsign')

            ->when(!$isFreight, function ($q) use ($info) {
                $q->whereRaw("(pax_type = ? OR pax_type IS NULL)", [$info->type]);
            })

            ->when($isFreight, function ($q) {
                $q->where('pax_type', 'FRT');
            }, function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('pax_type')->orWhere('pax_type', '!=', 'FRT');
                });
            })

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
            
        ;

        $availableBays = $availableBaysQuery->get();

        if ($isFreight && ($availableBays === null || $availableBays->isEmpty())) {
            $availableBays = Bays::where('airport', $info->arr)
                ->whereNull('callsign')
                ->whereRaw("(pax_type = ? OR pax_type IS NULL)", [$info->type])
                ->orderBy('priority', 'asc')
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
                ->where(function ($q2) {
                    $q2->whereNull('pax_type')->orWhere('pax_type', '!=', 'FRT');
                })
                ->orderByRaw($aircraftPrioritySql)
                ->orderByRaw("
                    CASE 
                        WHEN operators IS NULL THEN 4
                        ELSE FIND_IN_SET(?, REPLACE(operators, ' ', ''))
                    END
                ", [$operator])
                ->orderByRaw("RAND()")
            ->get();
        }
        // dd($availableBays)

        ####### - Oh No, The Harder Rule returned no options!!!!!!!  We need to find something, so lets do a relaxed version.......
        if($availableBays !== null){

        }

        // 
        $candidates = $availableBays->take(7);
        $selectedBay = $candidates->random();
        if (!app()->runningUnitTests()) {
            echo "Available bays for ".$cs['cs']."<br>";
            echo $availableBays."<br><br><br>";
        }

        // Randomise selection within top 7 - Wamt it to be a bit random over time :)
        $selectedBay = $availableBays->first();

        return $selectedBay;
    }

    private function assignBay($cs, $aircraftJSON, $initial, $discordChannel)
    {

        $info = collect($cs);
        // dd($cs);

        // dd($info['eibt']);

        try {
            $value = $this->selectBay($cs, $aircraftJSON, $discordChannel);

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

            // Record Scheduled Bay in Flights Table
            if($initial == 1) {
                #### - Initial Bay Assignment
                // Update scheduled_bay to the assigned bay
                $aircraftBay = Flights::find($info['cs_id']);
                $aircraftBay->scheduled_bay = $value->id;
                $aircraftBay->save();


                // Send Discord Embed Message
                $discord = new DiscordClient();
                $discord->sendMessageWithEmbed($discordChannel, "Bay Assigned | ".$info['cs'].", ".$info['ac'], " ".$value->bay." inbound ".$info['arr']."\n\nEIBT ".Carbon::parse($info['eibt'])->format('Hi')."z", '27F58B');


                // Hoppie CPDLC Message
                $version = 1;
                $flight = $aircraftBay->callsign;
                $cid = $aircraftBay->cid;
                $dep = $aircraftBay->dep;
                $arr = $aircraftBay->arr;
                $bayType = $aircraftBay->type;
                $arrBay = $value->bay;
                $telex = $this->HoppieFunction($version, $flight, $cid, $dep, $arr, $bayType, $arrBay, $discordChannel);

            } elseif($initial == 2) {
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
                $cid = $aircraftBay->cid;
                $dep = $aircraftBay->dep;
                $arr = $aircraftBay->arr;
                $bayType = $aircraftBay->type;
                $arrBay = $value->bay;
                $telex = $this->HoppieFunction($version, $flight, $cid, $dep, $arr, $bayType, $arrBay, $discordChannel);
            }

            return $value;
        } catch (\Throwable $e) {
            Log::channel('bays')->error("assignBay() failed for {$info['cs']}: {$e->getMessage()}");
            return null; // <-- This prevents the outer loop from crashing
        }
    }

    private function HoppieFunction($version, $flight, $cid, $dep, $arr, $bayType, $arrBay, $discordChannel)
    {
        // Those on the CPDLC Message
        $testers = [
            1342084, // Joshua
            1291605, // AJ
            1695019, // David
            1487719,  // Max
            1596254, // Jamie
            1750979, // Kyle
            1363418, // Corey
            1686135, // Alex B
            1687954, // Alex D
            1569950, // Nikola
            1773586, // Cruize
            1638887, // Max
            1252312, // Ben C
        ];

        $cid = (int) $cid;

        $hoppie = app(HoppieClient::class);
        $Uplink = $this->BuildCPDLCMessage($version, $flight, $dep, $arr, $bayType, $arrBay, $cid);

        $send_message = false;
        $arrival = Airports::where('icao', $arr)->first();

        // Only run the check on Production with an Active Airport
        if(env('APP_ENV')  == "production"){
            Log::channel('hoppie')->error("Attempting Hoppie Message for Flight ".$flight);

            if ($hoppie->isConnected($flight, $arr)) {
                if($arrival->status == "testing"){
                    Log::channel('hoppie')->error($flight." connected to the Hoppie Network & airport is in tester mode.");

                    // Messages to send during testing mode - Only those who are in the testing list
                    if(in_array($cid, $testers, true)){
                        $send_message = true;
                        Log::channel('hoppie')->error($flight." CID in tester list - Send it off!");   
                    } else {
                        $send_message = false;
                        Log::channel('hoppie')->error($flight." CID not in the tester list.");   
                    }
                } else {
                    Log::channel('hoppie')->error($flight." connected to the Hoppie Network in normal mode - Send the Message!");
                    // Messages to send during normal operation mode - Anyone connected
                    $send_message = true;
                }

                if($send_message == true){
                    $hoppie->sendTelex($arr, $flight, $Uplink);

                    $discord = new DiscordClient();
                    $discord->sendMessageWithEmbed($discordChannel, $flight." | CPDLC UPLINK", $Uplink, '808080');
                }
            } else {
                Log::channel('hoppie')->error($flight." not connected to Hoppie Network. Exiting.");
            }
        } else {
            echo "- Not on the Production Server: Skipping Hoppie Message";
        }
        
        return $Uplink;

    }

    private function BuildCPDLCMessage($version, $flight, $dep, $arr, $bayType, $arrBay, $cid): string
    {
        if ($version == 1) {
            $messageLines = [
                "{$arr} ARRIVAL INFO \\",
                "@{$flight}@, {$dep}-{$arr} \\",
                "ARR BAY: @{$bayType}, {$arrBay}@ \\",
                "IF UNABLE ADVISE GND FOR ALTN BAY ON FIRST CTC \\",
                "RMK/ AUTO BAY ASSIGNMENT SENT FROM OZBAYS.XYZ \\",
                "RMK/ ACK NOT REQUIRED WITH ATC",
                "END BAY UPLINK",
            ];
        } elseif ($version == 2) {
            $messageLines = [
                "{$arr} ARRIVAL UPDATE \\",
                "@{$flight}@, {$dep}-{$arr} \\",
                "ARR BAY: @{$bayType}, {$arrBay}@ \\",
                "IF UNABLE ADVISE GND FOR ALTN BAY ON FIRST CTC \\",
                "RMK/ BAY CHANGED DUE OTHER AC ON ASSIGNED BAY \\",
                "RMK/ ACK NOT REQUIRED WITH ATC",
                "END BAY UPLINK",
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