<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Flights;
use App\Models\Bays;

class APIController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function liveFlights()
    {
        $flights = Flights::where('online', 1)->get();

        return $flights;
    }

    public function liveBays()
    {
        $bays = Bays::all();

        return $bays;
    }
}
