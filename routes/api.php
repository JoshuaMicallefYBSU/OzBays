<?php

use App\Http\Controllers\APIController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/flights/live', [APIController::class, 'liveFlights']);
    Route::get('/bays/live', [APIController::class, 'liveBays']);
     Route::get('/ozstrips', [APIController::class, 'OzStrips']);

});
