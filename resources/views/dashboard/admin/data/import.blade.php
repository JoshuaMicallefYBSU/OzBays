@extends('layouts.app')

@section('content')
<h1>Import airport data</h1>
<p>Upload the existing OzBays format (an <code>Airports</code> object containing airport settings and parking), or a single object keyed by ICAO. Each airport becomes a separate approval request.</p>
<div class="alert alert-info">Nothing in this upload becomes live until a maintainer approves it.</div>
<form method="POST" action="{{ route('dashboard.admin.data.import.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="form-group"><label for="file">JSON file</label><input id="file" class="form-control-file @error('file') is-invalid @enderror" type="file" name="file" accept="application/json,.json" required>@error('file')<div class="text-danger">{{ $message }}</div>@enderror</div>
    <button class="btn btn-primary" type="submit">Validate and submit</button>
</form>
@endsection
