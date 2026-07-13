@extends('layouts.app')

@section('content')

<div class="d-flex justify-content-between align-items-center">
    <h3>Notifications</h3>
    @if($notifications->contains(fn($n) => is_null($n->read_at)))
        <form action="{{ route('notifications.readAll') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Mark all read</button>
        </form>
    @endif
</div>

<div class="card mt-4">
    <div class="card-body">
        @forelse($notifications as $notification)
            <a href="{{ route('notifications.read', $notification->id) }}" class="oz-notification-item d-flex list-group-item {{ is_null($notification->read_at) ? 'unread' : '' }}">
                <i class="fa {{ $notification->data['icon'] ?? 'fa-bell' }}"></i>
                <div class="oz-notification-item-body">
                    <div class="oz-notification-item-title">{{ $notification->data['title'] }}</div>
                    <div class="oz-notification-item-message text-muted">{{ $notification->data['message'] }}</div>
                    <div class="oz-notification-item-time text-muted">
                        {{ $notification->created_at->diffForHumans() }}
                        @if(!empty($notification->data['sender']))
                            &middot; From {{ $notification->data['sender'] }}
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <p class="text-muted mb-0">You don't have any notifications yet.</p>
        @endforelse
    </div>
</div>

<div class="mt-3">
    {{ $notifications->links() }}
</div>

@endsection
