@extends('layouts.app')

@section('content')

<div class="pb-3">
    <a href="{{route('notifications.index')}}"> <i class="fas fa-arrow-left"></i> Back to Notifications</a>
</div>

<div class="card">
    <div class="card-body">
        <h3 class="card-title"><i class="fa {{ $notification->data['icon'] ?? 'fa-bell' }}"></i> {{ $notification->data['title'] }}</h3>
        <p class="text-muted mb-1">
            {{ $notification->created_at->format('d/m/Y @ h:i A') }}
            @if(!empty($notification->data['sender']))
                &middot; From {{ $notification->data['sender'] }}
            @endif
        </p>

        <hr>

        <p>{!! nl2br(e($notification->data['body'])) !!}</p>
    </div>
</div>

@endsection
