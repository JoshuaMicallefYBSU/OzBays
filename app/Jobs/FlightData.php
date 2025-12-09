<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use App\Services\VATSIMClient;
use App\Services\DiscordClient;
use Carbon\Carbon;
use App\Models\Flights;
use Illuminate\Support\Facades\File;
use Exception;

class FlightData implements ShouldQueue
{
    use Queueable;

    public $timeout = 55;
    public $tries = 1;

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
        if(env('APP_DEBUG') == true){
            $this->update();
        } else {
            for ($i = 0; $i < 4; $i++) {

            $this->update();

            // Stop overlapping even if scheduler retries
            if ($i < 3) {
                sleep(15);
            }
        }
        }

    }

    private function update(): void
    {
        // Initialise some VARIABLES
        $vatsimData = new VATSIMClient();
        $pilots = $vatsimData->getPilots();

        // Bay Allocation Airports - Only These Flights get filtered
        $jsonPath = public_path('config/drome.json');
        $airports = json_decode(File::get($jsonPath), true);


        $arrivalAircraft = array_fill_keys(array_keys($airports), []);
        $OnGround = [];

        foreach($pilots as $pilot){

            // Check each Aircraft (See if they are on the ground)
            $distanceToYBBN = $this->calculateDistance($pilot->latitude, $pilot->longitude, $airports['YBBN']['lat'], $airports['YBBN']['lon']);
            $distanceToYSSY = $this->calculateDistance($pilot->latitude, $pilot->longitude, $airports['YSSY']['lat'], $airports['YSSY']['lon']);
            $distanceToYMML = $this->calculateDistance($pilot->latitude, $pilot->longitude, $airports['YMML']['lat'], $airports['YMML']['lon']);
            $distanceToYPPH = $this->calculateDistance($pilot->latitude, $pilot->longitude, $airports['YPPH']['lat'], $airports['YPPH']['lon']);

            // Check if Aircraft is on the Ground - Either Departing from the Airport, or 
            if($distanceToYBBN < 3 || $distanceToYSSY < 3 || $distanceToYMML < 3 || $distanceToYPPH <3 && $pilot->groundspeed < 80){
                $OnGround[] = [
                    'callsign'  => $pilot->callsign,
                    'cid'       => $pilot->cid,
                    'hdg'       => $pilot->heading,
                    'dep'       => $pilot->flight_plan->departure ?? null,
                    'arr'       => $pilot->flight_plan->arrival ?? null,
                    'ac'        => $pilot->flight_plan->aircraft_short ?? null,
                    'lat'       => $pilot->latitude,
                    'lon'       => $pilot->longitude,
                    'speed'     => $pilot->groundspeed,
                    'online'    => 1,
                ];
            }

            // Check FP - Changes what is done
            if($pilot->flight_plan == null){
                continue;
            }

            // Flight Scheduled at a ozBays Airport
            if(in_array($pilot->flight_plan->arrival, array_column($airports, 'icao'), true)){

                // Calculate distance from Airport
                $distanceToArrival = $this->calculateDistance($pilot->latitude, $pilot->longitude, $airports[$pilot->flight_plan->arrival]['lat'], $airports[$pilot->flight_plan->arrival]['lon']);
                

                // Do not interest yourself in Aircraft > 500NM from the Airport oh little one
                if($distanceToArrival > 600){
                    continue;
                }

                // Calculate Landing and Block Time (Estimates)
                if ($pilot->groundspeed <= 0) {
                    $eibt = null;
                    $elt = null;
                } else {
                    $TimeRemaining = ($distanceToArrival / $pilot->groundspeed) * 60;

                    $elt = Carbon::now('UTC')->addMinutes((int) round($TimeRemaining)); //Adds time for slowdown during descent

                    $eibt = Carbon::now('UTC')->addMinutes((int) round($TimeRemaining) + 10); //Adds further time for taxi to the bay - This is the time the bay is considered 'blocked' from.
                }

                // Status Calculation
                if($pilot->groundspeed < 80 && $distanceToArrival > 3 && $pilot->altitude < 4500){
                    $status = 'Departing another Airport';
                }elseif($pilot->groundspeed < 80 && $distanceToArrival > 3){
                    $status = 'Paused';
                } elseif($pilot->groundspeed < 80 && $distanceToArrival <= 3){
                    $status = 'Arrived';
                } elseif($pilot->groundspeed > 80 && $distanceToArrival < 20){
                    $status = 'Final Approach';
                } elseif($pilot->groundspeed > 80 && $distanceToArrival >= 20){
                    $status = 'Inbound';
                } else {
                    $status = null;
                }

                if (str_starts_with($pilot->flight_plan->departure, 'Y')) {
                    $type = 1;
                } else {
                    $type = 2;
                }


                // Collate the Data
                $arrivalAircraft[$pilot->flight_plan->arrival][] = [
                    'cid'       => $pilot->cid,
                    'callsign'  => $pilot->callsign,
                    'dep'       => $pilot->flight_plan->departure,
                    'arr'       => $pilot->flight_plan->arrival,
                    'ac'        => $pilot->flight_plan->aircraft_short,
                    'hdg'       => $pilot->heading,
                    'type'      => $type,
                    'lat'       => $pilot->latitude,
                    'lon'       => $pilot->longitude,
                    'speed'     => $pilot->groundspeed,
                    'distance'  => round($distanceToArrival),
                    'elt'       => $elt,
                    'eibt'      => $eibt,
                    'status'    => $status,
                ];
            }
        }

        // Set all flights to offline (gets reupdated below)
        $all_flights = Flights::where('online', 1)->get();
        foreach($all_flights as $fl){
            $fl->online = null;
            $fl->save();
        }

        // Update the Entries in the Database
        foreach ($OnGround as $aa) {
                Flights::updateOrCreate(['callsign' => $aa['callsign']], [
                    'id'        => $aa['cid'],
                    'hdg'       => $aa['hdg'],
                    'ac'      => $aa['ac'],
                    'lat'       => $aa['lat'],
                    'lon'       => $aa['lon'],
                    'speed'     => $aa['speed'],
                    'online'    => 1,
                ]);
        }

        // Update the Entries in the Database
        foreach ($arrivalAircraft as $airportIcao => $aircraftList) {
            foreach ($aircraftList as $ac) {

                Flights::updateOrCreate(['callsign' => $ac['callsign']], [
                    'id'   => $ac['cid'],
                    'dep'  => $ac['dep'],
                    'ac'   => $ac['ac'],
                    'type' => $type,
                    'arr'  => $ac['arr'],
                    'hdg'  => $ac['hdg'],
                    'lat'  => $ac['lat'],
                    'lon'  => $ac['lon'],
                    'speed'  => $ac['speed'],
                    'distance'  => $ac['distance'],
                    'elt'  => $ac['elt'],
                    'eibt'  => $ac['eibt'],
                    'status'  => $ac['status'],
                    'online'    => 1,
                ]);
            }
        }

        Log::info('FlightData result', $arrivalAircraft);

        dd($arrivalAircraft);
        // dd($OnGround);

    }

    // Calculate thistance
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
}


