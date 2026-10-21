@extends('layouts.app')

@section('content')

<style>
    .oz-feedback-slider { margin-bottom: 18px; }
    .oz-feedback-slider label { display: flex; justify-content: space-between; gap: 12px; font-weight: 500; }
    .oz-feedback-value {
        min-width: 44px;
        text-align: center;
        font-weight: 700;
        padding: 0 8px;
        border-radius: 999px;
        background: rgba(255, 199, 44, 0.15);
        color: var(--oz-amber);
    }
    .oz-feedback-scale { display: flex; justify-content: space-between; font-size: 11px; color: var(--oz-text-muted); }
    .oz-feedback-slider input[type=range] { accent-color: var(--oz-amber); }
</style>

<h1>Provide your Feedback!</h1>
<p>
    Thanks for trying OzBays! Your feedback goes straight to the OzBays team and helps us improve bay assignments before the full public launch.
    Tell us what worked, what didn't, and what you'd like to see.
</p>

<form action="{{ route('feedback.store') }}" method="POST" onsubmit="tinymce.triggerSave()">
    @csrf

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title">Quick Ratings</h4>
            <p class="text-muted">Drag each slider from 1 (poor) to 10 (excellent).</p>

            @foreach($questions as $key => $question)
                @php($value = old("ratings.{$key}", 5))
                <div class="oz-feedback-slider">
                    <label for="rating-{{ $key }}">
                        <span>{{ $question }}</span>
                        <span class="oz-feedback-value" id="rating-{{ $key }}-value">{{ $value }}</span>
                    </label>
                    <input type="range" class="custom-range" min="1" max="10" step="1"
                        id="rating-{{ $key }}" name="ratings[{{ $key }}]" value="{{ $value }}"
                        oninput="document.getElementById('rating-{{ $key }}-value').textContent = this.value">
                    <div class="oz-feedback-scale"><span>1</span><span>10</span></div>
                    @error("ratings.{$key}") <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title">Your Feedback</h4>

            <div class="form-group">
                <label for="flight">Which flight is this about? <span class="text-muted">(optional)</span></label>
                <input type="text" name="flight" id="flight" class="form-control @error('flight') is-invalid @enderror"
                    value="{{ old('flight') }}" maxlength="100" placeholder="e.g. QFA703 from YBCS into YMML, 21/10">
                @error('flight') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group mb-0">
                <label for="body">Answer any of the questions below that apply, and add anything else you'd like to tell us.</label>
                <textarea name="body" id="body" class="form-control @error('body') is-invalid @enderror">{{ old('body', view('feedback.lead-questions')->render()) }}</textarea>
                @error('body') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <input type="submit" class="btn oz-btn oz-btn-primary" value="Send Feedback">
</form>

<script>
    tinymce.init({
        selector: '#body',
        menubar: false,
        height: 520,
        plugins: 'lists link',
        toolbar: 'undo redo | bold italic | bullist numlist | link',
        skin: 'oxide-dark',
        content_css: 'dark',
    });
</script>
@endsection
