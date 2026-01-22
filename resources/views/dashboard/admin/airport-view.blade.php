@extends('layouts.app')

@section('content')

    @include('partials.message', [
        'type' => 'info',
        'message' => "NOTE: Information on this page is not yet editable. This is for viewing purposes only."
    ])

    <div class="row">
        <div class="col-md-7">
            <h1>Airport Info - {{$airport->name}} ({{$airport->icao}})</h1>
            <p>Airport & Bay Information for {{$airport->name}} Airport</p>

            <div class="pb-3">
                <a href="{{route('dashboard.admin.airport.all')}}" style="color: black;"> <i class="fas fa-arrow-left"></i> See All Airports</a>
            </div>
        </div>
        <div class="col-md-5">
            {{-- Create quick disable button incase something goes wrong in production --}}
            @if($airport->status == 'testing' && Auth::user()->hasRole('Maintainer'))
                <a href="#/sudden-disable">
                    <img style="height: 125px; width: auto;" src="https://png.pngtree.com/png-clipart/20231114/original/pngtree-panic-button-shutdown-picture-image_13260836.png">
                </a>
                <b>< Disables Airport</b>
            @endif
        </div>
    </div>


    {{-- Airport Information --}}
    <div class="row">
        <div class="col-md-8">

            {{-- AIrport Information Row --}}
            <h3>Airport Info</h3>
            <div class="row">
                {{-- Left Collum --}}
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">Name</span>
                        </div>
                        <input type="text" class="form-control" value="{{$airport->name}} Airport">
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">Status</span>
                        </div>
                        <input type="text" class="form-control" value="{{$airport->status}}">
                    </div>
                </div>

                {{-- Middle Collum --}}
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">lat</span>
                        </div>
                        <input type="text" class="form-control" value="{{$airport->lat}}">
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">lon</span>
                        </div>
                        <input type="text" class="form-control" value="{{$airport->lon}}">
                    </div>
                </div>

                {{-- Right Collum --}}
                <div class="col-md-4">
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">elt</span>
                        </div>
                        <input type="text" class="form-control" value="{{$airport->eibt_variable}}">
                    </div>

                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text" id="basic-addon1">taxi</span>
                        </div>
                        <input type="text" class="form-control" value="{{$airport->taxi_time}}">
                    </div>
                </div>
            </div>

            <h3 class="mt-2">Airport Bays</h3>
                <table id="dataTable" class="table table-hover" style="text-align: center">
                    <thead>
                        <tr>
                            <th scope="col">Identifier</th>
                            <th scope="col">Max Aircraft</th>
                            <th scope="col">Operator</th>
                            <th scope="col">Bay Type</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($airport->allBays as $bay)
                            <tr>
                                <td>{{$bay->bay}}</td>
                                <td>{{$bay->aircraft}}</td>
                                <td>{{$bay->operators ?? 'all operators'}}</td>
                                <td>{{$bay->pax_type ?? 'all types'}}</td>
                                <td><a href="{{route('dashboard.admin.bay.view', [$airport->icao, $bay->bay])}}">View Info</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

        </div>

        {{-- Map Section --}}
        <div class="col-md-4">
            <iframe
                src="{{ route('mapIndex', ['lat' => $airport->lat, 'lon' => $airport->lon, 'zoom' => '12.5', 'hide_info' => true]) }}"
                style="width:100%; height:500px; border:none;"
            ></iframe>
        </div>

    </div>
    
@endsection