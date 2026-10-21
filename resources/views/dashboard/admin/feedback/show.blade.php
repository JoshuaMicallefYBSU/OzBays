@extends('layouts.app')

@section('content')
    <h1>Feedback #{{ $feedback->id }}</h1>
    <p>
        From <b>{{ $feedback->user?->fullName('FLC') ?? $feedback->user_id }}</b>
        on {{ $feedback->created_at->format('d/m/Y \a\t H:i') }}z
        @if($feedback->flight) &middot; Flight: <b>{{ $feedback->flight }}</b> @endif
    </p>

    <div class="pb-3">
        <a href="{{ route('dashboard.feedback.index') }}"> <i class="fas fa-arrow-left"></i> All Feedback</a>
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
@endsection
