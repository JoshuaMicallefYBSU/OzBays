@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-md-3">
        <p></p>
    </div>

    <div class="col-md-6">
        <h2>Airport List</h2>
        <p>All OzBays airports will appear here. Select from the below options to view the indiviual airport information.</p>
        <table class="table" style="text-align: center; font-size: 12px;">
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
                            <b>
                                @if($airport->status == "disabled") <x style="color: red">Disabled</x> @endif
                                @if($airport->status == "testing") <x style="color: rgb(255, 179, 0)">In Testing</x> @endif
                                @if($airport->status == "active") <x style="color: green">Active</x> @endif
                            </b>
                        </td>
                        <td><a href="{{route('airportLadder', [$airport->icao])}}">View Ladder</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h4>Airport Status:</h4>
        <ul>
            <li><x style="color: green;"><b>Active:</b></x> Bay calculations are completed. Bay assignments are sent to OzStrips & Hoppies for controller/pilot notification.</li>
            <li><x style="color: rgb(255, 179, 0)"><b>In Testing:</b></x> Bay calculations are completed. Hoppie messages sent only to those on testing list. No OzStrips notification.</li>
            <li><x style="color: red"><b>Disabled:</b></x> Flights are not registered on the system for this airport, so no calculations are completed.</li>
        </ul>
    </div>
</div>



@endsection