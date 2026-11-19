<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\UpdateLastSeen;
use App\Services\DiscordClient;
use Illuminate\Database\LostConnectionDetector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            UpdateLastSeen::class,
        ]);
        $middleware->alias([
            'track.pageview' => TrackPageView::class,
        ]);
        // Set client-side via plain `document.cookie` (layouts/messages.blade.php's dismiss
        // handler) so it can be written instantly with no server round-trip - Laravel's
        // encrypted cookies can only be set/read through a response, so this one has to be
        // excluded from encryption or request()->cookie() would never see it as present.
        $middleware->encryptCookies(except: [
            'ozbays_vatpac_trial_notification',
        ]);
        $middleware->redirectGuestsTo(fn () => route('auth.sso.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $e) {
            try {
            // MySQL briefly unavailable (host restarts) - every per-minute job fails, so only alert once it has been
            // down for a few minutes, and at most every 30 minutes. Tracked in the file cache as the normal cache
            // lives in MySQL. Short blips just recover on the next run.
            if (app(LostConnectionDetector::class)->causedByLostConnection($e)) {
                $cache = Cache::store('file');
                $downSince = $cache->get('db-outage:since', now()->getTimestamp());
                $cache->put('db-outage:since', $downSince, now()->addMinutes(10)); // resets after 10 quiet minutes

                $minutesDown = (int) floor((now()->getTimestamp() - $downSince) / 60);
                if ($minutesDown < 3 || $cache->has('db-outage:alerted')) {
                    return;
                }
                $cache->put('db-outage:alerted', true, now()->addMinutes(30));

                (new DiscordClient())->sendMessageWithEmbed(
                    config('services.discord.' . config('app.env') . '.error_logs'),
                    'Database Unavailable',
                    "MySQL has been unreachable for {$minutesDown} minutes - jobs and pages that use the database are failing.\n"
                        . "Message: {$e->getMessage()}",
                    'fc0303',
                );

                return;
            }

            $authUser = Auth::check() ? Auth::user() : null;

            $authText = $authUser
                ? 'Auth: ' . ($authUser->fullName('FLC'))
                : 'Auth: Guest';

            $message = "Message: {$e->getMessage()}\n"
                . "File: {$e->getFile()}\n"
                . "Line: {$e->getLine()}\n"
                . $authText;

            $discord = new DiscordClient();
            $discord->sendMessageWithEmbed(
                config('services.discord.' . config('app.env') . '.error_logs'),
                'Server Error has Occurred',
                $message,
                'fc0303',
            );
        } catch (Throwable $discordError) {
            // Swallow this so Discord failure does not break exception reporting
        }
        });
    })

    
    ->create();
