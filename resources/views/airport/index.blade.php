@extends('layouts.app')

@section('content')

<div class="oz-section-title"><i class="fa fa-plane"></i> Airports</div>
<p>All OzBays airports appear here. Select an airport below to view its live arrival/departure ladder.</p>

<table id="dataTable" class="table table-hover" style="text-align: center; font-size: 12px;">
    <thead>
        <tr>
            <th width="25%">ICAO</th>
            <th width="25%">Name</th>
            <th width="25%">Status</th>
            <th width="25%">Action</th>
        </tr>
    </thead>
    <tbody>
        @foreach($airports as $airport)
            <tr>
                <td>{{$airport->icao}}</td>
                <td>{{$airport->name}} Airport</td>
                <td>
                    @if($airport->status == 'disabled')
                        <span class="oz-status-pill oz-status-pill--disabled">Disabled</span>
                    @elseif($airport->status == 'testing')
                        <span class="oz-status-pill oz-status-pill--testing">In Testing</span>
                    @elseif($airport->status == 'active')
                        <span class="oz-status-pill oz-status-pill--active">Active</span>
                    @endif
                </td>
                <td><a href="{{route('airportLadder', [$airport->icao])}}"><i class="fa fa-plane-arrival"></i> View Ladder</a></td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="oz-card-dark mt-4">
    <div class="oz-section-title" style="margin-bottom: 0.75rem;"><i class="fa fa-info-circle"></i> Airport Status</div>
    <ul class="mb-0">
        <li class="mb-2"><span class="oz-status-pill oz-status-pill--active">Active</span> Bay calculations are completed. Bay assignments are sent to OzStrips &amp; Hoppie for controller/pilot notification.</li>
        <li class="mb-2"><span class="oz-status-pill oz-status-pill--testing">In Testing</span> Bay calculations are completed. Hoppie messages are sent only to those on the testing list, with no OzStrips notification.</li>
        <li><span class="oz-status-pill oz-status-pill--disabled">Disabled</span> Flights are not registered on the system for this airport, so no calculations are completed.</li>
    </ul>
</div>

@endsection
