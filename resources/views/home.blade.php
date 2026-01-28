@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-md-8" style="text-align: justify">

        <div class="row">
            <div class="card card-body">
                @if(Auth::guest())
                    <h2>Welcome to OzBays! - Coming in 2026!</h2>
                @else
                    <h2>Welcome to OzBays, {{Auth::user()->fullName('F')}}! - Coming in 2026!</h2>
                @endif
                <p>Automatic Bay Assignment for VATSIM Australia Pacific (VATPAC) Controlled Airports on the VATSIM Network. This system is still in active development, and is currently <b>not deployed</b> on the VATSIM Network.</p>
                <p>Utilise the Nav Bar in order to access the information for each Airport currently supported by the system, as well as a map showing all Aircraft currently being monitored by OzBays. This information is dynamic, and will change frequently.</p>
                <p>The system is still in Alpha Development, meaning the system is not being utilised by OzStrips, or sending Messages via the Hoppies Network. Over time, airports will be activated to assign bays to aircraft and advise the pilot/atc. This will be showed in the status section of the <a href="{{route('airportIndex')}}">Airports</a> view.</p>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6" style="padding-left: 0px;">
                <div class="card card-body" >
                    <h2>Live Stats</h2>
                    <p>Live Airport Activity</p>
                </div>
            </div>

            <div class="col-md-6" style="padding: 0px;">
                <div class="card card-body">
                    <h2>Live Stats</h2>
                    <p>See current network stats for airports served by OzBays.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4" style="text-align: justify">
        <div class="card card-body">
            <h2>Recent News</h2>
            <p>News articles are coming soon! Alongside many, many new features!</p>
        </div>

        <div class="card card-body mt-4">
            @if(Auth::guest())
                <h2>OzBays Discord</h2>
                <p>OzBays has a dedicated Discord Server for VATSIM Community Members. This server is a place for announcements, discussion, as well as feedback to be provided from the community directly to those developing & maintaining the program.</p>
                <p>Sign in with VATSIM SSO in order to link your Discord Account, to access the OzBays Discord Server.</p>
            @elseif(Auth::user()->discord_member == false)
                <h2>OzBays Discord</h2>
                <p>OzBays has a dedicated Discord Server for VATSIM Community Members. This server is a place for announcements, discussion, as well as feedback to be provided from the community directly to those developing & maintaining the program.</p>
                <p><b>Access your Dashboard and link your Discord Account to access the Server</b></p>
            @elseif(Auth::user()->discord_user_id !== null && Auth::user()->discord_member == true)
                <h2>OzBays Discord</h2>
                <p>You are already a member of the OzBays Server, use this to report any issues you come across, or recommend any potential new features to the OzBays Team!</p>
            @endif
        </div>
    </div>
</div>
@endsection