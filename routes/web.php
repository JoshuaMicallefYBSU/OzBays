<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\MapController;


Route::get('/', [PagesController::class, 'Home'])->name('home');

Route::get('/map', [MapController::class, 'index']);

Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi');;






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

Route::get('/logs/bays', function () {
    $path = storage_path('logs/bays.log');

    if (!file_exists($path)) {
        abort(404, 'Log file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'text/plain',
    ]);
});