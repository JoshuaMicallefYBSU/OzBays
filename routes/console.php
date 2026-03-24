<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\AerodromeUpdates;
use App\Jobs\BayAllocation;
use App\Jobs\FlightData;
use App\Jobs\LiveBaysJob;

### MINUTE BY MINUTE UPDATES
// Flight Details
Schedule::job(new FlightData)->everyMinute();
Schedule::job(new BayAllocation)->everyMinute();


### HOURLY UPDATES
// Check Airport.JSON for any configuration updates
Schedule::job(new AerodromeUpdates)->cron('10 * * * *');
Schedule::job(new LiveBaysJob)->cron('5 * * * *');