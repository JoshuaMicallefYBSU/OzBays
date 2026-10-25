@extends('layouts.app')

@section('content')
    <h1>Import Airport</h1>
    <p>Upload an airport JSON file to add a new airport, or to update an existing one. You'll see every change before anything is applied. The file is saved privately on the OzBays server and kept in sync hourly.</p>

    <div class="pb-3">
        <a href="{{route('dashboard.admin.airport.all')}}"> <i class="fas fa-arrow-left"></i> See All Airports</a>
    </div>

    <form action="{{route('dashboard.admin.airport.import.store')}}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label for="airport_file">Airport JSON File</label>
            <input type="file" name="airport_file" id="airport_file" class="form-control-file @error('airport_file') is-invalid @enderror" accept=".json,application/json" required>
            <small class="form-text text-muted">One airport per file. Uploading an airport that already exists replaces its settings and bays with the ones in the file.</small>
            @if($errors->has('airport_file'))
                <div class="invalid-feedback d-block">
                    <ul class="mb-0 pl-3">
                        @foreach($errors->get('airport_file') as $error)
                            @foreach((array) $error as $message)
                                <li>{{$message}}</li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <input type="submit" class="btn oz-btn oz-btn-primary" value="Review Changes">
    </form>

    <h4 class="mt-5">File Format</h4>
    <pre class="p-3" style="background: rgba(0,0,0,0.3); border-radius: 6px;"><code>{
    "icao": "YPAD",
    "name": "Adelaide",
    "lat": -34.94659219,
    "lon": 138.5248029,
    "settings": {
        "color": "#1A3B8E",
        "eibt_config": "1.4",
        "taxi_time": "10",
        "status": "testing",
        "update_time_utc": "0,2,4,6,8,10,12,14,20,22"
    },
    "long_name": {
        "D": "Domestic"
    },
    "parking": {
        "10B": {
            "lat": -34.93748019,
            "lon": 138.54092694,
            "Operator": null,
            "Terminal": "T1",
            "AC": "SF34",
            "Type": null,
            "Priority": 5,
            "Gate": "2"
        }
    }
}</code></pre>
@endsection
