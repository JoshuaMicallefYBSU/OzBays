<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AirportsController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\MapController;


Route::get('/', [PagesController::class, 'lander'])->name('lander');

// New Homepage
Route::get('/home', [PagesController::class, 'newHome'])->name('home');

// Airport Arrival Ladders
Route::get('/airport', [AirportsController::class, 'index'])->name('airportIndex');
Route::get('/airport/{icao}', [AirportsController::class, 'airportLadder'])->name('airportLadder');

// Maps
Route::get('/map', [MapController::class, 'index']);
Route::get('/map/{icao}', [MapController::class, 'airportMap']);

// Updates
Route::get('/update/airports', [PagesController::class, 'AirportUpdate'])->name('airportsupdate');
Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi'); // Local Running Only



// Error File Checks
Route::get('/logs', [PagesController::class, 'logs']);
Route::get('/logs/aircraft', function () {
    $path = storage_path('logs/aircraft.log');

    if (!file_exists($path)) {
        abort(404, 'Log file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});
Route::get('/logs/allocations', function () {
    $path = storage_path('logs/allocations.log');

    if (!file_exists($path)) {
        abort(404, 'Log file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});
Route::get('/logs/bays', function () {
    $path = storage_path('logs/bays.log');

    if (!file_exists($path)) {
        abort(404, 'Log file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});
Route::get('/logs/hoppie', function () {
    $path = storage_path('logs/hoppie.log');

    if (!file_exists($path)) {
        abort(404, 'Log file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});
Route::get('/logs/laravel', function () {
    $path = storage_path('logs/laravel.log');

    if (!file_exists($path)) {
        abort(404, 'Log file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});