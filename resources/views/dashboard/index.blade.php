@extends('layouts.app')

@section('content')

<h3>Welcome to your OzBays Dashboard, {{Auth::user()->fullName('F')}}</h3>
<p>Your one stop shop for everything for your OzBays Experience,</p>

@include('partials.message', [
    'type' => 'info',
    'message' => "Clearly, it's like a barren wasteland here. As OzBays gains in popularity and functionality, more and more options will appear here. 
    <br><i>Announcements for new functionality will be released in the OzBays Discord in the <u>#ozbays-changes</u> channel.</i>"
])

    <div class="row">
        <div class="col-md-8">
            <div class="card mt-4">
                <div class="card-body">
                    <h3 class="card-title">Flight Info</h3>

                    {{-- Flight Information --}}
                    <x id="flight-info"></x>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mt-4">
                <div class="card-body">
                    <h3 class="card-title">My Profile</h3>
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a disabled class="card-link" style="color: black; cursor:default">
                                <h6 class="card-title mb-1"><i class="fa fa-sign-in"></i> {{Auth::user()->fullName('FLC')}}</h6>
                                <small class="text-muted"><b>Role: </b>{{Auth::user()->highestRole()->name}}</small><br>
                                <small class="text-muted"><b>Discord: </b> @if(Auth::user()->discord_user_id == null)Not Linked @else Linked @endif</small>
                            </a>
                        </div>
                    </li>
                </div>
            </div>

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

<script>
    function loadLadder() {
        fetch('/partial/dashboard/flight-info')
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('flight-info');

                // Create temp wrapper
                const temp = document.createElement('div');
                temp.innerHTML = html;

                // Replace children in one operation
                container.replaceChildren(...temp.children);
            });
        }

        // Initial load
        loadLadder();

        // Refresh every 15s
        setInterval(loadLadder, 15000);
</script>

@endsection