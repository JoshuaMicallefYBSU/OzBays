@extends('layouts.app')

@section('content')
    <h1>Feedback</h1>
    <p>Feedback submitted through the public trial form. Scores are out of 10.</p>

    @php($all = $feedback->map->ratings)
    @if($feedback->isNotEmpty())
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title">Average Scores <span class="text-muted" style="font-size: 14px;">({{ $feedback->count() }} {{ Str::plural('response', $feedback->count()) }})</span></h4>
                <div class="row">
                    @foreach($questions as $key => $question)
                        @php($scores = $all->pluck($key)->filter(fn ($v) => is_numeric($v)))
                        <div class="col-md-4 mb-2">
                            <b style="font-size: 20px; color: var(--oz-amber);">{{ $scores->isNotEmpty() ? round($scores->avg(), 1) : '-' }}</b>
                            <small class="text-muted d-block">{{ $question }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <table id="dataTable" class="table table-hover" style="text-align: center">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Submitted</th>
                <th scope="col">User</th>
                <th scope="col">Flight</th>
                <th scope="col">Average</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($feedback as $item)
                <tr>
                    <td>{{ $item->id }}</td>
                    <td data-order="{{ $item->created_at->timestamp }}">{{ $item->created_at->format('d/m/Y H:i') }}z</td>
                    <td>{{ $item->user?->fullName('FLC') ?? $item->user_id }}</td>
                    <td>{{ $item->flight ?? '-' }}</td>
                    <td>{{ $item->averageRating() }}/10</td>
                    <td>
                        @if($item->isClosed())
                            <span class="oz-status-pill oz-status-pill--disabled">Closed</span>
                        @else
                            <span class="oz-status-pill oz-status-pill--active">Open</span>
                        @endif
                    </td>
                    <td><a href="{{ route('dashboard.feedback.show', $item) }}">View</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
