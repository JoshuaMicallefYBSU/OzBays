@extends('layouts.app')

@section('content')

<h3>Welcome to your OzBays Dashboard, {{Auth::user()->fullName('F')}}</h3>
<p>Your one stop shop for everything for your OzBays Experience,</p>

    <div class="row">
        <div class="col-md-8">
            <div class="oz-card-dark mt-4">
                <div class="oz-section-title"><i class="fa fa-plane"></i> My Current Flight</div>

                {{-- Flight Information - refreshed every 15s --}}
                <x id="flight-info"></x>
            </div>

        </div>

        <div class="col-md-4">
            <div class="card mt-4">
                <div class="card-body">
                    <h3 class="card-title">My Profile</h3>

                {{-- User Card --}}
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a disabled class="card-link" style="cursor:default">
                                <h6 class="card-title mb-1"><i class="fa fa-user"></i> {{Auth::user()->fullName('FLC')}}</h6>
                                <small class="text-muted"><b>Role: </b>{{Auth::user()->highestRole()->name}}</small><br>
                                {{-- <small class="text-muted"><b>Discord: </b> @if(Auth::user()->discord_user_id == null)Not Linked @else Linked @endif</small> --}}
                            </a>
                        </div>
                    </li>

                {{-- Link Discord --}}
                    @if(Auth::user()->discord_user_id == null)
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.discord.link')}}" class="card-link">
                                <h6 class="card-title mb-1"><i class="fa fa-sign-in"></i> Link Your Discord Account</h6>
                                <small class="text-muted">Link your discord with your profile!</small>
                            </a>
                        </div>
                    </li>

                    @elseif(Auth::user()->discord_user_id !== null && Auth::user()->discord_member == false)
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.discord.join')}}" class="card-link">
                                <h6 class="card-title mb-1"><i class="fa fa-sign-in"></i> Join our Discord</h6>
                                <small class="text-muted">Join the OzBays discord!</small>
                            </a>
                        </div>
                    </li>

                    @elseif(Auth::user()->discord_user_id !== null && Auth::user()->discord_member == true)
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <!-- Circular Profile Picture -->
                                <img src="{{Auth::user()->discord_avatar}}" alt="Profile Picture" style="width: 70px; height: 70px; border-radius: 50%; margin-right: 10px;">
                                <div>
                                    <!-- Name and Username -->
                                    <h5 class="card-title mb-1" style="margin: 0;">Discord Account</h5>
                                    <h6 class="card-title mb-1" style="margin: 0;">{{Auth::user()->fullName('FLC')}}</h6>
                                    <small class="text-muted"><a href="{{route('dashboard.discord.unlink')}}">Unlink Account</a></small>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endif
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h3 class="card-title">My Actions</h3>

                {{-- OzBays Settings --}}
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.settings.index')}}" class="card-link">
                                <h6 class="card-title mb-1"><i class="fa fa-cog"></i> OzBays Settings</h6>
                                <small class="text-muted">Edit your personal OzBays Preferences</small>
                            </a>
                        </div>
                    </li>

                {{-- Contributor Application --}}
                    @if(\App\Http\Controllers\ContributorApplicationController::canApply(Auth::user()))
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{route('dashboard.contribute')}}" class="card-link">
                                <h6 class="card-title mb-1"><i class="fa fa-hands-helping"></i> Apply to Contribute</h6>
                                <small class="text-muted">Help maintain OzBays airport & bay data</small>
                            </a>
                        </div>
                    </li>
                    @elseif(\App\Http\Controllers\ContributorApplicationController::hasPendingApplication(Auth::user()))
                    <li style="margin-bottom: 5px; border-width: 1px; border-radius: 5px;" class="list-group-item">
                        <form action="{{route('dashboard.contribute.withdraw')}}" method="POST" onsubmit="return confirm('Rescind your contributor application?');">
                            @csrf
                            <button type="submit" class="btn btn-link card-link p-0 text-left">
                                <h6 class="card-title mb-1"><i class="fa fa-undo"></i> Rescind Contributor Application</h6>
                                <small class="text-muted">Your application is waiting for review</small>
                            </button>
                        </form>
                    </li>
                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- My Flights - every arrival into an OzBays airport, logged when it lands --}}
    <div class="oz-section-title mt-5" id="my-flights">
        <i class="fa fa-plane-arrival"></i> My Flights
        <span class="oz-section-sub">Arrivals into airports supported by OzBays from the 23OCT26</span>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="oz-card-dark h-100 oz-flight-log-card">
                <h5 class="oz-card-heading">My Flight History</h5>

                @if($flightLogs->isEmpty())
                    <p class="oz-chart-empty">No completed flights yet &mdash; fly into an OzBays airport and it'll show up here.</p>
                @else
                    {{-- Sized by the chart cards beside it - the script below shows as many rows as fit, then paginates --}}
                    <div class="oz-flight-log-body" id="flight-log-body">
                        <table class="table table-sm oz-flight-log-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Callsign</th>
                                    <th>Airline</th>
                                    <th>Route</th>
                                    <th title="Bay you parked at - with the OzBays assigned bay if it was different">Arrival Bay</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($flightLogs as $log)
                                    <tr title="{{ $log->created_at->format('d M Y H:i') }}z">
                                        <td class="oz-mono text-muted">{{ strtoupper($log->created_at->format('dMy')) }}</td>
                                        <td class="oz-mono">{{ $log->callsign }}</td>
                                        <td title="{{ $log->airlineInfo?->name }}">{{ $log->airline ?? '—' }}</td>
                                        <td class="oz-mono">{{ $log->departure ?? '????' }}<span class="text-muted"> - </span>{{ $log->arrival }}</td>
                                        <td class="oz-mono">
                                            {{-- Where they parked, falling back to the assigned bay if parking wasn't recorded --}}
                                            {{ $log->parked_bay ?? $log->assigned_bay ?? '—' }}
                                            @if($log->parked_bay !== null && $log->assigned_bay !== null && $log->parked_bay !== $log->assigned_bay)
                                                <span class="oz-bay-mismatch">({{ $log->assigned_bay }})</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="oz-flight-log-footer">
                        <nav aria-label="Completed flights pages"><ul class="pagination pagination-sm" id="flight-log-pager"></ul></nav>
                        <small class="oz-flight-log-key"><span class="oz-bay-mismatch">Amber</span> = parked at a different bay to the one OzBays assigned</small>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="oz-card-dark h-100">
                <h5 class="oz-card-heading">Aircraft Flown</h5>
                @include('partials.donut-chart', ['chart' => $aircraftChart, 'label' => 'Aircraft flown', 'emptyText' => 'Your aircraft will show here after your first flight.'])
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="oz-card-dark h-100">
                <h5 class="oz-card-heading">Airlines Flown</h5>
                @include('partials.donut-chart', ['chart' => $airlineChart, 'label' => 'Airlines flown', 'emptyText' => 'Your airlines will show here after your first airline flight.'])
                @if($nonAirlineFlights > 0 && $airlineChart['total'] > 0)
                    <p class="oz-chart-note">{{ $nonAirlineFlights }} {{ Str::plural('flight', $nonAirlineFlights) }} without an airline callsign not shown.</p>
                @endif
            </div>
        </div>
    </div>

<script>
    // Completed Flights - fill the card (its height is set by the chart cards beside it), then paginate the rest.
    // On phones the cards stack, so it falls back to 10 per page.
    (function () {
        const body = document.getElementById('flight-log-body');
        const pager = document.getElementById('flight-log-pager');
        if (!body || !pager) return;

        const rows = Array.from(body.querySelectorAll('tbody tr'));
        const STACKED_PAGE_SIZE = 10;
        let page = 0;
        let perPage = STACKED_PAGE_SIZE;

        function measure() {
            if (window.matchMedia('(max-width: 767.98px)').matches) return STACKED_PAGE_SIZE;

            rows.forEach(row => row.style.display = '');
            const headHeight = body.querySelector('thead').offsetHeight;
            const rowHeight = rows[0].offsetHeight || 1;

            return Math.max(1, Math.floor((body.clientHeight - headHeight) / rowHeight));
        }

        function pageLink(label, target, { active = false, disabled = false, aria = null } = {}) {
            const li = document.createElement('li');
            li.className = 'page-item' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = label;
            if (aria) a.setAttribute('aria-label', aria);
            a.addEventListener('click', e => {
                e.preventDefault();
                if (!disabled && !active) { page = target; render(); }
            });
            li.appendChild(a);
            return li;
        }

        function render() {
            const pages = Math.max(1, Math.ceil(rows.length / perPage));
            page = Math.min(page, pages - 1);

            rows.forEach((row, i) => {
                row.style.display = i >= page * perPage && i < (page + 1) * perPage ? '' : 'none';
            });

            pager.replaceChildren();
            if (pages === 1) return;

            pager.appendChild(pageLink('&lsaquo;', page - 1, { disabled: page === 0, aria: 'Previous' }));
            for (let p = 0; p < pages; p++) {
                // First, last and the pages either side of the current one; gaps become an ellipsis
                if (p === 0 || p === pages - 1 || Math.abs(p - page) <= 1) {
                    pager.appendChild(pageLink(String(p + 1), p, { active: p === page }));
                } else if (pager.lastChild?.dataset.gap !== '1') {
                    const gap = pageLink('&hellip;', p, { disabled: true });
                    gap.dataset.gap = '1';
                    pager.appendChild(gap);
                }
            }
            pager.appendChild(pageLink('&rsaquo;', page + 1, { disabled: page === pages - 1, aria: 'Next' }));
        }

        function layout() {
            const firstShown = page * perPage; // keep the same flights in view when the page size changes
            perPage = measure();
            page = Math.floor(firstShown / perPage);
            render();
        }

        let resizeTimer;
        window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(layout, 100); });
        window.addEventListener('load', layout); // fonts and charts change the card height once loaded
        layout();
    })();
</script>

<script>
    function loadLadder() {
        fetch('/partial/dashboard/flight-info')
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('flight-info');

                // Create temp wrapper
                const temp = document.createElement('div');
                temp.innerHTML = html;

                // Replace children in one operation
                container.replaceChildren(...temp.children);
            });
        }

        // Initial load
        loadLadder();

        // Refresh every 15s
        setInterval(loadLadder, 15000);
</script>

@endsection