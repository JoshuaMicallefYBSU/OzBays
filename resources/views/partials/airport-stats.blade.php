{{-- Home page ground traffic - top 3 OzBays airports by inbound and on-ground aircraft (PartialsController::updateAirportStats) --}}
<div class="row">
    @foreach([
        ['title' => 'Inbound', 'icon' => 'fa-plane-arrival', 'airports' => $stats_inbound, 'field' => 'stats_inbound', 'unit' => 'inbound', 'empty' => 'No aircraft inbound to OzBays airports'],
        ['title' => 'On the Ground', 'icon' => 'fa-tower-observation', 'airports' => $stats_ground, 'field' => 'stats_ground', 'unit' => 'on the ground', 'empty' => 'No ground movements at OzBays airports'],
        ['title' => 'Active Controllers', 'icon' => 'fa-headset', 'airports' => $stats_controllers, 'field' => 'stats_controllers', 'unit' => 'online', 'empty' => 'No controllers online at OzBays airports'],
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
                    </div>
                    <div class="oz-stat-count">
                        <span>{{ $airport->{$section['field']} }}</span>
                        <small class="text-muted">{{ $section['unit'] }}</small>
                    </div>
                </div>
            @empty
                <p class="oz-chart-empty">{{ $section['empty'] }}</p>
            @endforelse
        </div>
    @endforeach
</div>
