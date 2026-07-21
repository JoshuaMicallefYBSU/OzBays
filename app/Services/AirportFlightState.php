<?php

namespace App\Services;

use Carbon\CarbonInterface;

class AirportFlightState
{
    public const LOCAL_AIRPORT_RADIUS_NM = 3.0;

    public const CONFIRMED_BAY_RADIUS_METERS = 30.0;

    public const STATIONARY_SPEED_KTS = 5.0;

    public const TAXI_SPEED_KTS = 80.0;

    public const AIRBORNE_SPEED_KTS = 80.0;

    public function distanceNm(float|int|string $lat1, float|int|string $lon1, float|int|string $lat2, float|int|string $lon2): float
    {
        $lat1 = deg2rad((float) $lat1);
        $lon1 = deg2rad((float) $lon1);
        $lat2 = deg2rad((float) $lat2);
        $lon2 = deg2rad((float) $lon2);
        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return 3440.065 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function distanceMeters(float|int|string $lat1, float|int|string $lon1, float|int|string $lat2, float|int|string $lon2): float
    {
        return $this->distanceNm($lat1, $lon1, $lat2, $lon2) * 1852;
    }

    public function localPhase(float|int|string $speed, bool $atGate = false): string
    {
        if ($atGate) {
            return 'at_gate';
        }

        return (float) $speed > self::STATIONARY_SPEED_KTS ? 'taxiing' : 'on_ground';
    }

    public function isAirborne(float|int|string $speed): bool
    {
        return (float) $speed > self::AIRBORNE_SPEED_KTS;
    }

    public function filedEtd(?string $value, CarbonInterface $now): ?CarbonInterface
    {
        $value = trim((string) $value);
        if (! preg_match('/^([01]\d|2[0-3])([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        $candidate = $now->copy()->utc()->setTime((int) $matches[1], (int) $matches[2]);
        if ($candidate->lt($now->copy()->utc()->subHours(12))) {
            return $candidate->addDay();
        }
        if ($candidate->gt($now->copy()->utc()->addHours(12))) {
            return $candidate->subDay();
        }

        return $candidate;
    }

    public function arrivalEstimates(float|int|string $distance, float|int|string $speed, float|int|string|null $adjustment, int|float|string|null $taxiMinutes, CarbonInterface $now): ?array
    {
        $speed = (float) $speed;
        if ($speed <= self::AIRBORNE_SPEED_KTS || (float) $distance < 0) {
            return null;
        }
        $minutes = ((float) $distance / $speed) * 60 * max(1, (float) ($adjustment ?? 1));
        $elt = $now->copy()->utc()->addMinutes((int) round($minutes));

        return ['elt' => $elt, 'eibt' => $elt->copy()->addMinutes((int) $taxiMinutes)];
    }

    public function departureStatus(bool $departed, ?CarbonInterface $filedEtd, CarbonInterface $now): ?string
    {
        if ($departed) {
            return 'Departed';
        }
        if ($filedEtd === null) {
            return null;
        }
        $minutes = $now->diffInMinutes($filedEtd, false);
        if ($minutes <= 0) {
            return 'Closed';
        }
        if ($minutes <= 10) {
            return 'Final Call';
        }
        if ($minutes <= 30) {
            return 'Boarding';
        }

        return 'Gate Open';
    }

    public function arrivalStatus(bool $atGate, ?CarbonInterface $landedAt): string
    {
        if ($atGate) {
            return 'Arrived';
        }

        return $landedAt !== null ? 'Landed' : 'Arriving';
    }
}
