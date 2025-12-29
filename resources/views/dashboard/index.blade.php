@extends('layouts.app')

@section('content')

<h3>Welcome to your OzBays Dashboard, {{Auth::user()->fullName('F')}}</h3>
<p>Your one stop shop for everything for your OzBays Experience,</p>

<div class="alert-wrapper auto-close">
    <div class="alert alert-info">
        Clearly, it's like a baron wasteland here. As OzBays gains in popularity and functionality, more and more options will appear here. <br><i>Announcements for new functionality will be released in the OzBays Discord.</i>
        <button type="button" class="alert-close">&times;</button>
    </div>
</div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mt-4">
                <div class="card-body">
                    <h3 class="card-title">Flight Info</h3>

                    {{-- Flight Information --}}
                    @if(Auth::user()->isFlying->callsign == null)
                        <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="" class="card-link" style="color: black; cursor: default">
                                    <h6 class="card-title mb-1"><i class="fa-solid fa-plane-slash"></i> Currently Offline</h6>
                                    <small class="text-muted">No flight detected within 1500NM of an airport serviced by OzBays</small>
                                </a>
                            </div>
                        </li>
                    @else
                        <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="" class="card-link" style="color: black; cursor: default">
                                    <h6 class="card-title mb-1"><i class="fa-solid fa-plane-arrival"></i> {{Auth::user()->isFlying->callsign}} | {{Auth::user()->isFlying->ac}}</h6>
                                    <small class="text-muted">Arriving at {{Auth::user()->isFlying->arr}} | {{Auth::user()->isFlying->distance}} NM Away</small><br>
                                    <small class="text-muted">
                                        @if(Auth::user()->isFlying->scheduled_bay == null)
                                            No Assigned Bay 
                                        @else
                                            Assigned bay {{Auth::user()->isFlying->mapBay->bay}} on Arrival | If unable, advise GND for alternate bay on first contact
                                        @endif
                                    </small>
                                </a>
                            </div>
                        </li>
                    @endif
                </div>
            </div>
        </div>

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
                                    <h5 class="card-title mb-1" style="margin: 0;">Discord Account</h5>
                                    <h6 class="card-title mb-1" style="margin: 0;">{{Auth::user()->fullName('FLC')}}</h6>
                                    <small class="text-muted"><a href="{{route('dashboard.discord.unlink')}}">Unlink Account</a></small>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endif

                    {{-- OzBays Settings --}}
                    {{-- <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.discord.link')}}" class="card-link" style="color: black;">
                                <h6 class="card-title mb-1"><i class="fa fa-cog"></i> OzBays Settings</h6>
                                <small class="text-muted">Edit your personal OzBays Preferences</small>
                            </a>
                        </div>
                    </li> --}}

                </div>
            </div>
        </div>
    </div>

@endsection