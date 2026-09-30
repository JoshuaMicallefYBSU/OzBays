@extends('layouts.app')

@section('content')
    <h1>Contributor Applications</h1>
    <p>Review applications to join the Data Maintaining Team. Approving an application gives the user the <b>Contributor</b> role.</p>

    <h3 class="mt-4">Outstanding <span class="badge badge-warning">{{$pending->count()}}</span></h3>

    @forelse($pending as $application)
        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title mb-1">{{$application->user?->fullName('FLC') ?? 'Unknown user '.$application->user_id}}</h5>
                        <small class="text-muted">Applied {{$application->created_at->format('d/m/Y @ h:i A')}}</small>
                    </div>
                    <div class="d-flex">
                        <form action="{{route('dashboard.admin.applications.approve', $application)}}" method="POST" class="mr-2">
                            @csrf
                            <input type="submit" class="btn btn-success btn-sm" value="Approve">
                        </form>
                        <form action="{{route('dashboard.admin.applications.reject', $application)}}" method="POST">
                            @csrf
                            <input type="submit" class="btn btn-danger btn-sm" value="Reject">
                        </form>
                    </div>
                </div>

                <h6 class="mt-3 mb-1">What they want to work on</h6>
                <p style="white-space: pre-line;">{{$application->description}}</p>

                <h6 class="mb-1">Real world experience</h6>
                <p class="mb-0" style="white-space: pre-line;">{{$application->experience ?: 'None provided'}}</p>
            </div>
        </div>
    @empty
        <p class="text-muted">No outstanding applications.</p>
    @endforelse

    <h3 class="mt-5">Recently Reviewed</h3>
    <table class="table table-hover" style="text-align: center">
        <thead>
            <tr>
                <th scope="col">Applicant</th>
                <th scope="col">Outcome</th>
                <th scope="col">Reviewed By</th>
                <th scope="col">Reviewed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reviewed as $application)
                <tr>
                    <td>{{$application->user?->fullName('FLC') ?? $application->user_id}}</td>
                    <td>
                        @if($application->status == 'approved') <b style="color: green">Approved</b> @else <b style="color: red">Rejected</b> @endif
                    </td>
                    <td>{{$application->reviewer?->fullName('FL') ?? 'N/A'}}</td>
                    <td>{{$application->reviewed_at?->format('d/m/Y @ h:i A')}}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">No reviewed applications yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
