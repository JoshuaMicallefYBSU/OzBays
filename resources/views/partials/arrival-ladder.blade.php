@php
    $rows = collect();
    $onBayCallsigns = $occupied_bays->pluck('callsign')->filter()->all();

    foreach ($occupied_bays as $bay) {
        $rows->push([
            'phase' => 'boarding',
            'callsign' => $bay->callsign,
            'operator' => strtoupper(substr($bay->callsign ?? '', 0, 3)),
            'ac' => optional($bay->FlightInfo)->ac,
            'dep' => optional($bay->FlightInfo)->dep,
            'bay' => $bay->bay,
            'terminal' => $bay->terminal,
            'time' => $bay->updated_at,
            'time_label' => 'On Bay Since',
            'distance' => null,
        ]);
    }

    foreach ($taxing as $aircraft) {
        // Skip aircraft already shown as "Now Boarding" — a flight keeps
        // status "Arrived" even once its bay is confirmed occupied, since
        // there's no separate DB flag for "landed" vs "on bay".
        if (in_array($aircraft->callsign, $onBayCallsigns, true)) {
            continue;
        }

        $rows->push([
            'phase' => 'taxiing',
            'callsign' => $aircraft->callsign,
            'operator' => strtoupper(substr($aircraft->callsign ?? '', 0, 3)),
            'ac' => $aircraft->ac,
            'dep' => $aircraft->dep,
            'bay' => optional($aircraft->mapBay)->bay,
            'terminal' => optional($aircraft->mapBay)->terminal,
            'time' => $aircraft->eibt,
            'time_label' => 'Est. On Bay',
            'distance' => null,
        ]);
    }

    foreach ($arrival as $aircraft) {
        $rows->push([
            'phase' => 'inbound',
            'callsign' => $aircraft->callsign,
            'operator' => strtoupper(substr($aircraft->callsign ?? '', 0, 3)),
            'ac' => $aircraft->ac,
            'dep' => $aircraft->dep,
            'bay' => optional($aircraft->mapBay)->bay,
            'terminal' => optional($aircraft->mapBay)->terminal,
            'time' => $aircraft->elt,
            'time_label' => 'Est. Landing',
            'distance' => $aircraft->distance,
        ]);
    }

    $phaseMeta = [
        'inbound' => ['label' => 'Inbound', 'class' => 'oz-fids-status--inbound'],
        'taxiing' => ['label' => 'Taxiing', 'class' => 'oz-fids-status--taxiing'],
        'boarding' => ['label' => 'Now Boarding', 'class' => 'oz-fids-status--boarding'],
    ];
@endphp

<div class="oz-fids">
    <div class="oz-fids-scroll">
        <div class="oz-fids-row oz-fids-head">
            <div class="oz-fids-col oz-fids-col--status">Status</div>
            <div class="oz-fids-col oz-fids-col--flight">Flight</div>
            <div class="oz-fids-col oz-fids-col--type">Type</div>
            <div class="oz-fids-col oz-fids-col--origin">Origin</div>
            <div class="oz-fids-col oz-fids-col--bay">Bay</div>
            <div class="oz-fids-col oz-fids-col--dist">Dist.</div>
            <div class="oz-fids-col oz-fids-col--time">Time</div>
        </div>

        @forelse ($rows as $row)
            @php $meta = $phaseMeta[$row['phase']]; @endphp
            <div class="oz-fids-row">
                <div class="oz-fids-col oz-fids-col--status">
                    <span class="oz-fids-status {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                </div>
                <div class="oz-fids-col oz-fids-col--flight">
                    <span class="oz-fids-callsign">{{ $row['callsign'] }}</span>
                    @if(!empty($airlines[$row['operator']]))
                        <span class="oz-fids-airline">{{ $airlines[$row['operator']] }}</span>
                    @endif
                </div>
                <div class="oz-fids-col oz-fids-col--type">{{ $row['ac'] ?? '—' }}</div>
                <div class="oz-fids-col oz-fids-col--origin">{{ $row['dep'] ?? '—' }}</div>
                <div class="oz-fids-col oz-fids-col--bay">
                    @if($row['bay'])
                        <span class="oz-fids-bay">{{ $row['bay'] }}</span>
                        @if($row['terminal'])
                            <span class="oz-fids-terminal">{{ $row['terminal'] }}</span>
                        @endif
                    @else
                        <span class="oz-fids-bay oz-fids-bay--tba">TBA</span>
                    @endif
                </div>
                <div class="oz-fids-col oz-fids-col--dist">{{ $row['distance'] !== null ? $row['distance'].' NM' : '—' }}</div>
                <div class="oz-fids-col oz-fids-col--time">
                    @if($row['time'])
                        <span class="oz-fids-time">{{ \Carbon\Carbon::parse($row['time'])->format('H:i') }}z</span>
                        <span class="oz-fids-time-label">{{ $row['time_label'] }}</span>
                    @else
                        <span class="oz-fids-time">—</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="oz-fids-empty">No aircraft currently tracked inbound to {{ $icao }}</div>
        @endforelse
    </div>
</div>
