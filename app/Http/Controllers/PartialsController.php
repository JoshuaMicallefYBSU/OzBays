<?php

namespace App\Http\Controllers;

use App\Models\Airline;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;
use App\Services\AirportFlightState;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PartialsController extends Controller
{
    // ############# -------------------------------- ##############
    //  All Site Endpoints that update data over time
    // ############# -------------------------------- ##############

    // Render Ladder for Airport
    public function updateLadder(Request $request, string $icao)
    {
        $icao = strtoupper(trim($icao));
        abort_unless(Airports::where('icao', $icao)->exists(), 404);
        $board = $request->query('board', 'departures');
        abort_unless(in_array($board, ['departures', 'arrivals'], true), 422);

        $occupiedBays = Bays::where('airport', $icao)
            ->where('status', 2)->whereIn('id', function ($q) {
                $q->selectRaw('MIN(id)')
                    ->from('bays')
                    ->where('status', 2)
                    ->groupBy('callsign');
            })->with('FlightInfo')->orderBy('callsign', 'asc')->get()->keyBy('callsign');

        $flights = Flights::where('online', 1)->with('mapBay')->get();
        $state = app(AirportFlightState::class);
        $now = Carbon::now('UTC');
        $rows = $flights->map(function (Flights $flight) use ($board, $icao, $occupiedBays, $state, $now) {
            $occupiedBay = $occupiedBays->get($flight->callsign);
            $atGate = $occupiedBay !== null;
            $local = $this->isLocalFlight($flight, $icao);

            if ($board === 'arrivals') {
                if ($flight->arr !== $icao || (! $state->isAirborne($flight->speed) && ! $local && ! $atGate)) {
                    return null;
                }
                $status = $state->arrivalStatus($atGate, $flight->landed_at);
                if ($status === 'Arriving' && (float) $flight->distance > 200) {
                    $status = 'Inbound';
                }
                $time = $atGate ? $flight->arrived_at : ($flight->landed_at ?? $flight->elt);

                return $this->row($flight, $occupiedBay, $status, $time, $atGate ? 'At Gate' : ($flight->landed_at ? 'Landed' : 'ETA'), $status === 'Arriving' ? $flight->distance : null);
            }

            if ($flight->dep !== $icao || ! $local) {
                return null;
            }
            $departed = $flight->departed_at !== null;
            $taxiing = ! $departed && $state->localPhase($flight->speed, $atGate) === 'taxiing';
            $status = $taxiing ? 'Taxiing' : $state->departureStatus($departed, $flight->filed_etd, $now);
            if ($status === null) {
                return null;
            }
            $time = $departed ? $flight->departed_at : $flight->filed_etd;

            return $this->row($flight, $occupiedBay, $status, $time, $departed ? 'Departed' : 'Filed ETD');
        })->filter()->unique('callsign');

        $rows = $board === 'departures'
            ? $rows->sortBy(fn (array $row) => sprintf('%02d-%020d', $this->departureStatusRank($row['status']), $row['sort_time'] ?? PHP_INT_MAX))->values()
            : $rows->sortBy(fn (array $row) => $row['sort_time'] ?? PHP_INT_MAX)->values();

        // Resolve airline names from the 3-letter ICAO operator prefix of each callsign,
        // in one query rather than one per row.
        $callsigns = $rows->pluck('callsign')->filter();

        $operators = $callsigns->map(fn ($cs) => strtoupper(substr($cs, 0, 3)))->unique()->values();

        $airlines = Airline::whereIn('icao', $operators)->get()->keyBy('icao');

        return view('partials.airport-fids', compact('icao', 'board', 'rows', 'airlines'))->render();
    }

    private function isLocalFlight(Flights $flight, string $icao): bool
    {
        $airport = Airports::where('icao', $icao)->first();
        if ($airport === null || $flight->lat === null || $flight->lon === null) {
            return false;
        }

        return app(AirportFlightState::class)->distanceNm($flight->lat, $flight->lon, $airport->lat, $airport->lon) < AirportFlightState::LOCAL_AIRPORT_RADIUS_NM;
    }

    private function row(Flights $flight, ?Bays $occupiedBay, string $status, mixed $time, string $timeLabel, ?int $distance = null): array
    {
        $bay = $occupiedBay ?? $flight->mapBay;

        return [
            'callsign' => $flight->callsign,
            'operator' => strtoupper(substr($flight->callsign ?? '', 0, 3)),
            'ac' => $flight->ac,
            'origin' => $flight->dep,
            'destination' => $flight->arr,
            'bay' => $bay?->bay,
            'terminal' => $bay?->terminal,
            'status' => $status,
            'time' => $time,
            'time_label' => $timeLabel,
            'distance' => $distance,
            'sort_time' => $time?->getTimestamp(),
        ];
    }

    private function departureStatusRank(string $status): int
    {
        return match ($status) {
            'Departed' => 0,
            'Taxiing' => 1,
            'Closed' => 2,
            'Boarding', 'Final Call' => 3,
            'Gate Open' => 4,
            default => 5,
        };
    }

    // Render FlightInfo on Dashboard
    public function updateFlights()
    {
        return view('partials.dashboard-flight')->render();
    }

    public function updateAirportStats()
    {
        $stats_ground = Airports::whereIn('status', ['testing', 'active'])->where('stats_ground', '>', 0)->orderBy('stats_ground', 'desc')->limit(3)->get();
        $stats_inbound = Airports::whereIn('status', ['testing', 'active'])->where('stats_inbound', '>', 0)->orderBy('stats_inbound', 'desc')->limit(3)->get();

        return view('partials.airport-stats', compact('stats_ground', 'stats_inbound'))->render();
    }

    // Render Notification Bell for logged in users
    public function updateNotifications(Request $request)
    {
        $notifications = $request->user()->notifications()->limit(8)->get();
        $unread_count = $request->user()->unreadNotifications()->count();

        return view('partials.notifications', compact('notifications', 'unread_count'))->render();
    }
}
