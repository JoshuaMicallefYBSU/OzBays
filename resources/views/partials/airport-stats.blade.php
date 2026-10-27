{{-- Home page ground traffic - top airports by inbound and on-ground aircraft (PartialsController::updateAirportStats) --}}
<p class="oz-stat-updated">
    <i class="fa fa-clock"></i>
    Updated {{ $lastUpdated ? \Carbon\Carbon::parse($lastUpdated)->diffForHumans() : 'just now' }}
</p>

<div class="row">
    @foreach([
        ['title' => 'Inbound', 'icon' => 'fa-plane-arrival', 'airports' => $stats_inbound, 'mode' => 'inbound', 'empty' => 'No aircraft inbound to OzBays airports'],
        ['title' => 'On the Ground', 'icon' => 'fa-tower-observation', 'airports' => $stats_ground, 'mode' => 'ground', 'empty' => 'No ground movements at OzBays airports'],
        ['title' => 'Active Controllers', 'icon' => 'fa-headset', 'airports' => $stats_controllers, 'mode' => 'controllers', 'empty' => 'No controllers online at OzBays airports'],
    ] as $section)
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="oz-stat-heading"><i class="fa {{ $section['icon'] }}"></i> {{ $section['title'] }}</div>

            @forelse($section['airports'] as $airport)
                <div class="oz-stat-row">
                    <span class="oz-stat-rank oz-stat-rank-{{ $loop->iteration }}">{{ $loop->iteration }}</span>
                    <span class="oz-stat-accent" style="background: {{ $airport->color ?? 'var(--oz-amber)' }};"></span>
                    <div class="oz-stat-airport">
                        <span class="oz-stat-icao">{{ $airport->icao }}</span>
                        <small class="text-muted">{{ $airport->name }} Airport</small>

                        @if($section['mode'] === 'controllers')
                            @php($positions = $airport->stats_controller_positions ?? [])
                            <div class="oz-controller-badges">
                                @foreach(['DEL' => 'DEL', 'GND' => 'GND', 'TWR' => 'TWR', 'APP_DEP' => 'APP/DEP'] as $key => $label)
                                    @php($online = $positions[$key] ?? false)
                                    <span class="oz-badge-position {{ $online ? 'oz-badge-online' : 'oz-badge-offline' }}">{{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if($section['mode'] === 'inbound')
                        <div class="oz-stat-count oz-stat-count-split">
                            <div class="oz-stat-count-item">
                                <span>{{ $airport->stats_inbound }}</span>
                                <small class="text-muted">tracked</small>
                            </div>
                            <div class="oz-stat-count-item">
                                <span>{{ $airport->stats_inbound_bay_assigned ?? 0 }}</span>
                                <small class="text-muted">bay assigned</small>
                            </div>
                        </div>
                    @elseif($section['mode'] === 'ground')
                        @php($breakdown = $airport->stats_ground_breakdown ?? ['arrivals' => 0, 'departures' => 0])
                        <div class="oz-stat-count oz-stat-count-split">
                            <div class="oz-stat-count-item">
                                <span>{{ $breakdown['arrivals'] ?? 0 }}</span>
                                <small class="text-muted">arrivals</small>
                            </div>
                            <div class="oz-stat-count-item">
                                <span>{{ $breakdown['departures'] ?? 0 }}</span>
                                <small class="text-muted">departures</small>
                            </div>
                        </div>
                    @else
                        <div class="oz-stat-count">
                            <span>{{ $airport->stats_controllers }}</span>
                            <small class="text-muted">online</small>
                        </div>
                    @endif
                </div>
            @empty
                <p class="oz-chart-empty">{{ $section['empty'] }}</p>
            @endforelse
        </div>
    @endforeach
</div>
