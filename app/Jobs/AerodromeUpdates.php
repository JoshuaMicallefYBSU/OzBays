<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\Airports;
use App\Models\Bays;
use App\Services\AirportImporter;

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
        $importer = app(AirportImporter::class);

        // Grab every Airport JSON File (one file per airport)
        $files = File::glob(AirportImporter::directory().'/*.json');

        // Safety net - an empty/missing folder would otherwise delete every airport & bay
        if (empty($files)) {
            Log::error('AerodromeUpdates: no airport JSON files found in '.AirportImporter::directory().' - skipping update');
            return;
        }

        ### UPDATE THE AIRPORTS & BAYS
        // Set all airports & bays to not checked
        Airports::query()->update(['check_exist' => null]);
        Bays::query()->update(['check_exist' => null]);

        // Update/Create Airports & Bays from each file
        foreach($files as $file){
            $airport = json_decode(File::get($file), true);
            $errors = $importer->validate($airport);

            if (!empty($errors)) {
                // Don't let a broken file wipe the airport - keep whatever is already in the database for it
                $icao = strtoupper(pathinfo($file, PATHINFO_FILENAME));
                Airports::where('icao', $icao)->update(['check_exist' => 1]);
                Bays::where('airport', $icao)->update(['check_exist' => 1]);

                Log::error('AerodromeUpdates: skipped invalid file '.basename($file), $errors);
                continue;
            }

            $importer->import($airport);
        }

        // Delete Airports no longer in the JSON Files
        $deleteAirports = Airports::where('check_exist', null)->get();
        
        foreach($deleteAirports as $ap){
            $ap->delete();
        }

        // Delete Bays no longer in the JSON Files
        $deleteBays = Bays::where('check_exist', null)->get();
        foreach($deleteBays as $bay){
            $bay->delete();
        }
    }
}
