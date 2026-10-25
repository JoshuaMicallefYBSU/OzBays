@extends('layouts.app')

@section('content')
    <h1>Feedback #{{ $feedback->id }}</h1>
    <p>
        Submitted {{ $feedback->created_at->format('d/m/Y \a\t H:i') }}z
        @if($feedback->flight) &middot; Flight: <b>{{ $feedback->flight }}</b> @endif
        &middot;
        @if($feedback->isClosed())
            <span class="oz-status-pill oz-status-pill--disabled">Closed</span>
        @else
            <span class="oz-status-pill oz-status-pill--active">Open</span>
        @endif
    </p>

    <div class="pb-3">
        <a href="{{ route('feedback.my.index') }}"><i class="fas fa-arrow-left"></i> My Feedback</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title">Your Written Feedback</h4>
            {{-- Sanitised by HtmlSanitizer when it was saved --}}
            <div class="oz-feedback-body">{!! $feedback->body !!}</div>
        </div>
    </div>

    <h4 class="mt-4">Replies</h4>
    @include('feedback._thread', ['feedback' => $feedback])

    <div class="oz-card-dark mt-3">
        @if($feedback->isClosed())
            <p class="text-muted">This ticket is closed. Sending a reply will reopen it.</p>
        @endif
        <form action="{{ route('feedback.my.reply', $feedback) }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="body">Add a comment</label>
                <textarea name="body" id="body" rows="4" class="form-control @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn oz-btn oz-btn-primary">Send Reply</button>
        </form>
    </div>
@endsection
