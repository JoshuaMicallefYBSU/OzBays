@extends('layouts.app')

@section('content')
    <h3>OzBays - Aircraft.json View</h3>

    <p>
        Shows the current defined Aircraft in the JSON. This list is housed on the OzBays Server, and must be updated via the codebase.<br>
        Should an aircraft be missing from the below list and is flown inbound to an arrival airport, the Aircraft will default to a B738.<br><br>
        <b>E.g. </b>if a A306 flies into an airport. Aircraft in the same category will all be included as the same type and available for bay selection, as well as all groups below it. 
    </p>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-2">Missing Aircraft Types</h5>
            <p class="text-muted mb-3">Aircraft types flown into an OzBays airport that aren't in the JSON above, sorted by how often they've been missed. These fall back to a B738 bay selection until added.</p>

            @if($missingTypes->isEmpty())
                <span class="text-muted">No missing aircraft types recorded &mdash; nice!</span>
            @else
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Times Missed</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($missingTypes as $missing)
                            <tr>
                                <td style="font-family: monospace;">{{ $missing->type }}</td>
                                <td>{{ $missing->count }}</td>
                                <td>{{ optional($missing->last_seen_at)->format('d M Y, h:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="row">
        @foreach($groups as $group)
            <div class="col-12 col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">

                        <h5 class="card-title mb-2">Group: {{ $group['key'] }}</h5>

                        @if(!empty($group['description']))
                            <p class="text-muted mb-3">{{ $group['description'] }}</p>
                        @endif

                        @if(!empty($group['aircraft']))
                            @php
                                $columns = collect($group['aircraft'])->chunk(15);
                            @endphp

                            {{-- Keep all columns on ONE row; scroll horizontally if needed --}}
                            <div style="display:flex; flex-wrap:nowrap; gap:24px; overflow-x:auto; padding-bottom:6px;">
                                @foreach($columns as $col)
                                    <div style="min-width:70px;">
                                        <ul class="list-unstyled mb-0" style="font-family: monospace; font-size: 0.9rem; line-height: 1.4;">
                                            @foreach($col as $ac)
                                                <li style="white-space: nowrap; padding: 2px 0;">{{ $ac }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted">No aircraft listed.</span>
                        @endif

                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
