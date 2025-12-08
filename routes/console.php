<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\ArrivalFlights;

Schedule::job(new ArrivalFlights)->everyMinute();