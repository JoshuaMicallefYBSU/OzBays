<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\HoppieSend;
use App\Jobs\FlightData;
use App\Jobs\BayAllocation;
use App\Jobs\AerodromeUpdates;
use App\Jobs\LiveBaysJob;

class TestController extends Controller
{
    public function Job()
    {
        // Dispatch the job
        $job = AerodromeUpdates::dispatch();
        $job2 = FlightData::dispatch();
        $job3 = BayAllocation::dispatch();
        $job4 = LiveBaysJob::dispatch();

        // Call the handle method directly to get the result synchronously
        // $result = $job->handle();
        $result2 = $job2->handle();
        // $result3 = $job3->handle();
        // $result4 = $job4->handle();

        return response()->json([
            'message' => 'Job executed successfully',
            'data' => $result,
        ]);
    }
}
