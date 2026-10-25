@php
    $flight = Auth::user()->isFlying;

    if ($flight->callsign !== null) {
        $onGroundAtArrival = in_array($flight->status, ['Arrived', 'Taxiing', 'On Ground'], true);

        // How far along the route the aircraft is, measured against the furthest distance seen from the arrival airport
        if ($onGroundAtArrival) {
            $progress = 100;
        } elseif ($flight->status === 'Departing another Airport' || ! $flight->route_distance) {
            $progress = 0;
        } else {
            $progress = max(0, min(100, ($flight->route_distance - (float) $flight->distance) / $flight->route_distance * 100));
        }

        // Distance from the departure airport - straight from its coordinates when known, otherwise route minus distance to go
        $departureCoords = app(\App\Services\AirportLocations::class)->coordinates($flight->dep, false);
        if ($departureCoords !== null && $flight->lat !== null) {
            $fromDeparture = app(\App\Services\AirportFlightState::class)->distanceNm((float) $flight->lat, (float) $flight->lon, $departureCoords['lat'], $departureCoords['lon']);
        } else {
            $fromDeparture = $flight->route_distance && $flight->distance !== null ? max(0, $flight->route_distance - $flight->distance) : null;
        }
        $toArrival = $onGroundAtArrival ? 0 : $flight->distance;

        // Real-world bay only counts when the IRL flight flew the same route (see BayAllocation::selectBay)
        $live = $flight->liveBay;
        $sameRoute = $live->departure !== null && $live->departure === strtoupper((string) $flight->dep) && $live->airport === $flight->arr;
        $irlBay = $sameRoute && ! $live->isGateMatch() ? $live->bayInfo : null;

        // Or a gate covering several stands (e.g. YBSU gate "2")
        $irlGateStands = $sameRoute && $live->isGateMatch() ? $live->gateBays() : collect();
    }
@endphp

@if($flight->callsign === null)
    <div class="oz-flight-empty">
        <i class="fa-solid fa-plane-slash"></i>
        <div>
            <div class="oz-flight-empty-title">Currently Offline</div>
            <small class="text-muted">No flight detected within 1500NM of an airport serviced by OzBays</small>
        </div>
    </div>
@else
    <div class="oz-flight">
        <div class="oz-flight-head">
            <div>
                <span class="oz-flight-callsign">{{ $flight->callsign }}</span>
                <span class="oz-flight-type">{{ $flight->ac ?? 'Unknown type' }}</span>
            </div>
            <span class="oz-status-pill">{{ $flight->status ?? 'Unknown' }}</span>
        </div>

        {{-- Route progress --}}
        <div class="oz-route">
            <div class="oz-route-airport">
                <span class="oz-route-icao">{{ $flight->dep ?? '????' }}</span>
                <small class="text-muted">Departure</small>
                <span class="oz-route-distance">{{ $fromDeparture !== null ? number_format(round($fromDeparture)).'NM' : '—' }}</span>
            </div>

            <div class="oz-route-track" role="progressbar" aria-label="Flight progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($progress) }}">
                <div class="oz-route-fill" style="width: {{ $progress }}%;"></div>
                <div class="oz-route-plane" style="left: {{ $progress }}%;"><i class="fa-solid fa-plane"></i></div>
            </div>

            <div class="oz-route-airport text-right">
                <span class="oz-route-icao">{{ $flight->arr ?? '????' }}</span>
                <small class="text-muted">Arrival</small>
                <span class="oz-route-distance">{{ $toArrival !== null ? number_format(round($toArrival)).'NM' : '—' }}</span>
            </div>
        </div>

        {{-- Live stats --}}
        <div class="oz-flight-stats">
            <div><small>Altitude</small><span>{{ number_format((float) $flight->alt) }}ft</span></div>
            <div><small>Ground Speed</small><span>{{ $flight->speed }}kts</span></div>
            <div><small>Route Distance</small><span>{{ $flight->route_distance ? number_format($flight->route_distance).'NM' : '—' }}</span></div>
            <div><small>EIBT</small><span>{{ $flight->eibt ? $flight->eibt->format('H:i').'z' : '—' }}</span></div>
        </div>

        {{-- Bays --}}
        <div class="oz-flight-bays">
            <div class="oz-flight-bay {{ $flight->mapBay ? 'is-assigned' : '' }}">
                <small>OzBays Arrival Bay</small>
                @if($flight->mapBay)
                    <span class="oz-flight-bay-name">{{ $flight->mapBay->display_name }}</span>
                    <small class="text-muted">If unable, advise GND for an alternate bay on first contact</small>
                @else
                    <span class="oz-flight-bay-name text-muted">Not yet assigned</span>
                    <small class="text-muted">A bay will be assigned 200NM from {{ $flight->arr }}</small>
                @endif
            </div>

            <div class="oz-flight-bay">
                <small>Real-World Bay</small>
                @if($irlBay)
                    <span class="oz-flight-bay-name">{{ $irlBay->display_name }}</span>
                    <small class="text-muted">{{ $irlBay->terminal ? $irlBay->terminal.' · ' : '' }}Where the real {{ $flight->callsign }} is scheduled to park</small>
                @elseif($irlGateStands->isNotEmpty())
                    <span class="oz-flight-bay-name">Gate {{ $live->gate }}</span>
                    <small class="text-muted">{{ Str::plural('Stand', $irlGateStands->count()) }} {{ $irlGateStands->pluck('bay')->implode(', ') }} · Where the real {{ $flight->callsign }} is scheduled to park</small>
                @else
                    <span class="oz-flight-bay-name text-muted">No info</span>
                    <small class="text-muted">No real-world bay found for this flight</small>
                @endif
            </div>
        </div>
    </div>
@endif
