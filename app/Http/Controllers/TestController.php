<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\FlightData;
use App\Jobs\AerodromeUpdates;

class TestController extends Controller
{
    public function Job()
    {
        // Dispatch the job
        $job = AerodromeUpdates::dispatch();

        // Call the handle method directly to get the result synchronously
        $result = $job->handle();

        return response()->json([
            'message' => 'Job executed successfully',
            'data' => $result,
        ]);
    }
}
