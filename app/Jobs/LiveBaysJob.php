<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Services\AeroAPIClient;

class LiveBaysJob implements ShouldQueue
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
        @set_time_limit(3000);
        @ini_set('max_execution_time', '3000');

        
        ###### Airport Live Bay Updater
        // This function grabs live flight bay assignments for specific airports, and saves that data for the next 24 hours. 
        // All data older than one week gets auto deleted at the beginning of the function. Then goes through each airport via the AIRLABS API and tries to pull the next 10hrs of data of data. 
        // If it fails at all, then it disables the live_bays airport entry, and stops using an API query to find the data.

        // Load the Array Variable for when working this function
        $flight_data = [];

        // Load the Airlabs Client
        $aeroapi = new AeroAPIClient();

        // Delete all flight data over one week in age. Look at the last_updated collum...

        // Find all airports that data needs to be updated in this check.
        $nowHour = Carbon::now()->hour;
        $airports = Airports::where('live_bays', 1)
            ->whereRaw('FIND_IN_SET(?, live_update_times)', [$nowHour])
            ->get();



        ###### Find the Live Flight information for each airport that is active, and should update this hour
        foreach($airports as $airport){
            $schedules = $aeroapi->getAirportSchedule($airport->icao, $airport->live_type);

            // dd($schedules);

            foreach($schedules['scheduled_arrivals'] as $schedule){

                // dd($schedule);

                // If there is no gate assignment, or the entry does not exist, then skip adding the data (as it cant be used)
                if($schedule['gate_destination']){
                    if($schedule['gate_destination'] == null) {
                        continue;
                    }
                } else {
                    continue;
                }

                $all_callsigns = [];

                // Add Official CAllsign to callsigns array
                $all_callsigns[] = [
                    'operator'    => $schedule['operator'],
                    'flight_number' => $schedule['flight_number'],
                ];

                // Find Each Callsign which needs to be included as a possible callsign a pilot could be flying in as
                foreach($schedule['codeshares'] as $callsign){

                    if (!preg_match('/^([A-Z]+)(\d+)$/', $callsign, $m)) {
                        continue;
                    }

                    $all_callsigns[] = [
                        'operator'    => $m[1],
                        'flight_number' => $m[2],
                    ];
                }

                // Loop through each Callsign Possibility, and create a new entry
                foreach($all_callsigns as $callsign){
                    $flight_data[$schedule['destination']['code_icao']][] = [
                        'callsign'      =>  $callsign['operator'].''.$callsign['flight_number'],
                        'operator'      =>  $callsign['operator'],
                        'flight_number' =>  $callsign['flight_number'],
                        'arrival'       =>  $schedule['destination']['code_icao'],
                        'terminal'      =>  $schedule['terminal_destination'],
                        'gate'          =>  $schedule['gate_destination'],
                    ];
                }
            }
        }


        ###### BAY ALLOCATION CALCULATION - Lets make an entry, and also map it to an existing bay if it is found.
        $bays = Bays::all();

        foreach($flight_data as $airport_data){
            foreach($airport_data as $flight){

                $bay = Bays::where('airport', $flight['arrival'])
                    ->where('terminal', 'LIKE', '%' . $flight['terminal'] . '%')
                    ->where('bay', 'LIKE', '%' . $flight['gate'] . '%')
                    ->first();
                
                FlightLiveBays::updateOrCreate(['callsign' => $flight['callsign']], [
                    'airport'       => $flight['arrival'],
                    'terminal'      => $flight['terminal'],
                    'gate'          => $flight['gate'],
                    'scheduled_bay' => $bay->id ?? null,
                ]);
            }
        }

        dd($flight_data);

    }
}
