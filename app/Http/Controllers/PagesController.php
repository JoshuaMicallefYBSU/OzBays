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
    public function Lander()
    {
        $flight = Flights::all();
        
        $ybbn = 0;
        $yssy = 0;
        $ymml = 0;
        $ypph = 0;

        foreach($flight as $f){
            if($f->arr == "YBBN" && $f->online !== null){
                $ybbn++;
            }
            if($f->arr == "YSSY" && $f->online !== null){
                $yssy++;
            }
            if($f->arr == "YMML" && $f->online !== null){
                $ymml++;
            }
            if($f->arr == "YPPH" && $f->online !== null){
                $ypph++;
            }
        }

        return view('lander', compact('ybbn', 'yssy', 'ymml', 'ypph'));
    }

    public function newHome()
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
}
