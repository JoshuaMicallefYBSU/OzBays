@php
    $logo = $display['logo_path'] ? asset($display['logo_path']) : null;
    $gateLabel = $display['gate'] ?? 'TBA';
    $gateSourceLabel = match ($display['gate_source']) {
        'confirmed' => 'Gate',
        'scheduled' => 'Scheduled Gate',
        default => 'Gate',
    };
    $routePlace = $display['direction'] === 'departure' ? $display['destination'] : $display['origin'];
    $routeLabel = $display['direction'] === 'departure' ? 'Destination' : 'Origin';
    $hasLanded = $display['direction'] === 'arrival' && $display['landed_at'] !== null;
@endphp

<div class="oz-gate-display" data-gate-key="{{ $display['gate_key'] }}" data-gate="{{ $display['gate'] }}" data-terminal="{{ $display['terminal'] }}">
    <section class="oz-gate-route-panel">
        <div class="oz-gate-strip"><span>{{ $display['direction'] === 'departure' ? 'Now Boarding' : 'Arrival Information' }}</span><span>{{ $display['airport']->icao }}</span></div>
        <div class="oz-gate-route-label">{{ $routeLabel }}</div>
        <h1>{{ $routePlace ?? '--' }}</h1>
        <div class="oz-gate-route-meta">
            <span>{{ $display['origin'] ?? '--' }}</span>
            <i class="fas fa-arrow-right" aria-hidden="true"></i>
            <span>{{ $display['destination'] ?? '--' }}</span>
        </div>
        <div class="oz-gate-primary-time">
            <span>{{ $display['primary_label'] }}</span>
            <strong>{{ $display['primary_time'] ? $display['primary_time']->format('H:i') : '--:--' }}</strong>
        </div>
    </section>

    <section class="oz-gate-flight-panel">
        <div class="oz-gate-flight-topline">
            <span class="oz-gate-logo" aria-hidden="true">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                @endif
                <span @if($logo) hidden @endif>{{ $display['operator'] }}</span>
            </span>
            <div>
                <div class="oz-gate-flight-number">{{ $display['flight']->callsign }}</div>
            </div>
        </div>

        <div class="oz-gate-status-row">
            <span class="oz-gate-display-status {{ $display['status_class'] }}">{{ $display['status'] }}</span>
            <span>{{ $display['flight']->ac ?? 'Aircraft TBA' }}</span>
        </div>

        <div class="oz-gate-grid {{ $display['direction'] === 'departure' ? 'oz-gate-grid--departure' : '' }}">
            <div class="oz-gate-cell oz-gate-cell--hero">
                <span>{{ $gateSourceLabel }}</span>
                <strong>{{ $gateLabel }}</strong>
                @if($display['terminal'])<em>Terminal {{ $display['terminal'] }}</em>@endif
            </div>
            <div class="oz-gate-cell">
                <span>Filed ETD</span>
                <strong>{{ $display['filed_etd'] ? $display['filed_etd']->format('H:i') : '--:--' }}</strong>
            </div>
            @if ($display['direction'] === 'arrival')
                <div class="oz-gate-cell">
                    <span>{{ $hasLanded ? 'Landed' : 'ETA' }}</span>
                    <strong>{{ $hasLanded ? $display['landed_at']->format('H:i') : ($display['eta'] ? $display['eta']->format('H:i') : '--:--') }}</strong>
                </div>
                @if (! $hasLanded)
                    <div class="oz-gate-cell">
                        <span>Distance</span>
                        <strong>{{ $display['distance'] !== null ? $display['distance'].' NM' : '--' }}</strong>
                    </div>
                @endif
            @endif
        </div>

        <p class="oz-gate-helptext">
            {{ $display['direction'] === 'departure'
                ? 'Please remain near the gate area and monitor this display for boarding updates.'
                : 'Gate information updates automatically as the aircraft progresses to stand.' }}
        </p>
        <div class="oz-gate-updated">Updated {{ $display['rendered_at']->format('H:i:s') }} UTC</div>
    </section>
</div>
