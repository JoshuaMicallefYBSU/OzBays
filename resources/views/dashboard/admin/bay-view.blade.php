@extends('layouts.app')

@section('content')

<h3>Bay Information</h3>
<p>Hover over right-hand options to see what the option does, and how to modify it.</p>
<div class="pb-3">
    <a href="{{route('dashboard.admin.airport.view', [$bay->airport])}}" style="color: black;"> <i class="fas fa-arrow-left"></i> Airport Info</a>
</div>

@include('partials.message', [
        'type' => 'info',
        'message' => "NOTE: Information on this page is not yet editable. This is for viewing purposes only."
])

<div class="row">
    <div class="col-md-8">
        <div class="row">
            <div class="col-md-4">
                {{-- Bay ID --}}
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">Bay ID</span>
                    </div>
                    <input type="text" class="form-control" value="{{$bay->bay}}">
                </div>

                {{-- Lat --}}
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">lat</span>
                    </div>
                    <input type="text" class="form-control" value="{{$bay->lat}}">
                </div>

                {{-- Lon --}}
                <div class="input-group mb-3">
                    <div class="input-group-prepend">
                        <span class="input-group-text" id="basic-addon1">lon</span>
                    </div>
                    <input type="text" class="form-control" value="{{$bay->lon}}">
                </div>
            </div>

            <div class="col-md-8">
                {{-- Max Aircraft --}}
                <div class="input-group mb-3 hover-info">
                    <div class="input-group-prepend">
                        <span class="input-group-text">Max AC</span>
                    </div>
                    <input type="text" class="form-control" value="{{ $bay->aircraft }}">

                    <div class="hover-box">
                        Maximum Aircraft Type the bay is available to be assigned for. Similar aircraft sizes, e.g. B738/A20N will be included as options.
                    </div>
                </div>

                {{-- Assignment Priority --}}
                <div class="input-group mb-3 hover-info">
                    <div class="input-group-prepend">
                        <span class="input-group-text">Max Priority</span>
                    </div>
                    <input type="text" class="form-control" value="{{ $bay->priority }}">

                    <div class="hover-box">
                        Bay Assignment priority. 1-9 scale. All priority 1 bays will be sorted, then level 2 etc.
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="input-group mb-3 hover-info">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Operators</span>
                            </div>
                            <input type="text" class="form-control" value="{{ $bay->operators ?? 'any'}}">

                            <div class="hover-box">
                                Operators, in order of bay preference. E.g. "VOZ, JST" and "JST, QFA" will prioritise the 2nd bay higher in the bay assignment logic.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="input-group mb-3 hover-info">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Bay Type</span>
                            </div>
                            <input type="text" class="form-control" value="{{ $bay->pax_type ?? 'DOM & INTL'}}">

                            <div class="hover-box">
                                Bay Type, either "DOM", "INTL", or null for either option.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <iframe
            src="{{ route('mapIndex', ['lat' => $bay->lat, 'lon' => $bay->lon, 'zoom' => '16', 'hide_info' => true]) }}"
            style="width:100%; height:500px; border:none;"
        ></iframe>
    </div>
</div>

<style>
    .hover-info {
        position: relative;
    }

    .hover-box {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: #212529;
        color: #fff;
        padding: 6px 10px;
        border-radius: 4px;
        font-size: 0.85rem;
        white-space: nowrap;
        z-index: 10;
    }

    .hover-info:hover .hover-box {
        display: block;
    }
</style>

@endsection