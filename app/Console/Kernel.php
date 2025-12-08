<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ArrivalFlights;


Schedule::job(new ArrivalFlights)->everyMinute();