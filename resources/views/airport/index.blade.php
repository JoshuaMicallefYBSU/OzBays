@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-md-3">
        <p></p>
    </div>

    <div class="col-md-6">
        <h2>Airport Options</h2>
        <p>All OzBays airports will appear here. Select from the below options to view the indiviual airport information.</p>
        <table class="table" style="text-align: center; font-size: 12px;">
            <thead>
                <tr>
                    <th width="20%">ICAO</th>
                    <th width="50%">Name</th>
                    <th width="30%">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($airports as $airport)
                    <tr>  
                        <td>{{$airport->icao}}</td>
                        <td>{{$airport->name}} Airport</td>
                        <td><a href="{{route('airportLadder', [$airport->icao])}}">View Ladder</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>



@endsection