@extends('layouts.app')

@php
    use App\Services\AirportImporter;

    $show = fn ($value) => $value === null || $value === '' ? '—' : $value;
@endphp

@section('content')
    <h1>Review Import &mdash; {{ $airport['icao'] }}</h1>
    <p>
        @if($diff['new'])
            <strong>{{ $airport['name'] }}</strong> is a new airport. Confirm to add it with {{ $diff['bay_count'] }} bays.
        @else
            Changes uploading this file will make to <strong>{{ $airport['name'] }}</strong>. Nothing has changed yet &mdash; confirm below to apply them.
        @endif
    </p>

    <div class="pb-3">
        <a href="{{ route('dashboard.admin.airport.import') }}"><i class="fas fa-arrow-left"></i> Upload a different file</a>
    </div>

    @unless($diff['new'])
        <div class="oz-import-summary mb-4">
            <span class="oz-import-chip is-added"><strong>{{ count($diff['added']) }}</strong> bays added</span>
            <span class="oz-import-chip is-changed"><strong>{{ count($diff['changed']) }}</strong> bays changed</span>
            <span class="oz-import-chip is-removed"><strong>{{ count($diff['removed']) }}</strong> bays removed</span>
            <span class="oz-import-chip"><strong>{{ count($diff['airport']) }}</strong> airport settings changed</span>
        </div>
    @endunless

    @if(! $hasChanges)
        @include('partials.message', ['type' => 'info', 'message' => 'This file matches what is already in OzBays - there is nothing to change.'])
    @endif

    {{-- Airport settings --}}
    @if($diff['airport'])
        <div class="oz-card-dark mb-4">
            <h5 class="oz-card-heading">Airport Settings</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Setting</th><th>Current</th><th>New</th></tr></thead>
                <tbody>
                    @foreach($diff['airport'] as $field => [$old, $new])
                        <tr>
                            <td>{{ AirportImporter::AIRPORT_FIELDS[$field] ?? $field }}</td>
                            <td class="oz-mono oz-import-old">{{ $show($old) }}</td>
                            <td class="oz-mono oz-import-new">{{ $show($new) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Changed bays --}}
    @if($diff['changed'])
        <div class="oz-card-dark mb-4">
            <h5 class="oz-card-heading">Changed Bays ({{ count($diff['changed']) }})</h5>
            <table class="table table-sm mb-0">
                <thead><tr><th>Bay</th><th>Field</th><th>Current</th><th>New</th></tr></thead>
                <tbody>
                    @foreach($diff['changed'] as $bayCode => $fields)
                        @foreach($fields as $field => [$old, $new])
                            <tr>
                                @if($loop->first)<td class="oz-mono" rowspan="{{ count($fields) }}"><strong>{{ $bayCode }}</strong></td>@endif
                                <td>{{ AirportImporter::BAY_FIELDS[$field] ?? $field }}</td>
                                <td class="oz-mono oz-import-old">{{ $show($old) }}</td>
                                <td class="oz-mono oz-import-new">{{ $show($new) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Added bays --}}
    @if($diff['added'])
        <div class="oz-card-dark mb-4">
            <h5 class="oz-card-heading">{{ $diff['new'] ? 'Bays' : 'Added Bays' }} ({{ count($diff['added']) }})</h5>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Bay</th><th>Long Name</th><th>Aircraft</th><th>Type</th><th>Operators</th><th>Priority</th><th>Terminal</th></tr></thead>
                    <tbody>
                        @foreach($diff['added'] as $bayCode => $bay)
                            <tr>
                                <td class="oz-mono oz-import-new"><strong>{{ $bayCode }}</strong></td>
                                <td>{{ $show($bay['long_name']) }}</td>
                                <td class="oz-mono">{{ $show($bay['aircraft']) }}</td>
                                <td>{{ $show($bay['pax_type']) }}</td>
                                <td class="oz-mono">{{ $show($bay['operators']) }}</td>
                                <td>{{ $show($bay['priority']) }}</td>
                                <td>{{ $show($bay['terminal']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Removed bays --}}
    @if($diff['removed'])
        <div class="oz-card-dark mb-4">
            <h5 class="oz-card-heading">Removed Bays ({{ count($diff['removed']) }})</h5>
            <p>These bays aren't in the uploaded file and will be deleted from OzBays.</p>
            <table class="table table-sm mb-0">
                <thead><tr><th>Bay</th><th>Aircraft</th><th>Currently</th></tr></thead>
                <tbody>
                    @foreach($diff['removed'] as $bayCode => $bay)
                        <tr>
                            <td class="oz-mono oz-import-old"><strong>{{ $bayCode }}</strong></td>
                            <td class="oz-mono">{{ $show($bay->aircraft) }}</td>
                            <td>
                                @if($bay->callsign)
                                    <span class="oz-bay-mismatch"><i class="fa fa-exclamation-triangle"></i> {{ $bay->status == 2 ? 'Occupied' : 'Assigned' }} by {{ $bay->callsign }}</span>
                                @else
                                    <span class="text-muted">Free</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($hasChanges)
        <form action="{{ route('dashboard.admin.airport.import.confirm', $token) }}" method="POST" class="d-flex align-items-center" style="gap: 1rem;">
            @csrf
            <button type="submit" class="btn oz-btn oz-btn-primary"><i class="fa fa-check"></i> {{ $diff['new'] ? 'Import Airport' : 'Apply Changes' }}</button>
            <a href="{{ route('dashboard.admin.airport.import') }}" class="btn oz-btn oz-btn-ghost">Cancel</a>
        </form>
    @endif
@endsection
