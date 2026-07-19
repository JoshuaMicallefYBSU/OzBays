<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Jobs\FlightData;
use App\Jobs\BayAllocation;
use App\Jobs\AerodromeUpdates;

class TestController extends Controller
{
    public function Job()
    {
        // Local development helper only - never available in production
        abort_unless(app()->environment('local'), 404);

        // dispatchSync runs each job exactly once, inline. Calling handle()
        // on a dispatch()ed job used to run it twice (sync + queued copy).
        AerodromeUpdates::dispatchSync();
        FlightData::dispatchSync();
        BayAllocation::dispatchSync();

        return response()->json([
            'message' => 'Job executed successfully'
        ]);
    }
}
