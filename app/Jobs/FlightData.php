<?php

namespace App\Jobs;

use App\Models\Airports;
use App\Models\AssignmentLog;
use App\Models\BayAllocations;
use App\Models\BayConflicts;
use App\Models\Bays;
use App\Models\FlightLogs;
use App\Models\Flights;
use App\Services\AirportFlightState;
use App\Services\AirportLocations;
use App\Services\VATSIMClient;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FlightData implements ShouldQueue
{
    use Queueable;

    public $timeout = 55;

    public $tries = 1;

    // VATSIM ATC position suffixes counted towards an airport's "active controllers" stat.
    // CTR (en-route centre) and anything else (ATIS, OBS, FSS) is deliberately excluded - only
    // positions actually working this airport's terminal area count.
    private const CONTROLLER_POSITIONS = ['DEL', 'GND', 'TWR', 'APP', 'DEP'];

    /**
     * Create a new job instance.
     */
    public function __construct(private ?VATSIMClient $vatsimClient = null)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (env('APP_DEBUG') == true) {
            $this->update();
        } else {
            for ($i = 0; $i < 4; $i++) {

                $this->update();

                // Stop overlapping even if scheduler retries
                if ($i < 3) {
                    sleep(15);
                }
            }
        }

    }

    public function update(): void
    {
        $vatsimData = $this->vatsimClient ?? app(VATSIMClient::class);
        $data = $vatsimData->getVATSIMData();
        $pilots = $data->pilots ?? [];
        $controllers = $data->controllers ?? [];
        $state = app(AirportFlightState::class);

        // Bay Allocation Airports - Only These Flights get filtered
        $airports = Airports::whereIn('status', ['active', 'testing'])->get()->keyBy('icao');

        // dd($airports);

        $stats = [];
        $arrivalAircraft = array_fill_keys($airports->keys()->toArray(), []);

        $OnGround = [];
        $landingCalcs = [];

        foreach ($airports as $airport) {
            $stats[$airport->icao] = [
                'ground' => 0,
                'inbound' => 0,
                // Unique positions currently staffed for this airport's terminal area,
                // keyed by callsign so the same controller isn't double counted on a reconnect.
                'controllers' => [],
            ];
        }

        // Active ATC positions at OzBays airports - matched on each airport's VATSIM facility
        // code (e.g. "ML" for Melbourne, set per-airport via controller_prefix), not its ICAO -
        // terminal area controllers use facility codes like "SY_GND" or "ML-S_APP", not "YSSY_GND".
        // A callsign's facility is everything before the first "_", minus any "-SECTOR" suffix, so
        // "ML-S_APP" resolves to "ML" while "SML_APP" stays "SML" and correctly fails to match.
        $prefixToIcao = $airports->filter(fn (Airports $airport) => $airport->controller_prefix !== null)
            ->mapWithKeys(fn (Airports $airport) => [strtoupper($airport->controller_prefix) => $airport->icao]);

        foreach ($controllers as $controller) {
            $segments = explode('_', $controller->callsign ?? '');
            if (count($segments) < 2) {
                continue;
            }

            $position = strtoupper(end($segments));
            $facility = strtoupper(explode('-', $segments[0])[0]);

            if (in_array($position, self::CONTROLLER_POSITIONS, true) && $prefixToIcao->has($facility)) {
                $icao = $prefixToIcao->get($facility);
                $stats[$icao]['controllers'][$controller->callsign] = true;
            }
        }

        foreach ($pilots as $pilot) {

            $aircraft = Flights::where('callsign', $pilot->callsign)->first();

            $airportMatch = null;
            foreach ($airports as $icao => $airport) {
                $distance = $state->distanceNm(
                    $pilot->latitude,
                    $pilot->longitude,
                    $airport->lat,
                    $airport->lon
                );

                if ($distance < AirportFlightState::LOCAL_AIRPORT_RADIUS_NM) {
                    $airportMatch = $icao;
                    break;
                }
            }

            // Check if on ground
            $plan = data_get($pilot, 'flight_plan');
            $departure = $this->normaliseIcao(data_get($plan, 'departure'));
            $arrival = $this->normaliseIcao(data_get($plan, 'arrival'));
            $filedEtd = $state->filedEtd(data_get($plan, 'deptime'), Carbon::now('UTC'));

            if ($airportMatch && ! $state->isAirborne($pilot->groundspeed)) {
                $atGate = Bays::where('airport', $airportMatch)->where('status', 2)->where('callsign', $pilot->callsign)->exists();
                $phase = $state->localPhase($pilot->groundspeed, $atGate);
                $OnGround[] = [
                    'callsign' => $pilot->callsign,
                    'cid' => $pilot->cid,
                    'hdg' => $pilot->heading,
                    'at_airport' => $airportMatch,
                    'dep' => $departure,
                    'arr' => $arrival,
                    'ac' => data_get($plan, 'aircraft_short') ?: null,
                    'lat' => $pilot->latitude,
                    'lon' => $pilot->longitude,
                    'speed' => $pilot->groundspeed,
                    'alt' => $pilot->altitude,
                    'status_id' => null,
                    'status' => $phase === 'taxiing' ? 'Taxiing' : ($phase === 'at_gate' ? 'Arrived' : 'On Ground'),
                    'phase' => $phase,
                    'filed_etd' => $filedEtd,
                    'online' => 1,
                ];
            }

            // dd($OnGround);

            // Check FP - Changes what is done
            if ($plan == null) {
                continue;
            }

            // Flight Scheduled at a ozBays Airport
            if ($arrival && $airports->has($arrival)) {

                // Calculate distance from Airport
                $arrivalAirport = $airports[$arrival];

                $distanceToArrival = $state->distanceNm(
                    $pilot->latitude,
                    $pilot->longitude,
                    $arrivalAirport->lat,
                    $arrivalAirport->lon
                );

                // Do not interest yourself in Aircraft > 1500NM from the Airport oh little one
                if ($distanceToArrival > 1500) {
                    continue;
                }

                // Calculate Landing and Block Time (Estimates)

                $estimates = null;
                if ($state->isAirborne($pilot->groundspeed)) {
                    $estimates = $state->arrivalEstimates($distanceToArrival, $pilot->groundspeed, $arrivalAirport->eibt_variable, $arrivalAirport->taxi_time, Carbon::now('UTC'));
                }

                // Status Calculation
                if (! $state->isAirborne($pilot->groundspeed) && $distanceToArrival > 2 && $pilot->altitude < 8500) {
                    $status = 'Departing another Airport';
                } elseif (! $state->isAirborne($pilot->groundspeed) && $distanceToArrival > 2) {
                    $status = 'Paused';
                } elseif (! $state->isAirborne($pilot->groundspeed) && $distanceToArrival <= 2) {
                    $status = 'Arrived';
                } elseif ($state->isAirborne($pilot->groundspeed) && $distanceToArrival >= 2 && $distanceToArrival < 200) {
                    $status = 'On Approach';
                } elseif ($state->isAirborne($pilot->groundspeed) && $distanceToArrival >= 200) {
                    $status = 'Inbound';
                } else {
                    $status = 'Unknown';
                }

                $type = str_starts_with($departure, 'Y') ? 'DOM' : 'INTL';

                // Collate the Data
                $arrivalAircraft[$arrival][] = [
                    'cid' => $pilot->cid,
                    'callsign' => $pilot->callsign,
                    'dep' => $departure,
                    'arr' => $arrival,
                    'ac' => data_get($plan, 'aircraft_short'),
                    'hdg' => $pilot->heading,
                    'type' => $type,
                    'lat' => $pilot->latitude,
                    'lon' => $pilot->longitude,
                    'speed' => $pilot->groundspeed,
                    'alt' => $pilot->altitude,
                    'distance' => round($distanceToArrival),
                    'status' => $status,
                    'filed_etd' => $filedEtd,
                    'elt' => $estimates['elt'] ?? null,
                    'eibt' => $estimates['eibt'] ?? null,
                    'landed' => ! $state->isAirborne($pilot->groundspeed) && $distanceToArrival <= AirportFlightState::LOCAL_AIRPORT_RADIUS_NM,
                ];
            }
        }

        // Set all flights to offline (gets reupdated below)
        $all_flights = Flights::where('online', 1)->get();
        foreach ($all_flights as $fl) {
            $fl->online = null;
            $fl->save();
        }

        // Update the Entries in the Database
        foreach ($OnGround as $aa) {
            $existing = Flights::where('callsign', $aa['callsign'])->first();
            $changedPlan = $existing && $this->changedPlan($existing, $aa['dep'], $aa['arr']);
            $payload = [
                'id' => $aa['cid'],
                'cid' => $aa['cid'],
                'hdg' => $aa['hdg'],
                'dep' => $aa['dep'],
                'arr' => $aa['arr'],
                'ac' => $aa['ac'],
                'lat' => $aa['lat'],
                'lon' => $aa['lon'],
                'speed' => $aa['speed'],
                'alt' => $aa['alt'],
                'status' => $aa['status'],
                'filed_etd' => $aa['filed_etd'],
                'online' => 1,
            ];
            if ($changedPlan) {
                $payload += $this->resetLifecyclePayload();
            } elseif ($aa['phase'] === 'taxiing' && $existing && in_array($existing->status, ['Arrived', 'On Ground'], true)) {
                $payload['departed_at'] = $existing->departed_at ?? Carbon::now('UTC');
            }
            Flights::updateOrCreate(['callsign' => $aa['callsign']], $payload);

            $stats[$aa['at_airport']]['ground']++;
        }

        // Update the Entries in the Database
        foreach ($arrivalAircraft as $airportIcao => $aircraftList) {
            foreach ($aircraftList as $ac) {

                $existing = Flights::where('callsign', $ac['callsign'])->first();
                $changedPlan = $existing && $this->changedPlan($existing, $ac['dep'], $ac['arr']);
                $payload = [
                    'id' => $ac['cid'],
                    'cid' => $ac['cid'],
                    'dep' => $ac['dep'],
                    'ac' => $ac['ac'],
                    'type' => $ac['type'],
                    'arr' => $ac['arr'],
                    'hdg' => $ac['hdg'],
                    'lat' => $ac['lat'],
                    'lon' => $ac['lon'],
                    'speed' => $ac['speed'],
                    'alt' => $ac['alt'],
                    'distance' => $ac['distance'],
                    'route_distance' => $this->routeDistance($existing, $changedPlan, $ac, $airports[$airportIcao], $state),
                    'status' => $ac['status'],
                    'filed_etd' => $ac['filed_etd'],
                    'online' => 1,
                ];
                if ($changedPlan) {
                    $payload += $this->resetLifecyclePayload();
                } elseif ($ac['elt'] !== null) {
                    $payload['elt'] = $ac['elt'];
                    $payload['eibt'] = $ac['eibt'];
                } elseif ($ac['landed']) {
                    $payload['elt'] = null;
                    $payload['eibt'] = null;
                    $payload['landed_at'] = $existing?->landed_at ?? Carbon::now('UTC');
                }

                // First time this callsign has ever been seen inbound to this airport -
                // guarantees every flight shows up in the assignment log, even ones that
                // never end up getting a bay (go offline, turn away, etc).
                if ($existing === null) {
                    AssignmentLog::record($ac['arr'], $ac['callsign'], 'registered', 'Registered - inbound '.$ac['arr'], [
                        'dep' => $ac['dep'], 'ac' => $ac['ac'], 'type' => $ac['type'], 'distance' => $ac['distance'],
                    ], $ac['cid']);
                }

                Flights::updateOrCreate(['callsign' => $ac['callsign']], $payload);

                $stats[$ac['arr']]['inbound']++;
            }
        }

        // Alrighty. Need to record that the aircraft arrived at the Airport of choice
        // The parked bay is filled in later by BayAllocation once the aircraft stops at a bay
        $arrivals = Flights::whereNotNull('landed_at')->where('flight_recorded', 0)->where('online', 1)->with('mapBay')->get();
        foreach ($arrivals as $arr) {
            // dd($arr);
            try {
                FlightLogs::create([
                    'callsign' => $arr->callsign,
                    'airline' => FlightLogs::airlineFromCallsign($arr->callsign),
                    'departure' => $arr->dep,
                    'arrival' => $arr->arr,
                    'type' => $arr->type,
                    'aircraft' => $arr->ac,
                    'assigned_bay' => $arr->mapBay?->bay,
                    'user_id' => $arr->cid,
                ]);

                $arr->flight_recorded = 1;
                $arr->save();
            } catch (Exception $e) {
                // One bad row (e.g. a missing arr/type/ac on a NOT NULL column) shouldn't
                // stop the rest of the arrivals from being logged, or skip the offline
                // cleanup and stats update below. flight_recorded stays 0, so it's
                // retried on the next scheduled tick.
                Log::error("FlightData: failed to record flight log for {$arr->callsign}: {$e->getMessage()}");
            }
        }

        // Delete entries once offline for 15 minutes
        $offlineFlights = Flights::whereNull('online')->where('updated_at', '<', now()->subMinutes(6))->with('bayConflict')->get();

        // dd($offlineFlights);

        foreach ($offlineFlights as $flight) {

            // $flight->arr is whatever the flight is currently filed to, which for a
            // departure sitting at an OzBays airport is its real-world destination (e.g.
            // an overseas ICAO) - not necessarily (or even usually) an OzBays-tracked
            // airport. Only log here if it actually is one, so the assignment log never
            // picks up airports the system doesn't monitor.
            if ($flight->arr !== null && $airports->has($flight->arr)) {
                AssignmentLog::record($flight->arr, $flight->callsign, 'removed', 'Went offline - removed from tracking', [
                    'had_scheduled_bay' => $flight->scheduled_bay !== null,
                    'landed_at' => $flight->landed_at?->toIso8601String(),
                ], $flight->cid);
            }

            // Clear Bay Assignments
            $clearBays = BayAllocations::where('callsign', $flight->id)->get();
            foreach ($clearBays as $clearBay) {
                $clearBay->delete();
            }

            // Clear Bay Conflicts
            $slotConflicts = BayConflicts::where('callsign', $flight->id)->get();
            foreach ($slotConflicts as $conflict) {
                $conflict->delete();
            }

            // Delete the flight
            $flight->delete();
        }

        // Statistics Updates
        foreach ($airports as $airport) {
            $airport->stats_ground = $stats[$airport->icao]['ground'];
            $airport->stats_inbound = $stats[$airport->icao]['inbound'];
            $airport->stats_controllers = count($stats[$airport->icao]['controllers']);
            $airport->save();
        }

        // dd($offlineFlights);
        // dd($)
        // dd($OnGround);
        // dd($landingCalcs);
        // dd($stats);

    }

    private function normaliseIcao(?string $icao): ?string
    {
        $icao = strtoupper(trim((string) $icao));

        return $icao === '' ? null : $icao;
    }

    // Departure -> arrival distance the dashboard progress bar measures against. Worked out from the departure
    // airport when the flight is first detected (or its plan changes), then only ever grows if the aircraft is
    // further out than that (e.g. a departure turning away from the arrival). If the departure airport can't be
    // found, the furthest distance seen is used instead.
    private function routeDistance(?Flights $existing, bool $changedPlan, array $ac, Airports $arrival, AirportFlightState $state): int
    {
        $known = $changedPlan ? null : $existing?->route_distance;

        if ($known === null) {
            $departure = app(AirportLocations::class)->coordinates($ac['dep']);
            $known = $departure === null ? 0 : $state->distanceNm($departure['lat'], $departure['lon'], $arrival->lat, $arrival->lon);
        }

        return (int) round(max((float) $known, (float) $ac['distance']));
    }

    private function changedPlan(Flights $flight, ?string $departure, ?string $arrival): bool
    {
        return $flight->dep !== $departure || $flight->arr !== $arrival;
    }

    private function resetLifecyclePayload(): array
    {
        return [
            'elt' => null,
            'eibt' => null,
            'landed_at' => null,
            'departed_at' => null,
            'arrived_at' => null,
            'flight_recorded' => 0,
        ];
    }
}
