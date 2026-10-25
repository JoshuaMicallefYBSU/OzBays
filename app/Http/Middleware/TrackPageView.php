<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    // Identifies this browser regardless of login state, so anonymous and logged-in visits
    // can both be counted - never shown anywhere, just stored alongside the row.
    private const VISITOR_COOKIE = 'ozbays_visitor';

    private const VISITOR_COOKIE_MINUTES = 60 * 24 * 365 * 5; // ~5 years

    // A visit to the same page only counts once per window, so refreshing or lingering on
    // a page doesn't inflate the count - each tracked page gets its own dedupe cookie so
    // visiting two different pages in the same window still counts both.
    private const DEDUPE_MINUTES = 15;

    public function handle(Request $request, Closure $next, string $page): Response
    {
        $visitorId = $request->cookie(self::VISITOR_COOKIE);

        if ($visitorId === null) {
            $visitorId = (string) Str::uuid();
            Cookie::queue(self::VISITOR_COOKIE, $visitorId, self::VISITOR_COOKIE_MINUTES);
        }

        $dedupeCookie = 'ozbays_pv_seen_'.$page;

        if ($request->cookie($dedupeCookie) === null) {
            PageView::create([
                'page' => $page,
                'visitor_id' => $visitorId,
                'user_id' => auth()->id(),
            ]);

            Cookie::queue($dedupeCookie, '1', self::DEDUPE_MINUTES);
        }

        return $next($request);
    }
}
