<?php

namespace Tests\Unit;

use App\Services\AirportFlightState;
use Carbon\Carbon;
use Tests\TestCase;

class AirportFlightStateTest extends TestCase
{
    public function test_distance_and_phase_thresholds_are_consistent(): void
    {
        $state = new AirportFlightState;

        $this->assertEqualsWithDelta(60, $state->distanceNm(0, 0, 1, 0), 0.1);
        $this->assertSame('on_ground', $state->localPhase(5));
        $this->assertSame('taxiing', $state->localPhase(5.1));
        $this->assertSame('at_gate', $state->localPhase(99, true));
        $this->assertFalse($state->isAirborne(80));
        $this->assertTrue($state->isAirborne(80.1));
    }

    public function test_filed_etd_resolves_utc_and_rejects_malformed_times(): void
    {
        $state = new AirportFlightState;
        $now = Carbon::parse('2026-07-12 23:55:00', 'UTC');

        $this->assertSame('2026-07-13 00:10', $state->filedEtd('0010', $now)?->format('Y-m-d H:i'));
        $this->assertNull($state->filedEtd('2460', $now));
        $this->assertNull($state->filedEtd(null, $now));
    }

    public function test_eta_and_board_status_boundaries(): void
    {
        $state = new AirportFlightState;
        $now = Carbon::parse('2026-07-12 12:00:00', 'UTC');
        $estimate = $state->arrivalEstimates(100, 200, 1.2, 15, $now);

        $this->assertSame('12:36', $estimate['elt']->format('H:i'));
        $this->assertSame('12:51', $estimate['eibt']->format('H:i'));
        $this->assertNull($state->arrivalEstimates(100, 80, 1, 0, $now));
        $this->assertSame('Gate Open', $state->departureStatus(false, $now->copy()->addMinutes(31), $now));
        $this->assertSame('Boarding', $state->departureStatus(false, $now->copy()->addMinutes(30), $now));
        $this->assertSame('Final Call', $state->departureStatus(false, $now->copy()->addMinutes(10), $now));
        $this->assertSame('Closed', $state->departureStatus(false, $now, $now));
        $this->assertSame('Departed', $state->departureStatus(true, $now->copy()->addHour(), $now));
        $this->assertSame('Arriving', $state->arrivalStatus(false, null));
        $this->assertSame('Landed', $state->arrivalStatus(false, $now));
        $this->assertSame('Arrived', $state->arrivalStatus(true, $now));
    }
}
