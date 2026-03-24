@extends('layouts.app')

@section('content')
    <h1>Airport Admin View</h1>
    <p>View all airports maintained by OzBays. <i>Data is updated hourly via the <u>airports.json</u> file housed on the OzBays Servers.</i></p>

    <table id="dataTable" class="table table-hover" style="text-align: center; font-size: 12px;">
            <thead>
                <tr>
                    <th scope="col">Airport Name</th>
                    <th scope="col"># of Bays</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($airports as $airport)
                    <tr>  
                        <td>{{$airport->name}} Airport ({{$airport->icao}})</td>
                        <td>{{$airport->allBays()->count()}}</td>
                        <td>
                            <b>
                                @if($airport->status == "disabled") <x style="color: red">Disabled</x> @endif
                                @if($airport->status == "testing") <x style="color: rgb(255, 179, 0)">In Testing</x> @endif
                                @if($airport->status == "active") <x style="color: green">Active</x> @endif
                            </b>
                        </td>
                        <td><a href="{{route('dashboard.admin.airport.view', [$airport->icao])}}">View Info</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
@endsection