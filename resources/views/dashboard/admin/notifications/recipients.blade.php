@extends('layouts.app')

@section('content')
    <div class="pb-3">
        <a href="{{ route('dashboard.admin.notifications.index') }}"><i class="fas fa-arrow-left"></i> Back to Sent Notifications</a>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h3 class="mb-0"><i class="fa {{ $icon ?: 'fa-bell' }} mr-2"></i>{{ $title }}</h3>
    </div>
    <p class="text-muted">{{ $message }}</p>

    <div class="card mt-3">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Read At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipients as $recipient)
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.admin.users.view', $recipient->user) }}">{{ $recipient->user->fullName('FLC') }}</a>
                            </td>
                            <td>
                                @if($recipient->read_at)
                                    <span class="badge badge-success">Seen</span>
                                @else
                                    <span class="badge badge-secondary">Unseen</span>
                                @endif
                            </td>
                            <td>
                                {{ $recipient->read_at ? \Illuminate\Support\Carbon::parse($recipient->read_at)->format('d M Y, h:i A') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted p-3">No recipients found for this notification.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
