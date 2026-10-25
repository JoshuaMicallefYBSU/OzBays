<?php

namespace App\Http\Controllers;

use App\Models\AssignmentLog;
use Illuminate\Http\Request;

class AssignmentLogController extends Controller
{
    // One row per airport the system has ever logged an event for, with a running
    // count of distinct callsigns and when something was last logged.
    public function index()
    {
        $airports = AssignmentLog::selectRaw('airport, COUNT(DISTINCT callsign) as callsign_count, MAX(created_at) as last_activity')
            ->groupBy('airport')
            ->orderByDesc('last_activity')
            ->get();

        return view('dashboard.admin.logs.index', compact('airports'));
    }

    // Every callsign ever logged for this airport, newest activity first, with
    // its most recently recorded status - the dropdown events are lazy-loaded
    // per row via events() to avoid pulling every raw payload up front.
    // Optionally filtered by ?search= - matched against the callsign itself.
    public function show(string $icao, Request $request)
    {
        $icao = strtoupper($icao);
        $search = trim((string) $request->query('search', ''));

        $callsigns = AssignmentLog::where('airport', $icao)
            ->when($search !== '', fn ($q) => $q->where('callsign', 'LIKE', '%'.$search.'%'))
            ->selectRaw('callsign, MIN(created_at) as first_seen, MAX(created_at) as last_seen')
            ->groupBy('callsign')
            ->orderByDesc('last_seen')
            ->paginate(50)
            ->withQueryString();

        if ($callsigns->isNotEmpty()) {
            $latestByCallsign = AssignmentLog::where('airport', $icao)
                ->whereIn('callsign', $callsigns->pluck('callsign'))
                ->orderByDesc('created_at')
                ->get()
                ->unique('callsign')
                ->keyBy('callsign');

            foreach ($callsigns as $row) {
                $row->final_status = $latestByCallsign->get($row->callsign)?->status
                    ?? $latestByCallsign->get($row->callsign)?->event_type;
            }
        }

        return view('dashboard.admin.logs.show', ['icao' => $icao, 'callsigns' => $callsigns, 'search' => $search]);
    }

    // AJAX partial - every event ever logged for one callsign at one airport, newest first.
    public function events(string $icao, string $callsign)
    {
        $events = AssignmentLog::where('airport', strtoupper($icao))
            ->where('callsign', $callsign)
            ->orderByDesc('created_at')
            ->get();

        return view('dashboard.admin.logs.events', compact('events'));
    }
}
