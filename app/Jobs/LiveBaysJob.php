<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\FlightLiveMissingBays;
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
                    if($schedule['gate_destination'] == null || $schedule['destination']['code_icao'] == null && $schedule['terminal_destination']) {
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
                        'aircraft'      =>  $schedule['aircraft_type'] ?? null,
                        'operator'      =>  $callsign['operator'],
                        'flight_number' =>  $callsign['flight_number'],
                        'arrival'       =>  $schedule['destination']['code_icao'],
                        'terminal'      =>  $schedule['terminal_destination'],
                        'gate'          =>  $schedule['gate_destination'],
                    ];
                }
            }

            sleep(61);
        }


        ###### BAY ALLOCATION CALCULATION - Lets make an entry, and also map it to an existing bay if it is found.
        $bays = Bays::all();

        foreach($flight_data as $airport_data){
            foreach($airport_data as $flight){

                $bay = $this->matchBay($flight['arrival'], $flight['terminal'], $flight['gate']);

                if($bay == null){
                    
                    // Add to missing bay database
                    FlightLiveMissingBays::firstOrCreate(['airport' => $flight['arrival'], 'terminal' => $flight['terminal'], 'bay' => $flight['gate']],
                    [
                        'aircraft' => $flight['aircraft'],
                        'callsign' => $flight['callsign'],
                    ]);
                } else {

                    // Bay has been found! Lets add it to the database
                    FlightLiveBays::updateOrCreate(['callsign' => $flight['callsign']], [
                        'airport'       => $flight['arrival'],
                        'terminal'      => $flight['terminal'],
                        'gate'          => $flight['gate'],
                        'scheduled_bay' => $bay->id,
                    ]);
                }
            }
        }


        ####### Delete all flight entries over 72 hours old.
        $old_slots = FlightLiveBays::where('updated_at', '<', Carbon::now()->subDays(3))->get();
        foreach($old_slots as $os){
            $os->delete();
        }

    }

    /**
     * Map a live feed terminal/gate pair to an OzBays bay record.
     *
     * Matches on the exact bay name only. The old substring LIKE match
     * ("%4%") could link a gate to any unrelated bay containing the same
     * digit - this is how C4 at YMML ended up pointing at B24.
     */
    public function matchBay(string $airport, ?string $terminal, ?string $gate): ?Bays
    {
        $gate = strtoupper(trim((string) $gate));
        $terminal = trim((string) $terminal);

        if ($gate === '') {
            return null;
        }

        $bayQuery = Bays::where('airport', $airport)
            ->whereRaw('UPPER(bay) = ?', [$gate]);

        if ($terminal !== '') {
            $bayQuery->where(function ($q) use ($terminal) {
                $q->where('terminal', 'LIKE', '%' . $terminal . '%')
                    ->orWhereNull('terminal');
            });
        }

        $bay = $bayQuery->first();

        // Some feeds split "C4" into terminal "C" + gate "4" - try the
        // terminal-prefixed name before treating the bay as missing.
        if ($bay === null && $terminal !== '') {
            $bay = Bays::where('airport', $airport)
                ->whereRaw('UPPER(bay) = ?', [strtoupper($terminal) . $gate])
                ->first();
        }

        return $bay;
    }
}
