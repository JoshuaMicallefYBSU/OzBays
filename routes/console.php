<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\FlightData;

Schedule::job(new FlightData)->everyMinute();