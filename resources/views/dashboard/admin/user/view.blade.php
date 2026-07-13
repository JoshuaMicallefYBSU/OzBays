@extends('layouts.app')

@section('content')

    <div class="row">
        <div class="col-md-8">
            <h1>User Info - {{$user->fullName('FLC')}}</h1>
            <p>Profile & role information for this user.</p>

            <div class="pb-3">
                <a href="{{route('dashboard.admin.users.list')}}"> <i class="fas fa-arrow-left"></i> See All Users</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">

            <h3>Profile</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">CID</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->id}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Name</span>
                        </div>
                        <input type="text" class="form-control" value="{{trim($user->fname.' '.$user->lname)}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Initials</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->init}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Status</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->deleted ? 'Inactive' : 'Active'}}" readonly>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Discord</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->discord_username ?? 'Not Linked'}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Discord Member</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->discord_member ? 'Yes' : 'No'}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Email Subscribed</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->gdpr_subscribed_emails ? 'Yes' : 'No'}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Last Seen</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->last_seen ? $user->last_seen->format('d/m/Y @ h:i A').'Z' : 'Never'}}" readonly>
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text">Joined</span>
                        </div>
                        <input type="text" class="form-control" value="{{$user->created_at?->format('d/m/Y') ?? 'Unknown'}}" readonly>
                    </div>
                </div>
            </div>

            <h3 class="mt-2">Roles</h3>

            <div class="mb-3">
                @forelse($user->roles as $role)
                    <span class="badge badge-primary mr-2 mb-2" style="font-size: 14px; padding: 6px 10px;">
                        {{$role->name}}
                        @can('edit user data')
                            <form action="{{route('dashboard.admin.users.roles.remove', [$user, $role->name])}}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link p-0 ml-1 text-white" style="text-decoration:none; vertical-align: baseline;" title="Remove role" onclick="return confirm('Remove the {{$role->name}} role from {{$user->fullName('FL')}}?');">&times;</button>
                            </form>
                        @endcan
                    </span>
                @empty
                    <p class="text-muted">This user has no roles assigned.</p>
                @endforelse
            </div>

            @can('edit user data')
                @php
                    $assignableRoles = $roles->whereNotIn('name', $user->roles->pluck('name'));
                @endphp

                @if($assignableRoles->count())
                    <form action="{{route('dashboard.admin.users.roles.assign', $user)}}" method="POST" class="form-inline">
                        @csrf
                        <select name="role" class="form-control mr-2" required>
                            <option value="" disabled selected>Select a role...</option>
                            @foreach($assignableRoles as $role)
                                <option value="{{$role->name}}">{{$role->name}}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn oz-btn oz-btn-primary">Assign Role</button>
                    </form>
                @endif
            @endcan

        </div>
    </div>

@endsection
