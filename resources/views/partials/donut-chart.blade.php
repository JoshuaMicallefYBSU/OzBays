{{--
    Donut chart drawn as SVG - $chart = ['total' => int, 'slices' => [['label', 'name', 'count', 'other'?], ...]] (from DashboardController::donutSlices)
    Slices are coloured by position (most flown first), with "Other" always neutral grey. Hover a slice for its count.
--}}
@php
    $palette = ['var(--oz-series-1)', 'var(--oz-series-2)', 'var(--oz-series-3)', 'var(--oz-series-4)', 'var(--oz-series-5)'];
    $total = max(1, $chart['total']);
    $gap = count($chart['slices']) > 1 ? 0.8 : 0; // surface gap between slices, in % of the ring
    $offset = 0;
@endphp

@if($chart['total'] === 0)
    <p class="oz-chart-empty">{{ $emptyText }}</p>
@else
    <div class="oz-donut">
        <svg viewBox="0 0 42 42" class="oz-donut-svg" role="img" aria-label="{{ $label }}">
            <circle cx="21" cy="21" r="15.915" fill="none" class="oz-donut-track" stroke-width="5"></circle>
            @foreach($chart['slices'] as $i => $slice)
                @php
                    $pct = $slice['count'] / $total * 100;
                    $colour = ($slice['other'] ?? false) ? 'var(--oz-series-other)' : $palette[$i];
                @endphp
                <circle cx="21" cy="21" r="15.915" fill="none" class="oz-donut-slice" stroke="{{ $colour }}" stroke-width="5"
                        stroke-dasharray="{{ max($pct - $gap, 0.01) }} {{ 100 - max($pct - $gap, 0.01) }}"
                        stroke-dashoffset="{{ 25 - $offset }}">
                    <title>{{ $slice['label'] }}{{ $slice['name'] ? ' ('.$slice['name'].')' : '' }}: {{ $slice['count'] }} {{ Str::plural('flight', $slice['count']) }} ({{ round($pct) }}%)</title>
                </circle>
                @php $offset += $pct; @endphp
            @endforeach
            <text x="21" y="20.5" class="oz-donut-total">{{ $chart['total'] }}</text>
            <text x="21" y="25.5" class="oz-donut-caption">{{ Str::plural('flight', $chart['total']) }}</text>
        </svg>

        <ul class="oz-donut-legend">
            @foreach($chart['slices'] as $i => $slice)
                <li>
                    <span class="oz-donut-key" style="background: {{ ($slice['other'] ?? false) ? 'var(--oz-series-other)' : $palette[$i] }};"></span>
                    <span class="oz-donut-label">
                        {{ $slice['label'] }}
                        @if($slice['name'])<small class="text-muted">{{ $slice['name'] }}</small>@endif
                    </span>
                    <span class="oz-donut-count">{{ $slice['count'] }} <small class="text-muted">{{ round($slice['count'] / $total * 100) }}%</small></span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
