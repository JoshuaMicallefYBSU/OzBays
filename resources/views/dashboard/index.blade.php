@extends('layouts.app')

@section('content')

<div class="row">
        <div class="col-md-4">
            <div class="card mt-4">
                <div class="card-body">
                    <h3 class="card-title">My Actions</h3>

                    {{-- Link Discord --}}
                    @if(Auth::user()->discord_user_id == null)
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.discord.link')}}" class="card-link" style="color: black;">
                                <h6 class="card-title mb-1"><i class="fa fa-sign-in"></i> Link Your Discord Account</h6>
                                <small class="text-muted">Link your discord with your profile!</small>
                            </a>
                        </div>
                    </li>

                    @elseif(Auth::user()->discord_user_id !== null && Auth::user()->discord_member == false)
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.discord.join')}}" class="card-link" style="color: black;">
                                <h6 class="card-title mb-1"><i class="fa fa-sign-in"></i> Join our Discord</h6>
                                <small class="text-muted">Join the OzBays discord!</small>
                            </a>
                        </div>
                    </li>

                    @elseif(Auth::user()->discord_user_id !== null && Auth::user()->discord_member == true)
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <!-- Circular Profile Picture -->
                                <img src="{{Auth::user()->discord_avatar}}" alt="Profile Picture" style="width: 70px; height: 70px; border-radius: 50%; margin-right: 10px;">
                                <div>
                                    <!-- Name and Username -->
                                    <h6 class="card-title mb-1" style="margin: 0;">{{Auth::user()->fname}} {{ucfirst(substr(Auth::user()->lname, 0, 1))}} - {{Auth::user()->id}}</h6>
                                    <small class="text-muted"><a href="{{route('dashboard.discord.unlink')}}">Unlink Account</a></small>
                                </div>
                            </div>
                        </div>
                    </li>
                    
                    @endif

                </div>
            </div>
        </div>
    </div>

@endsection