@extends('layouts.app')

@section('content')

<h1>{{$airport->icao}} - {{$airport->name}} Airport Information</h1>
<x id="controller-info">
    
</x>

<script>
    const airportIcao = @json($airport->icao);

    function loadLadder() {
        fetch(`/api/v1/airport/ladder/${airportIcao}`)
            .then(res => res.text())
            .then(html => {
                document.getElementById('controller-info').innerHTML = html;
            });
    }

    // Run immediately on page load
    loadLadder();

    // Then run every 5 seconds
    setInterval(loadLadder, 15000);
</script>


@endsection