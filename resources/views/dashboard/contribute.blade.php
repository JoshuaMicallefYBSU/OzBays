@extends('layouts.app')

@section('content')
    <h1>Help Maintain OzBays</h1>
    <p>Apply to join the Data Maintaining Team and help keep airport & bay information accurate.</p>

    <div class="pb-3">
        <a href="{{route('dashboard.index')}}"> <i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    @if($onTeam)
        @include('partials.message', ['type' => 'info', 'message' => "You're already part of the OzBays team - no need to apply!"])
    @elseif($pending)
        @include('partials.message', ['type' => 'info', 'message' => 'Your application submitted on '.$pending->created_at->format('d/m/Y').' is waiting for review.'])
    @else
        <form action="{{route('dashboard.contribute.store')}}" method="POST">
            @csrf

            <div class="form-group">
                <label for="description">What would you like to work on?</label>
                <textarea name="description" id="description" rows="5" class="form-control @error('description') is-invalid @enderror" required maxlength="5000" placeholder="E.g. Keeping bay data for YMML up to date, adding new airports...">{{old('description')}}</textarea>
                @error('description') <div class="invalid-feedback">{{$message}}</div> @enderror
            </div>

            <div class="form-group">
                <label for="experience">Real World Experience (optional)</label>
                <textarea name="experience" id="experience" rows="3" class="form-control @error('experience') is-invalid @enderror" maxlength="5000" placeholder="E.g. Airline ground staff, ramp operations, ATC...">{{old('experience')}}</textarea>
                @error('experience') <div class="invalid-feedback">{{$message}}</div> @enderror
            </div>

            <input type="submit" class="btn oz-btn oz-btn-primary" value="Submit Application">
        </form>
    @endif
@endsection
