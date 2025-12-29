<div class="row">
    <div class="col-md-6">
        <h3>Taxiing - To Bay (ARR ONLY)</h3>
        @if($taxing->isEmpty())
            <tr>  
                <td><b>No aircraft Taxiing for a Bay at {{$icao}}</b></td>  <br><br>  
            <tr>
        @else
        <table class="table" style="text-align: center; font-size: 12px;">
            <thead>
                <tr>
                    <th width="25%">Callsign</th>
                    <th width="25%">AC Type</th>
                    <th width="30%">Bay</th>
                </tr>
            </thead>
            <tbody>
                @foreach($taxing as $aircraft)
                        <tr>  
                            <td>{{$aircraft->callsign}}</td>
                            <td>{{$aircraft->ac}}</td>
                            <td>{{ $aircraft->mapBay->bay ?? 'No Assigned Bay' }}</td>
                        </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <br><br>

    <div class="col-md-6">
        <h3>Assigned Bay (Within 200NM)</h3>
        @if($arrival->isEmpty())
            <tr>  
                <td><b>No aircraft Airbourne within 200 NM of {{$icao}}</b></td>    
            <tr>
        @else
        <table class="table" style="text-align: center; font-size: 12px;">
            <thead>
                <tr>
                    <th width="25%">Callsign</th>
                    <th width="25%">AC Type</th>
                    <th width="25%">Distance</th>
                    <th width="30%">Bay</th>
                </tr>
            </thead>
            <tbody>
                @foreach($arrival as $aircraft)
                    <tr>  
                        <td>{{$aircraft->callsign}}</td>
                        <td>{{$aircraft->ac}}</td>
                        <td>{{$aircraft->distance}} NM</td>
                        <td>{{ $aircraft->mapBay->bay ?? 'No Assigned Bay' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

<br><br>

<h3>Parked - On Bay</h3>
@if($occupied_bays->isEmpty())
    <tr>  
        <td><b>No aircraft parked on the ground at {{$icao}} at a bay</b></td>    
    <tr>
@else
<table class="table" style="text-align: center; font-size: 12px;">
    <thead>
        <tr>
            <th width="50%">Callsign</th>
            <th width="50%">Bay</th>
        </tr>
    </thead>
    <tbody>
        @foreach($occupied_bays as $bay)
            <tr>  
                <td>{{$bay->callsign}}</td>
                <td>{{$bay->bay}}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif