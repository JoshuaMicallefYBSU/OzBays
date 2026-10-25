@extends('layouts.app')

@section('content')
    <h1>Feedback #{{ $feedback->id }}</h1>
    <p>
        From <b>{{ $feedback->user?->fullName('FLC') ?? $feedback->user_id }}</b>
        on {{ $feedback->created_at->format('d/m/Y \a\t H:i') }}z
        @if($feedback->flight) &middot; Flight: <b>{{ $feedback->flight }}</b> @endif
        &middot;
        @if($feedback->isClosed())
            <span class="oz-status-pill oz-status-pill--disabled">Closed</span>
        @else
            <span class="oz-status-pill oz-status-pill--active">Open</span>
        @endif
    </p>

    <div class="pb-3 d-flex justify-content-between align-items-center">
        <a href="{{ route('dashboard.feedback.index') }}"> <i class="fas fa-arrow-left"></i> All Feedback</a>

        @if($feedback->isClosed())
            <form action="{{ route('dashboard.feedback.reopen', $feedback) }}" method="POST">
                @csrf
                <button type="submit" class="btn oz-btn oz-btn-ghost">Reopen Ticket</button>
            </form>
        @else
            <form action="{{ route('dashboard.feedback.close', $feedback) }}" method="POST">
                @csrf
                <button type="submit" class="btn oz-btn oz-btn-ghost">Close Ticket</button>
            </form>
        @endif
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title">Scores <span class="text-muted" style="font-size: 14px;">(average {{ $feedback->averageRating() }}/10)</span></h4>
            @foreach($questions as $key => $question)
                @php($score = $feedback->ratings[$key] ?? null)
                <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom: 1px solid var(--oz-border);">
                    <span>{{ $question }}</span>
                    <b style="color: var(--oz-amber); white-space: nowrap; margin-left: 12px;">{{ $score ?? '-' }}/10</b>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title">Written Feedback</h4>
            {{-- Sanitised by HtmlSanitizer when it was saved --}}
            <div class="oz-feedback-body">{!! $feedback->body !!}</div>
        </div>
    </div>

    <h4 class="mt-4">Replies</h4>
    @include('feedback._thread', ['feedback' => $feedback])

    <div class="oz-card-dark mt-3">
        <form action="{{ route('dashboard.feedback.reply', $feedback) }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="body">Reply to {{ $feedback->user?->fullName('FL') ?? 'this user' }}</label>
                <textarea name="body" id="body" rows="4" class="form-control @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn oz-btn oz-btn-primary">Send Reply</button>
        </form>
    </div>
@endsection
