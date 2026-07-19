<?php

use App\Http\Controllers\AirportsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataManagementController;
use App\Http\Controllers\DiscordController;
use App\Http\Controllers\FlightDisplayController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PartialsController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

// New Homepage
Route::get('/', [PagesController::class, 'Home'])->name('home');

// Error Messages
Route::get('/404', function () {
    abort(404);
});
Route::get('/500', function () {
    abort(500);
});

// Privacy Policy - Required for VATSIM SSO
Route::prefix('policy')->group(function () {
    Route::get('privacy', [PagesController::class, 'PrivacyPolicy'])->name('privacy.policy');
});

// Changelog
Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog.index');

// Airport Arrival Ladders
Route::get('/airports', [AirportsController::class, 'index'])->name('airportIndex');
Route::get('/airports/{icao}/flights/{callsign}/display', [FlightDisplayController::class, 'show'])->name('airport.flight-display');
Route::get('/airports/{icao}', [AirportsController::class, 'airportLadder'])->name('airportLadder');

// Maps
Route::get('/map', [MapController::class, 'index'])->name('mapIndex');
Route::get('/map/embed', [MapController::class, 'embed'])->name('mapEmbed');
Route::get('/map/{icao}', [MapController::class, 'airportMap']);

// News Articles
Route::get('/news', [NewsController::class, 'list'])->name('news.index');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

