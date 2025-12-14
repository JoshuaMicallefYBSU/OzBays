<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\FlightData;
use App\Models\Flights;
use App\Services\HoppieClient;

class PagesController extends Controller
{
    public function Home()
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

        return view('welcome', compact('ybbn', 'yssy', 'ymml', 'ypph'));
    }

    public function Hoppie()
    {
        $hoppie = app(HoppieClient::class);

        if ($hoppie->isConnected($flight->callsign)) {
            $hoppie->sendTelex(
                'YSSY',
                $flight->callsign,
                'GATE ASSIGNED E11'
            );
        }

    }

    public function Logs()
    {
        return view('logs');
    }
}
