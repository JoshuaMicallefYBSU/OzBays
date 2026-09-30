<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\Carbon;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\FlightLiveBays;
use App\Models\FlightLiveMissingBays;
use App\Services\AirLabsClient;

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
        // All data older than one week gets auto deleted at the beginning of the function. Then goes through each airport via the AIRLABS API and pulls all scheduled arrivals.
        // If it fails at all, then it skips that airport and tries again on the next update.

        // Load the Array Variable for when working this function
        $flight_data = [];

        // Load the Airlabs Client
        $airlabs = new AirLabsClient();

        // Find all airports that data needs to be updated in this check.
        $nowHour = Carbon::now()->hour;
        $airports = Airports::where('live_bays', 1)
            ->whereRaw('FIND_IN_SET(?, live_update_times)', [$nowHour])
            ->get();



        ###### Find the Live Flight information for each airport that is active, and should update this hour
        foreach($airports as $airport){
            $schedules = $airlabs->getAirportArrivals($airport->icao);

            if ($schedules === null) {
                continue;
            }

            // AirLabs returns codeshares as their own rows, pointing at the operating flight via cs_flight_iata.
            // Index the operating flights so a codeshare row without gate info can inherit it.
            $operating = [];
            foreach($schedules as $schedule){
                if (empty($schedule['cs_flight_iata']) && !empty($schedule['flight_iata'])) {
                    $operating[$schedule['flight_iata']] = $schedule;
                }
            }

            foreach($schedules as $schedule){

                $parent = !empty($schedule['cs_flight_iata']) ? ($operating[$schedule['cs_flight_iata']] ?? null) : null;

                $gate     = $schedule['arr_gate'] ?? $parent['arr_gate'] ?? null;
                $terminal = $schedule['arr_terminal'] ?? $parent['arr_terminal'] ?? null;
                $arrival  = $schedule['arr_icao'] ?? $airport->icao;

                // If there is no gate assignment, or no ICAO callsign, then skip adding the data (as it cant be used)
                if(empty($gate) || empty($schedule['flight_icao']) || empty($schedule['airline_icao'])) {
                    continue;
                }

                $flight_data[$arrival][] = [
                    'callsign'      =>  strtoupper($schedule['flight_icao']),
                    'aircraft'      =>  $schedule['aircraft_icao'] ?? null,
                    'operator'      =>  strtoupper($schedule['airline_icao']),
                    'flight_number' =>  $schedule['flight_number'] ?? null,
                    'arrival'       =>  $arrival,
                    'terminal'      =>  $terminal,
                    'gate'          =>  $gate,
                ];
            }
        }


        ###### BAY ALLOCATION CALCULATION - Lets make an entry, and also map it to an existing bay if it is found.
        foreach($flight_data as $airport_data){
            foreach($airport_data as $flight){

                $bay = $this->findBay($flight);

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

    // Match an IRL gate to an OzBays bay. Exact match first, then a bay with the same number and an
    // optional letter prefix/suffix (gate "4" -> "C4" or "4A", but never "B24"). If that finds more
    // than one bay the gate is ambiguous, so return null and let it be recorded as a missing bay.
    private function findBay(array $flight): ?Bays
    {
        $gate = strtoupper(trim((string) $flight['gate']));

        $bays = Bays::where('airport', $flight['arrival'])
            ->when($flight['terminal'], function ($q) use ($flight) {
                $q->where('terminal', 'LIKE', '%' . $flight['terminal'] . '%');
            })
            ->get();

        $exact = $bays->first(fn ($bay) => strtoupper($bay->bay) === $gate);
        if ($exact !== null) {
            return $exact;
        }

        $pattern = '/^[A-Z]*' . preg_quote($gate, '/') . '[A-Z]?$/';
        $matches = $bays->filter(fn ($bay) => preg_match($pattern, strtoupper($bay->bay)));

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