// Administration Actions
Route::prefix('admin')->group(function () {

    Route::prefix('data')->middleware(['auth', 'can:view data'])->group(function () {
        Route::get('/', [DataManagementController::class, 'index'])->name('dashboard.admin.data.index');
        Route::get('/import', [DataManagementController::class, 'import'])->name('dashboard.admin.data.import');
        Route::post('/import', [DataManagementController::class, 'storeImport'])->name('dashboard.admin.data.import.store');
        Route::get('/changes/{change}', [DataManagementController::class, 'show'])->name('dashboard.admin.data.show');
        Route::post('/changes/{change}/approve', [DataManagementController::class, 'approve'])->middleware('can:approve changes')->name('dashboard.admin.data.approve');
        Route::post('/changes/{change}/reject', [DataManagementController::class, 'reject'])->middleware('can:approve changes')->name('dashboard.admin.data.reject');
    });

    // Airport Information
    Route::middleware(['auth', 'can:view data'])->group(function () {
        Route::get('airport', [DashboardController::class, 'airportList'])->name('dashboard.admin.airport.all');
        Route::get('airport/{icao}', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.view');
        Route::get('airport/{icao}/{bay}', [DashboardController::class, 'bayView'])->name('dashboard.admin.bay.view');
    });
    Route::middleware(['auth', 'can:update status'])->group(function () {
        Route::post('airport/live-disable', [DashboardController::class, 'disableLiveAirport'])->name('dashboard.admin.airport.live-disable');
        Route::post('airport/live-activate', [DashboardController::class, 'activateLiveAirport'])->name('dashboard.admin.airport.live-activate');
        Route::post('airport/disable', [DashboardController::class, 'disableAirport'])->name('dashboard.admin.airport.disable');
        Route::post('airport/activate', [DashboardController::class, 'activateAirport'])->name('dashboard.admin.airport.activate');
    });
    // Route::post('airport/{icao}/update', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.update');
    // Route::post('airport/{icao}/approve', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.approve.change');

    // User Information
    Route::middleware(['auth', 'can:view users'])->group(function () {
        Route::get('users', [DashboardController::class, 'userList'])->name('dashboard.admin.users.list');
    });
    Route::middleware(['auth', 'can:view user data'])->group(function () {
        Route::get('users/{user}', [DashboardController::class, 'userView'])->name('dashboard.admin.users.view');
    });
    Route::middleware(['auth', 'can:edit user data'])->group(function () {
        Route::post('users/{user}/roles', [DashboardController::class, 'userAssignRole'])->name('dashboard.admin.users.roles.assign');
        Route::delete('users/{user}/roles/{role}', [DashboardController::class, 'userRemoveRole'])->name('dashboard.admin.users.roles.remove');
    });

    // Aircraft Information
    Route::get('aircraft', [DashboardController::class, 'aircraftList'])->middleware(['auth', 'can:view data'])->name('dashboard.admin.aircraft.all');
});

// News Administration
Route::prefix('admin/news')->middleware(['auth', 'can:manage news'])->group(function () {
    Route::get('/', [NewsController::class, 'index'])->name('dashboard.admin.news.index');
    Route::get('/create', [NewsController::class, 'create'])->name('dashboard.admin.news.create');
    Route::post('/', [NewsController::class, 'store'])->name('dashboard.admin.news.store');
    Route::get('/{news}/edit', [NewsController::class, 'edit'])->name('dashboard.admin.news.edit');
    Route::put('/{news}', [NewsController::class, 'update'])->name('dashboard.admin.news.update');
    Route::delete('/{news}', [NewsController::class, 'destroy'])->name('dashboard.admin.news.destroy');
});

// Notification Administration
Route::prefix('admin/notifications')->middleware(['auth', 'can:send notifications'])->group(function () {
    Route::get('/', [NotificationController::class, 'adminIndex'])->name('dashboard.admin.notifications.index');
    Route::get('/create', [NotificationController::class, 'create'])->name('dashboard.admin.notifications.create');
    Route::post('/', [NotificationController::class, 'store'])->name('dashboard.admin.notifications.store');
    Route::get('/recipients', [NotificationController::class, 'recipients'])->name('dashboard.admin.notifications.recipients');
});

// Dashboard
Route::prefix('dashboard')->middleware('auth')->group(function () {
    Route::get('', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/my-settings', [DashboardController::class, 'settingsView'])->name('dashboard.settings.index');
    Route::post('/my-settings/save', [DashboardController::class, 'settingsSave'])->name('dashboard.settings.save');

    // Discord Linking
    Route::get('/discord/unlink', [DiscordController::class, 'unlinkDiscord'])->name('dashboard.discord.unlink');
    Route::get('/discord/link/callback', [DiscordController::class, 'linkCallbackDiscord'])->name('dashboard.discord.link.callback');
    Route::get('/discord/link', [DiscordController::class, 'linkRedirectDiscord'])->name('dashboard.discord.link');
    Route::get('/discord/server/join', [DiscordController::class, 'joinRedirectDiscord'])->name('dashboard.discord.join');
    Route::get('/discord/server/join/callback', [DiscordController::class, 'joinCallbackDiscord']);
});

// Notifications
Route::prefix('notifications')->middleware('auth')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::post('/{id}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
    Route::get('/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/{id}', [NotificationController::class, 'show'])->name('notifications.show');
});

// Updates
Route::get('/update/airports', [PagesController::class, 'AirportUpdate'])->middleware(['auth', 'can:approve changes'])->name('airportsupdate');
Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi'); // Local Running Only - environment-gated in the controller

// ## Authentication Section - VATSIM SSO :)
// Authentication
Route::prefix('auth')->group(function () {
    Route::get('/sso/login', fn () => redirect(route('auth.connect.login'), 301))->middleware('guest')->name('auth.sso.login');
    Route::get('/connect/login', [AuthController::class, 'connectLogin'])->middleware('guest')->name('auth.connect.login');
    Route::get('/connect/validate', [AuthController::class, 'validateConnectLogin'])->middleware('guest');
    Route::get('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');
});

// PARTIALS SECTIONS
Route::prefix('partial')->group(function () {
    Route::get('/airport/ladder/{icao}', [PartialsController::class, 'updateLadder'])->name('airportLadderPartial');
    Route::get('/airports/{icao}/flights/{callsign}/display', [FlightDisplayController::class, 'partial'])->name('airport.flight-display.partial');
    Route::get('/dashboard/flight-info', [PartialsController::class, 'updateFlights']);
    Route::get('/home/airport-stats', [PartialsController::class, 'updateAirportStats']);
    Route::get('/notifications', [PartialsController::class, 'updateNotifications'])->middleware('auth');

});
