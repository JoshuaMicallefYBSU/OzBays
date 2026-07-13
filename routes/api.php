<?php

use App\Http\Controllers\APIController;
use App\Http\Controllers\ChangelogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/flights/live', [APIController::class, 'liveFlights']);
    Route::get('/bays/live', [APIController::class, 'liveBays']);
    Route::get('/ozstrips', [APIController::class, 'OzStrips']);
    Route::get('/ozstrips/cdm', [APIController::class, 'OzStripsCDM']);
});

// Webhook used by the discord-notify GitHub Action to record merged PRs
Route::post('/changelog', [ChangelogController::class, 'store'])->name('changelog.store');
