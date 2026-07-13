<a class="nav-link" href="" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    <i class="fa fa-bell"></i>
    @if($unread_count > 0)
        <span class="badge badge-danger oz-notification-badge">{{ $unread_count > 9 ? '9+' : $unread_count }}</span>
    @endif
</a>

<div class="dropdown-menu dropdown-menu-right oz-notification-menu">
    <div class="d-flex justify-content-between align-items-center oz-notification-header">
        <span>Notifications</span>
        @if($unread_count > 0)
            <form action="{{ route('notifications.readAll') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-link btn-sm p-0">Mark all read</button>
            </form>
        @endif
    </div>

    @forelse($notifications as $notification)
        @php $hasDestination = !empty($notification->data['url']) || !empty($notification->data['body']); @endphp
        <div class="oz-notification-item {{ is_null($notification->read_at) ? 'unread' : '' }}" data-notification-id="{{ $notification->id }}">
            @if($hasDestination)
                <a class="oz-notification-item-link" href="{{ route('notifications.read', $notification->id) }}">
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
            @else
                <div class="oz-notification-item-link oz-notification-item-static">
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
                </div>
            @endif

            @if(is_null($notification->read_at) && !$hasDestination)
                <button type="button" class="oz-notification-mark-read" title="Mark as read">
                    <i class="fa fa-check"></i>
                </button>
            @endif
        </div>
    @empty
        <span class="dropdown-item disabled">No notifications yet</span>
    @endforelse

    <div class="dropdown-divider"></div>
    <a class="dropdown-item text-center" href="{{ route('notifications.index') }}">View All</a>
</div>
