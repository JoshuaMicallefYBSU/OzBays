<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\AirportsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscordController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PartialsController;
use App\Http\Controllers\TestController;


// New Homepage
Route::get('/', [PagesController::class, 'Home'])->name('home');

// Privacy Policy - Required for VATSIM SSO
Route::prefix('policy')->group(function () {
    Route::get('privacy', [PagesController::class, 'PrivacyPolicy'])->name('privacy.policy');
});

// Airport Arrival Ladders
Route::get('/airports', [AirportsController::class, 'index'])->name('airportIndex');
Route::get('/airports/{icao}', [AirportsController::class, 'airportLadder'])->name('airportLadder');

// Maps
Route::get('/map', [MapController::class, 'index'])->name('mapIndex');
Route::get('/map/{icao}', [MapController::class, 'airportMap']);

// Dashboard
Route::prefix('dashboard')->middleware('auth')->group(function () {
    Route::get('', [DashboardController::class, 'index'])->name('dashboard.index');
    
    // Discord Linking
    Route::get('/discord/unlink', [DiscordController::class, 'unlinkDiscord'])->name('dashboard.discord.unlink');
    Route::get('/discord/link/callback', [DiscordController::class, 'linkCallbackDiscord'])->name('dashboard.discord.link.callback');
    Route::get('/discord/link', [DiscordController::class, 'linkRedirectDiscord'])->name('dashboard.discord.link');
    Route::get('/discord/server/join', [DiscordController::class, 'joinRedirectDiscord'])->name('dashboard.discord.join');
    Route::get('/discord/server/join/callback', [DiscordController::class, 'joinCallbackDiscord']);

    // Administration Actions
    Route::prefix('admin')->group(function () {

        // Airport Information
        Route::get('airport', [DashboardController::class, 'airportList'])->name('dashboard.admin.airport.all');
        Route::get('airport/{icao}', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.view');
        Route::get('airport/{icao}/{bay}', [DashboardController::class, 'bayView'])->name('dashboard.admin.bay.view');
        Route::post('airport/disable', [DashboardController::class, 'disableAirport'])->name('dashboard.admin.airport.disable');
        // Route::post('airport/{icao}/update', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.update');
        // Route::post('airport/{icao}/approve', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.approve.change');
    });
});

// Updates
Route::get('/update/airports', [PagesController::class, 'AirportUpdate'])->name('airportsupdate');
Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi'); // Local Running Only


### Authentication Section - VATSIM SSO :)
// Authentication
Route::prefix('auth')->group(function () {
    Route::get('/sso/login', fn() => redirect(route('auth.connect.login'), 301))->middleware('guest')->name('auth.sso.login');
    Route::get('/connect/login', [AuthController::class, 'connectLogin'])->middleware('guest')->name('auth.connect.login');
    Route::get('/connect/validate', [AuthController::class, 'validateConnectLogin'])->middleware('guest');
    Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');
});


// PARTIALS SECTIONS
Route::prefix('partial')->group(function () {
    Route::get('/airport/ladder/{icao}', [PartialsController::class, 'updateLadder']);
    Route::get('/dashboard/flight-info', [PartialsController::class, 'updateFlights']);
});


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