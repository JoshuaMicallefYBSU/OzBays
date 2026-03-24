<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\FlightData;
use App\Models\Flights;
use App\Services\HoppieClient;
use App\Jobs\AerodromeUpdates;

class PagesController extends Controller
{
    public function Home()
    {
        return view('home');
    }

    public function AirportUpdate()
    {
        $job = AerodromeUpdates::dispatch();
        $result = $job->handle();
        return response()->json([
            'message' => 'Job executed successfully',
            'data' => $result,
        ]);
    }

    public function Logs()
    {
        return view('logs');
    }

        public function PrivacyPolicy()
    {
        return view('privacy-policy');
    }
}
