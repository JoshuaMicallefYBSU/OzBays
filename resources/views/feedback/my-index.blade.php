@extends('layouts.app')

@section('content')
    <div class="oz-section-title"><i class="fa fa-comment"></i> My Feedback</div>
    <p>Feedback you've submitted to the OzBays team. Select one to see any replies or add a comment.</p>

    @if($feedback->isEmpty())
        <div class="oz-card-dark">
            <p class="mb-0">You haven't submitted any feedback yet. <a href="{{ route('feedback.create') }}">Provide some feedback</a> to get started.</p>
        </div>
    @else
        <table id="dataTable" class="table table-hover" style="text-align: center">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Submitted</th>
                    <th scope="col">Flight</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($feedback as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td data-order="{{ $item->created_at->timestamp }}">{{ $item->created_at->format('d/m/Y H:i') }}z</td>
                        <td>{{ $item->flight ?? '-' }}</td>
                        <td>
                            @if($item->isClosed())
                                <span class="oz-status-pill oz-status-pill--disabled">Closed</span>
                            @else
                                <span class="oz-status-pill oz-status-pill--active">Open</span>
                            @endif
                        </td>
                        <td><a href="{{ route('feedback.my.show', $item) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
