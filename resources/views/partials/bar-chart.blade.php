{{--
    Daily bar chart drawn as plain CSS bars - $series = ['YYYY-MM-DD' => count, ...], oldest first.
    Hover a bar for its date and count. $label is used as the chart's accessible name.
--}}
@php
    $max = max(1, max($series));
@endphp

@if(array_sum($series) === 0)
    <p class="oz-chart-empty">No page views recorded in this period yet.</p>
@else
    <div class="oz-bars" role="img" aria-label="{{ $label }}">
        @foreach($series as $day => $count)
            @php
                $height = max(2, round($count / $max * 100));
                $date = \Carbon\Carbon::parse($day);
            @endphp
            <div class="oz-bar-col" title="{{ $date->format('D, j M') }}: {{ $count }} {{ Str::plural('view', $count) }}">
                <div class="oz-bar-track">
                    {{-- Only rendered when there's something to show - .oz-bar's min-height would
                         otherwise draw a visible sliver for every zero-count day, turning 30 empty
                         days into a solid baseline that crowds the date labels underneath it. --}}
                    @if($count > 0)
                        <div class="oz-bar" style="height: {{ $height }}%;"></div>
                    @endif
                </div>
                <span class="oz-bar-label">{{ ($loop->first || $loop->last || $date->isMonday()) ? $date->format('j/n') : '' }}</span>
            </div>
        @endforeach
    </div>
@endif
