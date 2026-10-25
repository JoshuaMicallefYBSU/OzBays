@forelse($events as $event)
    <div class="oz-reply {{ in_array($event->event_type, ['assigned', 'reassigned', 'live_bay_match']) ? 'oz-reply--dev' : '' }} mb-2">
        <div class="oz-reply-meta">
            <b>{{ strtoupper(str_replace('_', ' ', $event->event_type)) }}</b>
            @if($event->status) &middot; {{ $event->status }} @endif
            &middot; {{ $event->created_at->format('d/m/Y H:i:s') }}z
        </div>
        @if(! empty($event->payload))
            <pre class="oz-log-payload">{{ json_encode($event->payload, JSON_PRETTY_PRINT) }}</pre>
        @endif
    </div>
@empty
    <p class="text-muted mb-0">No events logged.</p>
@endforelse
