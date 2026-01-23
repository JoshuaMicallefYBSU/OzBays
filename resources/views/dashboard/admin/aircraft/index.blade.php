@extends('layouts.app')

@section('content')
    <h3>OzBays - Aircraft.json View</h3>

    <p>
        Shows the current defined Aircraft in the JSON. This list is housed on the OzBays Server, and must be updated via the codebase.<br>
        Should an aircraft be missing from the below list and is flown inbound to an arrival airport, the Aircraft will default to a B738.<br><br>
        <b>E.g. </b>if a A306 flies into an airport. Aircraft in the same category will all be included as the same type and available for bay selection, as well as all groups below it. 
    </p>

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
