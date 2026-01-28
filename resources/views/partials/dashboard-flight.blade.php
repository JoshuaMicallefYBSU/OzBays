@if(Auth::user()->isFlying->callsign == null)
                        <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a disabled class="card-link" style="color: black; cursor: default">
                                    <h6 class="card-title mb-1"><i class="fa-solid fa-plane-slash"></i> Currently Offline</h6>
                                    <small class="text-muted">No flight detected within 1500NM of an airport serviced by OzBays</small>
                                </a>
                            </div>
                        </li>
@else
                        <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <a disabled class="card-link" style="color: black; cursor: default">
                                    <h6 class="card-title mb-1"><i class="fa-solid fa-plane-arrival"></i> {{Auth::user()->isFlying->callsign}} | {{Auth::user()->isFlying->ac}} | {{Auth::user()->isFlying->alt}}ft | {{Auth::user()->isFlying->speed}}kts</h6>
                                    <small class="text-muted">Arriving at {{Auth::user()->isFlying->arr}} | {{Auth::user()->isFlying->distance}} NM Away</small><br>
                                    <small class="text-muted">
                                        @if(Auth::user()->isFlying->scheduled_bay == null)
                                            No Current OzBays Assigned Arrival Bay - Bay will be assigned 200NM from {{Auth::user()->isFlying->arr}}<br>
                                            @if(Auth::user()->isFlying->liveBay->scheduled_bay == null)
                                                No Real World Bay Assignment Info | Want a specific bay? Request it below!
                                            @else
                                                Real Aircraft scheduled to arrive at {{Auth::user()->isFlying->liveBay->bayInfo->terminal}}, {{Auth::user()->isFlying->liveBay->bayInfo->bay}} | Want a different bay? Request it below!
                                            @endif
                                        @else
                                            Assigned Bay {{Auth::user()->isFlying->mapBay->bay}} on Arrival | If unable, advise GND for alternate bay on first contact
                                        @endif
                                    </small>
                                </a>
                            </div>
                        </li>
@endif