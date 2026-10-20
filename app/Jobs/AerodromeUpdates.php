<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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

        ### UPDATE THE AIRPORTS & BAYS
        // Grab every Airport JSON File uploaded to the site (one file per airport).
        // Airports without a file are left as they are in the database.
        $files = File::glob(AirportImporter::directory().'/*.json');

        foreach($files as $file){
            $airport = json_decode(File::get($file), true);
            $errors = $importer->validate($airport);

            // Don't touch the airport if its file is broken - keep whatever is already in the database
            if (!empty($errors)) {
                Log::error('AerodromeUpdates: skipped invalid file '.basename($file), $errors);
                continue;
            }

            $importer->import($airport);
        }
    }
}
