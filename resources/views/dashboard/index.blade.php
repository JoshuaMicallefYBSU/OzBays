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

            {{-- Bay Requests - only shown while flying into an OzBays airport. Rendered by the script below. --}}
            <div class="oz-card-dark mt-4 oz-bayreq d-none" id="bay-requests" data-url="{{ route('dashboard.bayRequests.show') }}" data-save-url="{{ route('dashboard.bayRequests.save') }}">
                <div class="oz-section-title">
                    <i class="fa-solid fa-square-parking"></i> Request a Bay
                    <span class="oz-section-sub" id="bay-req-sub"></span>
                </div>

                <p class="oz-bayreq-intro" id="bay-req-intro"></p>

                <div class="oz-bayreq-lock d-none" id="bay-req-lock"></div>

                <ol class="oz-bayreq-list" id="bay-req-list" aria-label="Requested bays, first choice first"></ol>
                <p class="oz-bayreq-empty d-none" id="bay-req-empty">No bays requested yet. OzBays will choose one for you.</p>

                <div class="oz-bayreq-controls" id="bay-req-controls">
                    <div class="oz-bayreq-add">
                        <select class="custom-select" id="bay-req-select" aria-label="Bay to request"></select>
                        <button type="button" class="btn oz-bayreq-add-btn" id="bay-req-add"><i class="fa-solid fa-plus"></i> Add</button>
                        <button type="button" class="btn oz-bayreq-map-btn" id="bay-req-map-open"><i class="fa-solid fa-map-location-dot"></i> Select from map</button>
                    </div>
                    <div class="oz-bayreq-footer">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="bay-req-all">
                            <label class="custom-control-label" for="bay-req-all">Show every bay my aircraft fits</label>
                        </div>
                        <small class="oz-bayreq-saved" id="bay-req-saved" aria-live="polite"></small>
                    </div>
                </div>
            </div>

            {{-- Bay Requests map picker - ground view of the arrival airport, bays the aircraft fits only --}}
            <div class="oz-baymap d-none" id="bay-map" role="dialog" aria-modal="true" aria-labelledby="bay-map-title" data-token="{{ config('services.mapbox.token') }}">
                <div class="oz-baymap-panel">
                    <div class="oz-baymap-head">
                        <div>
                            <div class="oz-baymap-title" id="bay-map-title">Select bays from the map</div>
                            <small class="text-muted" id="bay-map-sub"></small>
                        </div>
                        <div class="oz-baymap-tools">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="bay-map-all">
                                <label class="custom-control-label" for="bay-map-all">Show every bay my aircraft fits</label>
                            </div>
                            <button type="button" class="oz-bayreq-action" id="bay-map-close" title="Close" aria-label="Close map"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <div class="oz-baymap-body">
                        <div class="oz-baymap-canvas" id="bay-map-canvas"></div>
                        <div class="oz-baymap-legend">
                            <span><i class="oz-baymap-dot is-available"></i> Available</span>
                            <span><i class="oz-baymap-dot is-reserved"></i> Reserved</span>
                            <span><i class="oz-baymap-dot is-occupied"></i> Occupied</span>
                            <span><i class="oz-baymap-dot is-selected"></i> Your choice</span>
                            <span class="oz-baymap-legend-other"><i class="oz-baymap-dot is-other"></i> Outside normal allocation</span>
                        </div>
                    </div>

                    <div class="oz-baymap-foot">
                        <div class="oz-baymap-choices" id="bay-map-choices"></div>
                        <div class="oz-baymap-foot-right">
                            <small class="oz-bayreq-saved" id="bay-map-saved" aria-live="polite"></small>
                            <button type="button" class="btn oz-bayreq-add-btn" id="bay-map-done">Done</button>
                        </div>
                    </div>
                </div>
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
    // Bay Requests - up to 3 bays in order of preference, drag (or the arrows) to reorder. Every change saves straight away.
    (function () {
        const card = document.getElementById('bay-requests');
        if (!card) return;

        const list = document.getElementById('bay-req-list');
        const select = document.getElementById('bay-req-select');
        const addBtn = document.getElementById('bay-req-add');
        const showAll = document.getElementById('bay-req-all');
        const savedNote = document.getElementById('bay-req-saved');
        const SHOW_ALL_KEY = 'ozbays.bayRequests.showAll';
        const POLL_MS = 30000;

        let state = null;
        let order = [];       // requested bay ids, first choice first
        let saving = false;
        let dragging = null;

        showAll.checked = localStorage.getItem(SHOW_ALL_KEY) === '1';

        function el(tag, className, text) {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined && text !== null) node.textContent = text;
            return node;
        }

        function optionById(id) {
            return state.options.find(o => o.id === id);
        }

        function groupLabel(group) {
            return {
                own: state.flight.operator + ' bays',
                open: 'Open bays',
                shared: 'Shared bays (open to all operators)',
                other: 'Other bays (outside normal allocation)',
            }[group];
        }

        function render() {
            if (!state || !state.available) {
                card.classList.add('d-none');
                return;
            }
            card.classList.remove('d-none');

            const f = state.flight;
            document.getElementById('bay-req-sub').textContent = f.callsign + (f.aircraft ? ' · ' + f.aircraft : '') + ' into ' + f.arrival;
            document.getElementById('bay-req-intro').textContent =
                'Pick up to ' + state.max + ' bays at ' + (f.airport_name || f.arrival) + ' and drag them into your order of preference. ' +
                'When OzBays assigns your bay it tries your first choice first, then your second and third. ' +
                'If none of them are free, it assigns a bay the normal way.';

            const lock = document.getElementById('bay-req-lock');
            if (state.locked) {
                let text = state.lock_reason;
                if (state.assigned_bay) {
                    text = 'Assigned bay: ' + state.assigned_bay.name + (state.assigned_bay.requested_position
                        ? ' (your choice ' + state.assigned_bay.requested_position + ')'
                        : (order.length ? ' (none of your requested bays were free)' : ''));
                }
                lock.textContent = text;
                lock.classList.remove('d-none');
            } else {
                lock.classList.add('d-none');
            }

            renderList();
            renderSelect();
            document.getElementById('bay-req-controls').classList.toggle('d-none', state.locked);
        }

        function renderList() {
            list.replaceChildren();
            document.getElementById('bay-req-empty').classList.toggle('d-none', order.length > 0 || state.locked);

            order.forEach((id, index) => {
                const bay = optionById(id);
                if (!bay) return;

                const li = el('li', 'oz-bayreq-item');
                li.dataset.id = id;
                li.draggable = !state.locked;

                if (!state.locked) {
                    const handle = el('span', 'oz-bayreq-handle');
                    handle.innerHTML = '<i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>';
                    handle.title = 'Drag to reorder';
                    li.appendChild(handle);
                }

                li.appendChild(el('span', 'oz-bayreq-rank', String(index + 1)));

                const main = el('div', 'oz-bayreq-main');
                main.appendChild(el('span', 'oz-bayreq-name', bay.name));
                const meta = el('div', 'oz-bayreq-meta');
                const size = el('span', 'oz-bayreq-chip', 'Max ' + (bay.max_size || '?'));
                size.title = 'Fits: ' + (bay.aircraft || 'unknown');
                meta.appendChild(size);
                if (bay.terminal) meta.appendChild(el('span', 'oz-bayreq-chip', bay.terminal));
                if (bay.pax_type) meta.appendChild(el('span', 'oz-bayreq-chip', bay.pax_type));
                if (!bay.recommended) meta.appendChild(el('span', 'oz-bayreq-chip is-other', 'Outside normal allocation'));
                main.appendChild(meta);
                li.appendChild(main);

                li.appendChild(el('span', 'oz-bayreq-status is-' + bay.status, bay.status_label));

                if (!state.locked) {
                    const actions = el('div', 'oz-bayreq-actions');
                    actions.appendChild(actionButton('fa-chevron-up', 'Move up', index === 0, () => move(index, index - 1)));
                    actions.appendChild(actionButton('fa-chevron-down', 'Move down', index === order.length - 1, () => move(index, index + 1)));
                    actions.appendChild(actionButton('fa-xmark', 'Remove ' + bay.bay, false, () => { order.splice(index, 1); changed(); }));
                    li.appendChild(actions);
                }

                list.appendChild(li);
            });
        }

        function actionButton(icon, label, disabled, onClick) {
            const btn = el('button', 'oz-bayreq-action');
            btn.type = 'button';
            btn.disabled = disabled;
            btn.title = label;
            btn.setAttribute('aria-label', label);
            btn.innerHTML = '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>';
            btn.addEventListener('click', onClick);
            return btn;
        }

        function renderSelect() {
            const previous = select.value;
            select.replaceChildren();

            const full = order.length >= state.max;
            const placeholder = el('option', null, full ? 'You have requested ' + state.max + ' bays - remove one to add another' : 'Choose a bay to add…');
            placeholder.value = '';
            select.appendChild(placeholder);

            const groups = {};
            state.options
                .filter(o => !order.includes(o.id) && (o.recommended || showAll.checked))
                .forEach(o => {
                    if (!groups[o.group]) {
                        groups[o.group] = el('optgroup');
                        groups[o.group].label = groupLabel(o.group);
                    }
                    const opt = el('option', null, o.name + '  ·  Max ' + (o.max_size || '?') + '  ·  ' + o.status_label);
                    opt.value = o.id;
                    groups[o.group].appendChild(opt);
                });

            ['own', 'open', 'shared', 'other'].forEach(g => groups[g] && select.appendChild(groups[g]));

            if (select.querySelector('option[value="' + previous + '"]')) select.value = previous;
            select.disabled = full;
            addBtn.disabled = full || select.value === '';
        }

        function move(from, to) {
            if (to < 0 || to >= order.length) return;
            order.splice(to, 0, order.splice(from, 1)[0]);
            changed();
        }

        function changed() {
            renderList();
            renderSelect();
            refreshMap();
            save();
        }

        // Save status is shown on the card and in the map picker footer
        function setSaved(text, isError) {
            [savedNote, document.getElementById('bay-map-saved')].forEach(note => {
                note.textContent = text;
                note.classList.toggle('is-error', !!isError);
            });
        }

        function save() {
            saving = true;
            setSaved('Saving…', false);

            fetch(card.dataset.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ bays: order }),
            })
                .then(res => res.json().then(body => ({ ok: res.ok, body })))
                .then(({ ok, body }) => {
                    if (body && 'available' in body) apply(body);
                    setSaved(ok ? 'Saved' : (body.message || 'Could not save your bays'), !ok);
                })
                .catch(() => {
                    setSaved('Could not save your bays - check your connection', true);
                })
                .finally(() => { saving = false; });
        }

        function apply(data) {
            state = data;
            order = data.available ? data.requests.slice() : [];
            render();

            // Bays can't be picked once the flight is locked (bay assigned / landed) or gone
            if (!data.available || data.locked) closeMap();
            else refreshMap();
        }

        function load() {
            // Don't pull the list out from under someone mid-drag or mid-save
            if (saving || dragging) return;
            fetch(card.dataset.url, { headers: { 'Accept': 'application/json' } })
                .then(res => res.ok ? res.json() : null)
                .then(data => { if (data && !saving && !dragging) apply(data); })
                .catch(() => {});
        }

        // Drag and drop reordering
        list.addEventListener('dragstart', e => {
            const item = e.target.closest('.oz-bayreq-item');
            if (!item) return;
            dragging = item;
            item.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', item.dataset.id);
        });

        list.addEventListener('dragover', e => {
            if (!dragging) return;
            e.preventDefault();
            const after = Array.from(list.querySelectorAll('.oz-bayreq-item:not(.is-dragging)'))
                .find(item => e.clientY < item.getBoundingClientRect().top + item.offsetHeight / 2);
            after ? list.insertBefore(dragging, after) : list.appendChild(dragging);
        });

        list.addEventListener('drop', e => e.preventDefault());

        list.addEventListener('dragend', () => {
            if (!dragging) return;
            dragging.classList.remove('is-dragging');
            dragging = null;
            const newOrder = Array.from(list.querySelectorAll('.oz-bayreq-item')).map(item => Number(item.dataset.id));
            if (newOrder.join() !== order.join()) {
                order = newOrder;
                changed();
            } else {
                renderList();
            }
        });

        select.addEventListener('change', () => { addBtn.disabled = select.value === ''; });

        addBtn.addEventListener('click', () => {
            const id = Number(select.value);
            if (!id || order.length >= state.max || order.includes(id)) return;
            order.push(id);
            select.value = '';
            changed();
        });

        function setShowAll(checked) {
            showAll.checked = checked;
            document.getElementById('bay-map-all').checked = checked;
            localStorage.setItem(SHOW_ALL_KEY, checked ? '1' : '0');
            if (state && state.available) renderSelect();
            refreshMap();
        }

        showAll.addEventListener('change', () => setShowAll(showAll.checked));

        // ---------- Map picker - ground view of the arrival airport, click a bay to add/remove it ----------
        const mapModal = document.getElementById('bay-map');
        const mapAll = document.getElementById('bay-map-all');
        const MAPBOX_VERSION = 'v2.15.0'; // same as the live map
        const MAP_STYLE = 'mapbox://styles/mapbox/dark-v11'; // same as the live map
        const STATUS_COLOURS = { available: '#4ade80', assigned: '#ffc72c', reserved: '#f59e0b', occupied: '#f87171' };

        let map = null;
        let mapPopup = null;
        let popupBayId = null;
        let mapboxLoading = null;

        mapAll.checked = showAll.checked;

        function mapOpen() {
            return !mapModal.classList.contains('d-none');
        }

        // Mapbox is only needed once someone opens the picker
        function loadMapbox() {
            if (window.mapboxgl) return Promise.resolve();
            if (mapboxLoading) return mapboxLoading;

            // Wait for the stylesheet as well as the script - the map measures its container when it's created
            const load = (tag, attrs) => new Promise((resolve, reject) => {
                const node = Object.assign(document.createElement(tag), attrs);
                node.onload = resolve;
                node.onerror = reject;
                document.head.appendChild(node);
            });

            mapboxLoading = Promise.all([
                load('link', { rel: 'stylesheet', href: 'https://api.mapbox.com/mapbox-gl-js/' + MAPBOX_VERSION + '/mapbox-gl.css' }),
                load('script', { src: 'https://api.mapbox.com/mapbox-gl-js/' + MAPBOX_VERSION + '/mapbox-gl.js' }),
            ]).catch(err => { mapboxLoading = null; throw err; });

            return mapboxLoading;
        }

        function mappable(option) {
            return option.lat !== null && option.lon !== null;
        }

        function bayFeatures() {
            return {
                type: 'FeatureCollection',
                features: state.options.filter(mappable).map(o => ({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [o.lon, o.lat] },
                    properties: { id: o.id, bay: o.bay, status: o.status, recommended: o.recommended, rank: order.indexOf(o.id) + 1 },
                })),
            };
        }

        // Without "show every bay" only the bays normal allocation would use - plus any already chosen
        function applyMapFilter() {
            const filter = mapAll.checked ? null : ['any', ['get', 'recommended'], ['>', ['get', 'rank'], 0]];
            ['req-bays-dot', 'req-bays-label'].forEach(layer => map.getLayer(layer) && map.setFilter(layer, filter));
            mapModal.querySelector('.oz-baymap-legend-other').classList.toggle('d-none', !mapAll.checked);
        }

        function addBayLayers() {
            if (map.getSource('req-bays')) return;

            map.addSource('req-bays', { type: 'geojson', data: bayFeatures() });

            map.addLayer({
                id: 'req-bays-dot', type: 'circle', source: 'req-bays',
                paint: {
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 13, 3, 16, 7, 18, 11],
                    'circle-color': ['match', ['get', 'status'],
                        'available', STATUS_COLOURS.available, 'assigned', STATUS_COLOURS.assigned,
                        'reserved', STATUS_COLOURS.reserved, 'occupied', STATUS_COLOURS.occupied, '#97a2b8'],
                    'circle-opacity': ['case', ['get', 'recommended'], 0.95, 0.6],
                    'circle-stroke-width': ['case', ['get', 'recommended'], 1.5, 2],
                    'circle-stroke-color': ['case', ['get', 'recommended'], '#0a0e18', '#d6c3fb'],
                },
            });

            map.addLayer({
                id: 'req-bays-label', type: 'symbol', source: 'req-bays', minzoom: 15,
                layout: { 'text-field': ['get', 'bay'], 'text-size': 11, 'text-offset': [0, 1.2], 'text-anchor': 'top' },
                paint: { 'text-color': '#ffffff', 'text-halo-color': '#0a0e18', 'text-halo-width': 1.4 },
            });

            // Chosen bays - a numbered amber marker on top, always shown
            map.addLayer({
                id: 'req-bays-selected', type: 'circle', source: 'req-bays', filter: ['>', ['get', 'rank'], 0],
                paint: {
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 13, 8, 16, 11, 18, 15],
                    'circle-color': '#ffc72c', 'circle-stroke-color': '#0a0e18', 'circle-stroke-width': 2,
                },
            });

            map.addLayer({
                id: 'req-bays-rank', type: 'symbol', source: 'req-bays', filter: ['>', ['get', 'rank'], 0],
                layout: { 'text-field': ['to-string', ['get', 'rank']], 'text-size': 13, 'text-allow-overlap': true, 'text-ignore-placement': true },
                paint: { 'text-color': '#16210a' },
            });

            applyMapFilter();
        }

        function popupContent(bay) {
            const wrap = el('div', 'oz-baymap-pop');
            wrap.appendChild(el('div', 'oz-baymap-pop-name', bay.name));

            const meta = el('div', 'oz-bayreq-meta');
            const size = el('span', 'oz-bayreq-chip', 'Max ' + (bay.max_size || '?'));
            size.title = 'Fits: ' + (bay.aircraft || 'unknown');
            meta.appendChild(size);
            if (bay.terminal) meta.appendChild(el('span', 'oz-bayreq-chip', bay.terminal));
            if (bay.pax_type) meta.appendChild(el('span', 'oz-bayreq-chip', bay.pax_type));
            if (!bay.recommended) meta.appendChild(el('span', 'oz-bayreq-chip is-other', 'Outside normal allocation'));
            wrap.appendChild(meta);

            wrap.appendChild(el('span', 'oz-bayreq-status is-' + bay.status, bay.status_label));

            const rank = order.indexOf(bay.id) + 1;
            const btn = el('button', 'btn btn-sm btn-block ' + (rank ? 'oz-baymap-remove' : 'oz-bayreq-add-btn'));
            btn.type = 'button';

            if (rank) {
                btn.textContent = 'Remove (choice ' + rank + ')';
                btn.addEventListener('click', () => { order.splice(rank - 1, 1); changed(); });
            } else if (order.length >= state.max) {
                btn.textContent = 'You have ' + state.max + ' bays - remove one first';
                btn.disabled = true;
            } else {
                btn.textContent = 'Add as choice ' + (order.length + 1);
                btn.addEventListener('click', () => { order.push(bay.id); changed(); });
            }
            wrap.appendChild(btn);

            return wrap;
        }

        function openPopup(id) {
            const bay = optionById(id);
            if (!bay || !mappable(bay)) return;

            if (!mapPopup) {
                mapPopup = new mapboxgl.Popup({ offset: 14, maxWidth: '280px', className: 'oz-baymap-popup' });
                mapPopup.on('close', () => { popupBayId = null; });
            }

            popupBayId = id;
            mapPopup.setLngLat([bay.lon, bay.lat]).setDOMContent(popupContent(bay)).addTo(map);
        }

        // Choice 1-3 strip along the bottom of the picker
        function renderMapChoices() {
            const strip = document.getElementById('bay-map-choices');
            strip.replaceChildren();

            for (let i = 0; i < state.max; i++) {
                const bay = order[i] !== undefined ? optionById(order[i]) : null;
                const chip = el('div', 'oz-baymap-choice' + (bay ? '' : ' is-empty'));
                chip.appendChild(el('span', 'oz-bayreq-rank', String(i + 1)));

                if (bay) {
                    const name = el('button', 'oz-baymap-choice-name', bay.bay);
                    name.type = 'button';
                    name.title = 'Show ' + bay.name + ' on the map';
                    name.addEventListener('click', () => {
                        if (!map || !mappable(bay)) return;
                        map.flyTo({ center: [bay.lon, bay.lat], zoom: Math.max(map.getZoom(), 16.5) });
                        openPopup(bay.id);
                    });
                    chip.appendChild(name);
                    chip.appendChild(actionButton('fa-xmark', 'Remove ' + bay.bay, false, () => { order.splice(i, 1); changed(); }));
                } else {
                    chip.appendChild(el('span', 'oz-baymap-choice-name text-muted', 'Click a bay'));
                }

                strip.appendChild(chip);
            }
        }

        function refreshMap() {
            if (!mapOpen() || !state || !state.available) return;

            renderMapChoices();
            if (!map) return;

            const source = map.getSource('req-bays');
            if (source) source.setData(bayFeatures());
            applyMapFilter();

            if (popupBayId !== null) openPopup(popupBayId);
        }

        function fitToBays() {
            const bays = state.options.filter(o => mappable(o) && (o.recommended || mapAll.checked || order.includes(o.id)));
            if (bays.length === 0) {
                if (state.flight.airport_lon !== null) map.jumpTo({ center: [state.flight.airport_lon, state.flight.airport_lat], zoom: 14 });
                return;
            }

            const bounds = new mapboxgl.LngLatBounds();
            bays.forEach(o => bounds.extend([o.lon, o.lat]));
            map.fitBounds(bounds, { padding: 60, maxZoom: 17, duration: 0 });
        }

        function createMap() {
            mapboxgl.accessToken = mapModal.dataset.token;
            map = new mapboxgl.Map({
                container: 'bay-map-canvas',
                style: MAP_STYLE,
                center: [state.flight.airport_lon ?? 151.18, state.flight.airport_lat ?? -33.94],
                zoom: 14,
                projection: 'mercator',
            });

            map.dragRotate.disable();
            map.touchZoomRotate.disableRotation();
            map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

            map.on('style.load', addBayLayers);
            map.once('load', () => { map.resize(); fitToBays(); });

            ['req-bays-dot', 'req-bays-selected'].forEach(layer => {
                map.on('click', layer, e => openPopup(e.features[0].properties.id));
                map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
                map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
            });
        }

        function openMap() {
            if (!state || !state.available || state.locked) return;

            const f = state.flight;
            document.getElementById('bay-map-sub').textContent =
                f.callsign + (f.aircraft ? ' · ' + f.aircraft : '') + ' into ' + (f.airport_name || f.arrival) +
                ' - click a bay to add it, up to ' + state.max + '. Only bays your aircraft fits are shown.';

            mapModal.classList.remove('d-none');
            document.body.classList.add('oz-baymap-open');
            renderMapChoices();

            loadMapbox()
                .then(() => {
                    if (!map) {
                        createMap();
                    } else {
                        map.resize();
                        refreshMap();
                        fitToBays();
                    }
                })
                .catch(() => {
                    document.getElementById('bay-map-canvas').replaceChildren(el('p', 'oz-baymap-error', 'The map could not be loaded. You can still choose bays from the list.'));
                });
        }

        function closeMap() {
            if (!mapOpen()) return;
            if (mapPopup) mapPopup.remove();
            mapModal.classList.add('d-none');
            document.body.classList.remove('oz-baymap-open');
        }

        document.getElementById('bay-req-map-open').addEventListener('click', openMap);
        document.getElementById('bay-map-close').addEventListener('click', closeMap);
        document.getElementById('bay-map-done').addEventListener('click', closeMap);
        mapModal.addEventListener('click', e => { if (e.target === mapModal) closeMap(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMap(); });
        mapAll.addEventListener('change', () => setShowAll(mapAll.checked));


        load();
        setInterval(load, POLL_MS);
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