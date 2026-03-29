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
        if (auth()->check()) {
            $pref = UserPreference::where('user_id', Auth::user()->id)->first();

            if($pref == null){
                UserPreference::create(['user_id' => Auth::user()->id]);
            }
        }

        return $next($request);
    }
}