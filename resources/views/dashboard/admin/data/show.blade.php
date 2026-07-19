@extends('layouts.app')

@section('content')
<a href="{{ route('dashboard.admin.data.index') }}">&larr; All changes</a>
<h1 class="mt-3">{{ $change->target }} airport change</h1>
<p>Submitted by {{ $change->submitter?->fullName('FL') ?? $change->submitted_by }} on {{ $change->created_at->format('d M Y H:i') }}. This definition contains {{ count($change->payload['bays'] ?? []) }} bays.</p>
<dl class="row"><dt class="col-sm-3">Airport</dt><dd class="col-sm-9">{{ $change->payload['airport']['name'] }}</dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ ucfirst($change->status) }}</dd></dl>
<details class="mb-4"><summary>Inspect normalised JSON</summary><pre class="bg-light p-3 mt-2" style="max-height:30rem;overflow:auto">{{ json_encode($change->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
@if($change->status === 'pending' && auth()->user()->can('approve changes'))
<form method="POST" action="{{ route('dashboard.admin.data.approve', $change) }}" class="mb-3">@csrf<div class="form-group"><label for="review_note">Review note (optional for approval)</label><textarea class="form-control" id="review_note" name="review_note" rows="3"></textarea></div><button class="btn btn-success">Approve and publish</button></form>
<form method="POST" action="{{ route('dashboard.admin.data.reject', $change) }}">@csrf<div class="form-group"><label for="reject_note">Reason for rejection</label><textarea class="form-control" id="reject_note" name="review_note" rows="3" required></textarea></div><button class="btn btn-danger">Reject</button></form>
@elseif($change->reviewed_at)
<div class="alert alert-secondary">Reviewed by {{ $change->reviewer?->fullName('FL') ?? $change->reviewed_by }}: {{ $change->review_note ?: 'No note provided.' }}</div>
@endif
@endsection
