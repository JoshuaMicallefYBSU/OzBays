@extends('layouts.app')

@section('content')
    <div class="oz-section-title"><i class="fa fa-database"></i> Assignment Log - {{ $icao }}</div>
    <p>Every callsign the system has ever registered for {{ $icao }}, with its most recently recorded status. Expand a row to see the raw event data - bay candidates considered before randomisation, live bay matches, Hoppie messages, reassignments and anything else logged for it.</p>

    <div class="pb-3">
        <a href="{{ route('dashboard.logs.index') }}"><i class="fas fa-arrow-left"></i> All Airports</a>
    </div>

    <form action="{{ route('dashboard.logs.show', $icao) }}" method="GET" class="form-inline mb-3">
        <input type="text" name="search" class="form-control mr-2" style="min-width: 260px;" placeholder="Search callsign..." value="{{ $search }}">
        <button type="submit" class="btn oz-btn oz-btn-primary mr-2"><i class="fa fa-search"></i> Search</button>
        @if($search !== '')
            <a href="{{ route('dashboard.logs.show', $icao) }}" class="btn oz-btn oz-btn-ghost">Clear</a>
        @endif
    </form>

    @if($callsigns->isEmpty())
        <div class="oz-card-dark">
            @if($search !== '')
                <p class="mb-0">No callsigns matching "{{ $search }}" have been logged for {{ $icao }}.</p>
            @else
                <p class="mb-0">No events have been logged for {{ $icao }} yet.</p>
            @endif
        </div>
    @else
        <table class="table table-hover" style="text-align: center">
            <thead>
                <tr>
                    <th scope="col">Callsign</th>
                    <th scope="col">First Seen</th>
                    <th scope="col">Last Activity</th>
                    <th scope="col">Final Status</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($callsigns as $row)
                    <tr>
                        <td>{{ $row->callsign }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->first_seen)->format('d/m/Y H:i') }}z</td>
                        <td>{{ \Carbon\Carbon::parse($row->last_seen)->format('d/m/Y H:i') }}z</td>
                        <td>{{ $row->final_status ?? '-' }}</td>
                        <td>
                            <a href="#" class="oz-log-toggle" data-url="{{ route('dashboard.logs.events', [$icao, $row->callsign]) }}" data-target="#events-{{ $loop->index }}">
                                <i class="fa fa-chevron-down"></i> Details
                            </a>
                        </td>
                    </tr>
                    <tr id="events-{{ $loop->index }}" class="d-none">
                        <td colspan="5" style="text-align: left;">
                            <div class="oz-log-events-container">Loading&hellip;</div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $callsigns->links('pagination::bootstrap-4') }}
    @endif

    <script>
        document.querySelectorAll('.oz-log-toggle').forEach(toggle => {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();

                const targetRow = document.querySelector(this.dataset.target);
                const container = targetRow.querySelector('.oz-log-events-container');

                targetRow.classList.toggle('d-none');

                if (!targetRow.classList.contains('d-none') && !container.dataset.loaded) {
                    fetch(this.dataset.url)
                        .then(res => res.text())
                        .then(html => {
                            container.innerHTML = html;
                            container.dataset.loaded = '1';
                        });
                }
            });
        });
    </script>
@endsection
