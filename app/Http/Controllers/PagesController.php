<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\FlightData;
use App\Models\Flights;
use App\Models\NewsArticle;
use App\Services\HoppieClient;
use App\Jobs\AerodromeUpdates;

class PagesController extends Controller
{
    public function Home()
    {
        $news = NewsArticle::latest('id')->take(3)->get();

        return view('home', compact('news'));
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
