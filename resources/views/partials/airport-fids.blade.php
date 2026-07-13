@php
    $isDepartures = $board === 'departures';
    $headers = $isDepartures
        ? ['Status', 'Flight', 'Type', 'Destination', 'Gate', 'Filed', 'Time']
        : ['Status', 'Flight', 'Type', 'Origin', 'Gate', 'Distance', 'Time'];
@endphp

<div class="oz-fids" aria-live="polite" aria-label="{{ ucfirst($board) }} flight information display">
    <div class="oz-fids-terminal-bar">
        <span class="oz-fids-terminal-mark">{{ $icao }}</span>
        <span class="oz-fids-terminal-title">{{ $isDepartures ? 'Departures' : 'Arrivals' }}</span>
        <span class="oz-fids-terminal-live">
            <i class="fas fa-circle" aria-hidden="true"></i> Live Flight Information
            <span class="oz-fids-cycle" aria-live="polite">
                <span id="oz-board-cycle-label">Next display</span>
                <strong id="oz-board-cycle-time">15s</strong>
                <span class="oz-fids-cycle-track" aria-hidden="true"><span id="oz-board-cycle-progress"></span></span>
            </span>
        </span>
    </div>
    <div class="oz-fids-scroll">
        <div class="oz-fids-row oz-fids-head">
            @foreach ($headers as $header)
                <div class="oz-fids-col">{{ $header }}</div>
            @endforeach
        </div>
        @forelse ($rows as $row)
            @php
                $airline = $airlines->get($row['operator']);
                $logo = $airline?->logo_path && str_starts_with($airline->logo_path, 'img/airlines/') ? asset($airline->logo_path) : null;
                $statusClass = 'oz-fids-status--'.str($row['status'])->slug();
            @endphp
            <div class="oz-fids-row">
                <div class="oz-fids-col"><span class="oz-fids-status {{ $statusClass }}">{{ $row['status'] }}</span></div>
                <div class="oz-fids-col oz-fids-col--flight">
                    <span class="oz-fids-flight-ident">
                        <span class="oz-fids-logo" aria-hidden="true">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                            @endif
                            <span @if($logo) hidden @endif>{{ $row['operator'] }}</span>
                        </span>
                        <a
                            class="oz-fids-callsign oz-fids-callsign-link"
                            href="{{ route('airport.flight-display', ['icao' => $icao, 'callsign' => $row['callsign']]) }}"
                            target="_blank"
                            rel="noopener"
                            aria-label="Open gate display for {{ $row['callsign'] }}"
                        >{{ $row['callsign'] }}</a>
                    </span>
                </div>
                <div class="oz-fids-col oz-fids-col--type">{{ $row['ac'] ?? '--' }}</div>
                <div class="oz-fids-col oz-fids-col--origin">{{ $isDepartures ? ($row['destination'] ?? '--') : ($row['origin'] ?? '--') }}</div>
                <div class="oz-fids-col oz-fids-col--bay">{{ $row['bay'] ?? 'TBA' }}</div>
                <div class="oz-fids-col oz-fids-col--dist">{{ $isDepartures ? ($row['time'] ? $row['time']->format('H:i').'z' : '--') : ($row['distance'] !== null ? $row['distance'].' NM' : '--') }}</div>
                <div class="oz-fids-col oz-fids-col--time"><span class="oz-fids-time">{{ $row['time'] ? $row['time']->format('H:i').'z' : '--' }}</span><span class="oz-fids-time-label">{{ $row['time_label'] }}</span></div>
            </div>
        @empty
            <div class="oz-fids-empty">No {{ $isDepartures ? 'local departures' : 'arrivals' }} currently tracked for {{ $icao }}</div>
        @endforelse
    </div>
</div>
