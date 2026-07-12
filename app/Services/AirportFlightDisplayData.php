<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Airports;
use App\Models\Bays;
use App\Models\Flights;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AirportFlightDisplayData
{
    public function __construct(private AirportFlightState $state) {}

    public function forAirportAndCallsign(string $icao, string $callsign): ?array
    {
        $icao = strtoupper(trim($icao));
        $callsign = strtoupper(trim($callsign));

        $airport = Airports::where('icao', $icao)->first();
        if ($airport === null || $callsign === '') {
            return null;
        }

        $flight = Flights::where('online', 1)
            ->where('callsign', $callsign)
            ->with('mapBay')
            ->latest('updated_at')
            ->first();

        if ($flight === null || ($flight->dep !== $icao && $flight->arr !== $icao)) {
            return null;
        }

        $confirmedBay = Bays::where('airport', $icao)
            ->where('status', 2)
            ->where('callsign', $flight->callsign)
            ->orderBy('id')
            ->first();

        $scheduledBay = $flight->mapBay?->airport === $icao ? $flight->mapBay : null;
        $bay = $confirmedBay ?? $scheduledBay;
        $gateSource = $confirmedBay !== null ? 'confirmed' : ($scheduledBay !== null ? 'scheduled' : 'unassigned');
        $operator = strtoupper(substr($flight->callsign ?? '', 0, 3));
        $airline = Airline::where('icao', $operator)->first();
        $direction = $flight->dep === $icao ? 'departure' : 'arrival';
        $status = $direction === 'departure'
            ? $this->departureStatus($flight, $confirmedBay !== null)
            : $this->arrivalStatus($flight, $confirmedBay !== null);
        $primary = $this->primaryTime($direction, $status, $flight, $confirmedBay !== null);
        $logoPath = $airline?->logo_path && str_starts_with($airline->logo_path, 'img/airlines/') ? $airline->logo_path : null;

        return [
            'airport' => $airport,
            'flight' => $flight,
            'operator' => $operator,
            'airline' => $airline,
            'logo_path' => $logoPath,
            'direction' => $direction,
            'origin' => $flight->dep,
            'destination' => $flight->arr,
            'gate' => $bay?->bay,
            'terminal' => $bay?->terminal,
            'gate_source' => $gateSource,
            'gate_key' => $bay?->bay ? $gateSource.'|'.$bay->bay.'|'.($bay->terminal ?? '') : 'unassigned',
            'status' => $status,
            'status_class' => 'oz-gate-display-status--'.Str::slug($status),
            'primary_time' => $primary['time'],
            'primary_label' => $primary['label'],
            'filed_etd' => $flight->filed_etd,
            'eta' => $flight->elt,
            'landed_at' => $flight->landed_at,
            'arrived_at' => $flight->arrived_at,
            'departed_at' => $flight->departed_at,
            'distance' => $flight->distance,
            'rendered_at' => Carbon::now('UTC'),
        ];
    }

    private function departureStatus(Flights $flight, bool $atGate): string
    {
        if ($flight->departed_at !== null) {
            return 'Departed';
        }
        if ($this->state->localPhase($flight->speed, $atGate) === 'taxiing') {
            return 'Taxiing';
        }

        return $this->state->departureStatus(false, $flight->filed_etd, Carbon::now('UTC')) ?? 'Gate Open';
    }

    private function arrivalStatus(Flights $flight, bool $atGate): string
    {
        $status = $this->state->arrivalStatus($atGate, $flight->landed_at);
        if ($status === 'Arriving' && (float) $flight->distance > 200) {
            return 'Inbound';
        }

        return $status;
    }

    private function primaryTime(string $direction, string $status, Flights $flight, bool $atGate): array
    {
        if ($direction === 'departure') {
            return [
                'label' => $status === 'Departed' ? 'Departed' : ($status === 'Taxiing' ? 'Taxiing' : 'Boarding at'),
                'time' => $status === 'Departed' ? $flight->departed_at : $flight->filed_etd,
            ];
        }

        if ($atGate) {
            return ['label' => 'At Gate', 'time' => $flight->arrived_at];
        }
        if ($flight->landed_at !== null) {
            return ['label' => 'Landed', 'time' => $flight->landed_at];
        }

        return ['label' => 'ETA', 'time' => $flight->elt];
    }
}
