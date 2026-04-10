<div class="row mt-4">
    <div class="col-md-6">
        <h4 class="font-weight-bold">Airport Inbound Info</h4>
        <ul class="list-unstyled">
        @php $index = 1; @endphp
            @foreach ($stats_inbound as $gnd)
                <li class="mb-1">
                    <div class="d-flex flex-row">
                        <span class="font-weight-bold blue-text" style="font-size: 1.9em;">
                            @if ($index == 1)
                                <i class="fas fa-trophy fa-fw" style="color: #FFC107;"></i> {{-- gold --}}
                            @elseif ($index == 2)
                                <i class="fas fa-trophy fa-fw" style="color: #607D8B;"></i> {{-- silver --}}
                            @elseif ($index == 3)
                                <i class="fas fa-trophy fa-fw" style="color: #795548;"></i> {{-- bronze --}}
                            @endif
                        </span>
                        <p class="mb-0 ml-1">
                            <span style="font-size: 1.4em;">
                                    <div class="d-flex flex-column ml-2">
                                        <h5 class="fw-400">{{ $gnd->icao }} | {{ $gnd->name }} Airport</h5>
                                        <p>{{$gnd->stats_inbound}} currently inbound to {{ $gnd->name }}</p>
                                    </div>
                            </span>
                        </p>
                    </div>
                </li>
                @php $index++; @endphp
            @endforeach
            
            @if (count($stats_inbound) < 1)
                <p>No Aircraft Inbound to OzBays Airports</p>
            @endif
        </ul>
    </div>

    <div class="col-md-6">
        <h4 class="font-weight-bold">Airport GND Movements</h4>
        <ul class="list-unstyled">
        @php $index = 1; @endphp
            @foreach ($stats_ground as $gnd)
                <li class="mb-1">
                    <div class="d-flex flex-row">
                        <span class="font-weight-bold blue-text" style="font-size: 1.9em;">
                            @if ($index == 1)
                                <i class="fas fa-trophy fa-fw" style="color: #FFC107;"></i> {{-- gold --}}
                            @elseif ($index == 2)
                                <i class="fas fa-trophy fa-fw" style="color: #607D8B;"></i> {{-- silver --}}
                            @elseif ($index == 3)
                                <i class="fas fa-trophy fa-fw" style="color: #795548;"></i> {{-- bronze --}}
                            @endif
                        </span>
                        <p class="mb-0 ml-1">
                            <span style="font-size: 1.4em;">
                                    <div class="d-flex flex-column ml-2">
                                        <h5 class="fw-400">{{ $gnd->icao }} | {{ $gnd->name }} Airport</h5>
                                        <p>{{$gnd->stats_ground}} currently on the ground at {{ $gnd->name }}</p>
                                    </div>
                            </span>
                        </p>
                    </div>
                </li>
                @php $index++; @endphp
            @endforeach
            
            @if (count($stats_ground) < 1)
                <p>No Ground Movements at OzBays Airports</p>
            @endif
        </ul>
    </div>
</div>