@if(Auth::user()->isFlying->callsign == null)
                        <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="" class="card-link" style="color: black; cursor: default">
                                    <h6 class="card-title mb-1"><i class="fa-solid fa-plane-slash"></i> Currently Offline</h6>
                                    <small class="text-muted">No flight detected within 1500NM of an airport serviced by OzBays</small>
                                </a>
                            </div>
                        </li>
@else
                        <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="" class="card-link" style="color: black; cursor: default">
                                    <h6 class="card-title mb-1"><i class="fa-solid fa-plane-arrival"></i> {{Auth::user()->isFlying->callsign}} | {{Auth::user()->isFlying->ac}}</h6>
                                    <small class="text-muted">Arriving at {{Auth::user()->isFlying->arr}} | {{Auth::user()->isFlying->distance}} NM Away</small><br>
                                    <small class="text-muted">
                                        @if(Auth::user()->isFlying->scheduled_bay == null)
                                            No Assigned Bay 
                                        @else
                                            Assigned bay {{Auth::user()->isFlying->mapBay->bay}} on Arrival | If unable, advise GND for alternate bay on first contact
                                        @endif
                                    </small>
                                </a>
                            </div>
                        </li>
@endif