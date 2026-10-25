<?php

use App\Http\Controllers\AirportsController;
use App\Http\Controllers\AssignmentLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ContributorApplicationController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscordController;
use App\Http\Controllers\FlightDisplayController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PartialsController;
use App\Http\Controllers\TeamController;
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
    Route::get('credits', [PagesController::class, 'Credits'])->name('policy.credits');
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

// Team
Route::get('/team', [TeamController::class, 'index'])->name('team.index');

// News Articles
Route::get('/news', [NewsController::class, 'list'])->name('news.index');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

// Administration Actions
Route::prefix('admin')->group(function () {

    // Airport Information
    Route::get('airport', [DashboardController::class, 'airportList'])->name('dashboard.admin.airport.all');
    Route::middleware(['auth', 'can:import airports'])->group(function () {
        Route::get('airport/import', [DashboardController::class, 'airportImport'])->name('dashboard.admin.airport.import');
        Route::post('airport/import', [DashboardController::class, 'airportImportStore'])->name('dashboard.admin.airport.import.store');
        Route::get('airport/import/{token}', [DashboardController::class, 'airportImportReview'])->name('dashboard.admin.airport.import.review');
        Route::post('airport/import/{token}', [DashboardController::class, 'airportImportConfirm'])->name('dashboard.admin.airport.import.confirm');
    });
    Route::get('airport/{icao}', [DashboardController::class, 'airportView'])->name('dashboard.admin.airport.view');
    Route::delete('airport/{icao}', [DashboardController::class, 'deleteAirport'])->middleware(['auth', 'can:delete airports'])->name('dashboard.admin.airport.delete');
    Route::get('airport/{icao}/{bay}', [DashboardController::class, 'bayView'])->name('dashboard.admin.bay.view');
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
    Route::get('aircraft', [DashboardController::class, 'aircraftList'])->name('dashboard.admin.aircraft.all');
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

// Contributor Applications Administration
Route::prefix('admin/applications')->middleware(['auth', 'can:review applications'])->group(function () {
    Route::get('/', [ContributorApplicationController::class, 'index'])->name('dashboard.admin.applications.index');
    Route::post('/{application}/approve', [ContributorApplicationController::class, 'approve'])->name('dashboard.admin.applications.approve');
    Route::post('/{application}/reject', [ContributorApplicationController::class, 'reject'])->name('dashboard.admin.applications.reject');
});

// Notification Administration
Route::prefix('admin/notifications')->middleware(['auth', 'can:send notifications'])->group(function () {
    Route::get('/', [NotificationController::class, 'adminIndex'])->name('dashboard.admin.notifications.index');
    Route::get('/create', [NotificationController::class, 'create'])->name('dashboard.admin.notifications.create');
    Route::post('/', [NotificationController::class, 'store'])->name('dashboard.admin.notifications.store');
    Route::get('/recipients', [NotificationController::class, 'recipients'])->name('dashboard.admin.notifications.recipients');
});

// Dashboard
// Public trial feedback - login required, 5 submissions an hour per user
Route::middleware('auth')->group(function () {
    Route::get('/provide-feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/provide-feedback', [FeedbackController::class, 'store'])->middleware('throttle:5,60')->name('feedback.store');

    Route::get('/my-feedback', [FeedbackController::class, 'myIndex'])->name('feedback.my.index');
    Route::get('/my-feedback/{feedback}', [FeedbackController::class, 'myShow'])->name('feedback.my.show');
    Route::post('/my-feedback/{feedback}/reply', [FeedbackController::class, 'userReply'])->middleware('throttle:20,60')->name('feedback.my.reply');
});

// Feedback admin view
Route::prefix('dashboard/feedback')->middleware(['auth', 'can:view feedback'])->group(function () {
    Route::get('/', [FeedbackController::class, 'index'])->name('dashboard.feedback.index');
    Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('dashboard.feedback.show');
    Route::post('/{feedback}/reply', [FeedbackController::class, 'reply'])->name('dashboard.feedback.reply');
    Route::post('/{feedback}/close', [FeedbackController::class, 'close'])->name('dashboard.feedback.close');
    Route::post('/{feedback}/reopen', [FeedbackController::class, 'reopen'])->name('dashboard.feedback.reopen');
});

// Bay assignment audit log - Developers only
Route::prefix('dashboard/logs')->middleware(['auth', 'can:view assignment logs'])->group(function () {
    Route::get('/', [AssignmentLogController::class, 'index'])->name('dashboard.logs.index');
    Route::get('/{icao}', [AssignmentLogController::class, 'show'])->name('dashboard.logs.show');
    Route::get('/{icao}/{callsign}/events', [AssignmentLogController::class, 'events'])->name('dashboard.logs.events');
});

Route::prefix('dashboard')->middleware('auth')->group(function () {
    Route::get('', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/my-settings', [DashboardController::class, 'settingsView'])->name('dashboard.settings.index');
    Route::get('/contribute', [ContributorApplicationController::class, 'create'])->name('dashboard.contribute');
    Route::post('/contribute', [ContributorApplicationController::class, 'store'])->name('dashboard.contribute.store');
    Route::post('/contribute/withdraw', [ContributorApplicationController::class, 'withdraw'])->name('dashboard.contribute.withdraw');
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
Route::get('/update/airports', [PagesController::class, 'AirportUpdate'])->name('airportsupdate');
Route::get('/test/vatsim-api', [TestController::class, 'Job'])->name('vatsimapi'); // Local Running Only

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
