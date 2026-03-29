<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\UserPreference;

class UpdateUserPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = Auth::id();

        if ($userId) {
            UserPreference::firstOrCreate([
                'user_id' => $userId,
            ]);
        }

        return $next($request);
    }
}