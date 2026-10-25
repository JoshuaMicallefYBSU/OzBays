@forelse($feedback->replies as $reply)
    @php($isDev = $reply->user_id !== $feedback->user_id)
    <div class="oz-reply {{ $isDev ? 'oz-reply--dev' : '' }}">
        <div class="oz-reply-meta">
            <b>{{ $reply->user?->fullName('FLC') ?? 'Unknown' }}</b>
            @if($isDev)<span class="oz-status-pill" style="margin-left: 6px;">Developer</span>@endif
            &middot; {{ $reply->created_at->format('d/m/Y \a\t H:i') }}z
        </div>
        <div class="oz-reply-body">{{ $reply->body }}</div>
    </div>
@empty
    <p class="text-muted">No replies yet.</p>
@endforelse
