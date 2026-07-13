<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use App\Models\Airports;
use App\Models\Bays;

class AerodromeUpdates implements ShouldQueue
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
        // Grab the Airports.JSON File
        $jsonPath = public_path('config/airport.json');
        $rawJson = json_decode(File::get($jsonPath), true);
        $airports = $rawJson['Airports'] ?? [];

        // dd($airports);

        ### UPDATE THE AIRPORTS & BAYS
        // Set all airports to not checked
        $allAirports = Airports::all();
        foreach($allAirports as $ap){
            $ap->check_exist = null;
            $ap->save();
        }

        // Set all bays to not checked
        $allBays = Bays::all();
        foreach($allBays as $bay){
            $bay->check_exist = null;
            $bay->save();
        }

        // Update/Create new Bays
        foreach($airports as $airport){
            $icao = $airport['icao'];

            Airports::updateOrCreate(['icao' => $icao], [
                'lat' => $airport['lat'],
                'lon' => $airport['lon'],
                'name' => $airport['name'],
                'color' => $airport['settings']['color'] ?? 'purple',
                'status' => $airport['settings']['status'] ?? 'disabled',
                'eibt_variable' => $airport['settings']['eibt_config'] ?? 1.4,
                'taxi_time' => $airport['settings']['taxi_time'] ?? 15,
                'live_type' => $airport['settings']['airport_type'] ?? null,
                'live_update_times' => $airport['settings']['update_time_utc'] ?? '0,2,4,6,8,10,12,14,20,22,24',
                'check_exist' => 1,
            ]);

            foreach($airport['parking'] as $bayCode => $bay){
                Bays::updateOrCreate(['airport' => $airport['icao'], 'bay' => $bayCode], [
                    'lat'       => $bay['lat'],
                    'lon'       => $bay['lon'],
                    'aircraft'  => $bay['AC'],
                    'pax_type'      => $bay['Type'],
                    'operators'  => $bay['Operator'],
                    'priority'  => $bay['Priority'],
                    'lat'       => $bay['lat'],
                    'check_exist'   => 1,
                    'terminal'  => $bay['Terminal'],
                ]);   
            }
        }

        // Delete Airports no longer in the JSON File
        $deleteAirports = Airports::where('check_exist', null)->get();
        foreach($deleteAirports as $ap){
            $ap->delete();
        }

        // Delete Bays no longer in the JSON File
        $deleteBays = Bays::where('check_exist', null)->get();
        foreach($deleteBays as $bay){
            $bay->delete();
        }
    }
}
