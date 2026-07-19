@extends('layouts.app')

@section('content')
<h2>The OzBays Team</h2>
<p>The people who build and maintain OzBays.</p>

<hr>

<h4>Dvelopment Team</h4>
<div class="row mt-3">
    @forelse($leadership as $user)
        <div class="col-md-3 col-sm-6 text-center mb-4">
            @if($user->discord_avatar)
                <img src="{{ $user->discord_avatar }}" alt="{{ $user->discord_username ?? $user->fullName('F') }}" style="width: 90px; height: 90px; border-radius: 50%;">
            @else
                <i class="fas fa-user-circle" style="font-size: 90px; color: #ccc;"></i>
            @endif
            <h5 class="mt-2 mb-0">{{ $user->discord_username ?? $user->fullName('F') }}</h5>
            <small class="text-muted">{{ $user->hasRole('Lead Developer') ? 'Lead Developer' : 'Developer' }}</small>
        </div>
    @empty
        <p class="text-muted">No leadership team members yet.</p>
    @endforelse
</div>

<hr>

<h4>Data Maintaining Team</h4>
<div class="row mt-3">
    @forelse($community as $user)
        <div class="col-md-3 col-sm-6 text-center mb-4">
            @if($user->discord_avatar)
                <img src="{{ $user->discord_avatar }}" alt="{{ $user->discord_username ?? $user->fullName('F') }}" style="width: 90px; height: 90px; border-radius: 50%;">
            @else
                <i class="fas fa-user-circle" style="font-size: 90px; color: #ccc;"></i>
            @endif
            <h5 class="mt-2 mb-0">{{ $user->discord_username ?? $user->fullName('F') }}</h5>
            <small class="text-muted">{{ $user->hasRole('Maintainer') ? 'Maintainer' : 'Contributor' }}</small>
        </div>
    @empty
        <p class="text-muted">No community team members yet.</p>
    @endforelse
</div>
@endsection
