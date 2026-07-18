@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1>Data changes</h1><p class="text-muted mb-0">Submitted data stays out of the live tables until a maintainer approves it.</p></div>
    <a class="btn btn-primary" href="{{ route('dashboard.admin.data.import') }}">Import airport JSON</a>
</div>
<table class="table table-hover">
    <thead><tr><th>Submitted</th><th>Target</th><th>By</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($changes as $change)
        <tr><td>{{ $change->created_at->format('d M Y H:i') }}</td><td><strong>{{ $change->target }}</strong></td><td>{{ $change->submitter?->fullName('FL') ?? $change->submitted_by }}</td><td><span class="badge badge-{{ $change->status === 'approved' ? 'success' : ($change->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($change->status) }}</span></td><td><a href="{{ route('dashboard.admin.data.show', $change) }}">Review</a></td></tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted">No data changes have been submitted.</td></tr>
    @endforelse
    </tbody>
</table>
{{ $changes->links() }}
@endsection
