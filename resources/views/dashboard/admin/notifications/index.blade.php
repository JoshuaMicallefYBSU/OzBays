@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h3 class="mb-0">Sent Notifications</h3>
        <a href="{{route('dashboard.admin.notifications.create')}}" class="btn oz-btn oz-btn-primary btn-sm">Send Notification</a>
    </div>
    <p class="text-muted">Every send &mdash; manual or automatic (e.g. new articles) &mdash; grouped by batch, with how many recipients have seen it.</p>

    <div class="card mt-3">
        <div class="card-body p-0">
            @forelse($batches as $batch)
                @php
                    $unseen = $batch->total - $batch->read_count;
                    $percentSeen = $batch->total > 0 ? round(($batch->read_count / $batch->total) * 100) : 0;
                @endphp
                <div class="oz-notification-item">
                    <div class="oz-notification-item-link oz-notification-item-static">
                        <i class="fa {{ $batch->icon ?: 'fa-bell' }}"></i>
                        <div class="oz-notification-item-body">
                            <div class="oz-notification-item-title">{{ $batch->title }}</div>
                            <div class="oz-notification-item-message text-muted">{{ $batch->message }}</div>
                            <div class="oz-notification-item-time text-muted">
                                {{ \Illuminate\Support\Carbon::parse($batch->sent_at)->format('d M Y, h:i A') }}
                                @if(!empty($batch->sender))
                                    &middot; Sent by {{ $batch->sender }}
                                @endif
                            </div>

                            <div class="oz-batch-stats">
                                <div class="oz-batch-bar">
                                    <div class="oz-batch-bar-fill" style="width: {{ $percentSeen }}%;"></div>
                                </div>
                                <span class="oz-batch-stat-text">
                                    <strong>{{ $batch->read_count }}</strong> seen &middot;
                                    <strong>{{ $unseen }}</strong> unseen
                                    <span class="text-muted">of {{ $batch->total }}</span>
                                    &middot;
                                    <a href="{{ route('dashboard.admin.notifications.recipients', [
                                        'type' => $batch->type,
                                        'title' => $batch->title,
                                        'message' => $batch->message,
                                        'url' => $batch->url,
                                        'icon' => $batch->icon,
                                        'sender' => $batch->sender,
                                        'bucket' => $batch->bucket,
                                    ]) }}">who hasn't seen this?</a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0 p-3">No notifications have been sent yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-3">
        {{ $batches->links() }}
    </div>
@endsection
