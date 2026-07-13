<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            // only update once every 60 minutes to avoid constant writes
            if (!$user->last_seen || $user->last_seen->lt(now()->subMinutes(60))) {
                $user->forceFill([
                    'last_seen' => now(),
                ])->save();
            }
        }

        return $next($request);
    }
}