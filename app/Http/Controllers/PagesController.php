<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\ArrivalFlights;
use App\Models\Flights;

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
            if($f->arr == "YBBN"){
                $ybbn++;
            }
            if($f->arr == "YSSY"){
                $yssy++;
            }
            if($f->arr == "YMML"){
                $ymml++;
            }
            if($f->arr == "YPPH"){
                $ypph++;
            }
        }

        return view('welcome', compact('ybbn', 'yssy', 'ymml', 'ypph'));
    }
}
