@extends('layouts.app')

@section('content')
    <div class="oz-section-title"><i class="fa fa-database"></i> Bay Assignment Logs</div>
    <p>Every event the bay assignment pipeline has ever logged (candidates considered, live bay matches, Hoppie messages, reassignments, diversions, errors...), broken down by airport. Developer access only.</p>

    @if($airports->isEmpty())
        <div class="oz-card-dark">
            <p class="mb-0">No assignment events have been logged yet.</p>
        </div>
    @else
        <table id="dataTable" class="table table-hover" style="text-align: center">
            <thead>
                <tr>
                    <th scope="col">Airport</th>
                    <th scope="col">Callsigns Logged</th>
                    <th scope="col">Last Activity</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($airports as $airport)
                    @php($lastActivity = \Carbon\Carbon::parse($airport->last_activity))
                    <tr>
                        <td>{{ $airport->airport }}</td>
                        <td>{{ $airport->callsign_count }}</td>
                        <td data-order="{{ $lastActivity->timestamp }}">{{ $lastActivity->format('d/m/Y H:i') }}z</td>
                        <td><a href="{{ route('dashboard.logs.show', $airport->airport) }}">View Log</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
