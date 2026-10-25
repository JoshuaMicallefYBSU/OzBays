<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\UpdateLastSeen;
use App\Services\DiscordClient;
use Illuminate\Support\Facades\Auth;

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
