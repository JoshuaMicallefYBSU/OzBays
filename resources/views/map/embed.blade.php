<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>OzBays - Map</title>

    <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta property="og:title" content="ozBays">
        <meta property="og:description"
            content="Automatic bay assignments for Brisbane, Sydney, Melbourne & Perth International Airports on the VATSIM Network.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('img/favcon.png') }}">
      <link rel="icon" type="image/png" href="{{ asset('img/favcon.png') }}">
      

    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />

    <style>
        body { margin: 0; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        #map { height: 100vh; width: 100%; }
        pre { font-family: monospace; font-size: 13px; }

        .oz-panel {
            position: absolute;
            background: rgba(19, 27, 46, 0.92);
            color: #e8ecf6;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.4);
            z-index: 2;
        }

        /* Info toggle button */
        #info-toggle {
            top: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            user-select: none;
        }
        #info-toggle:hover { background: rgba(255, 255, 255, 0.08); }

        /* Collapsible info panel */
        #event-overlay {
            top: 50px;
            right: 10px;
            min-width: 240px;
            max-width: 320px;
            padding: 12px 14px;
            font-size: 12px;
            line-height: 1.5;
            display: none;
        }
        #event-overlay.open { display: block; }
        #event-overlay h2 { margin: 0 0 8px 0; font-size: 15px; }
        #event-overlay p { margin: 0 0 8px 0; }
        #event-overlay p:last-child { margin-bottom: 0; }

        /* Persistent status legend */
        #status-legend {
            bottom: 16px;
            left: 16px;
            padding: 10px 14px;
            font-size: 12px;
        }
        #status-legend .legend-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        #status-legend .legend-row:last-child { margin-bottom: 0; }
        #status-legend .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        /* ---------- Popups (aircraft / bay / airport labels) ---------- */
        .mapboxgl-popup-content {
            background: rgba(19, 27, 46, 0.97);
            color: #e8ecf6;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.45);
            padding: 0;
            overflow: hidden;
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
        }
        .mapboxgl-popup-anchor-bottom .mapboxgl-popup-tip,
        .mapboxgl-popup-anchor-bottom-left .mapboxgl-popup-tip,
        .mapboxgl-popup-anchor-bottom-right .mapboxgl-popup-tip { border-top-color: rgba(19, 27, 46, 0.97); }
        .mapboxgl-popup-anchor-top .mapboxgl-popup-tip,
        .mapboxgl-popup-anchor-top-left .mapboxgl-popup-tip,
        .mapboxgl-popup-anchor-top-right .mapboxgl-popup-tip { border-bottom-color: rgba(19, 27, 46, 0.97); }
        .mapboxgl-popup-anchor-left .mapboxgl-popup-tip { border-right-color: rgba(19, 27, 46, 0.97); }
        .mapboxgl-popup-anchor-right .mapboxgl-popup-tip { border-left-color: rgba(19, 27, 46, 0.97); }
        .mapboxgl-popup-close-button {
            color: #94a3b8;
            font-size: 18px;
            padding: 2px 8px;
            border-radius: 0 12px 0 6px;
        }
        .mapboxgl-popup-close-button:hover { background: rgba(255, 255, 255, 0.08); color: #fff; }

        .oz-pop { min-width: 210px; font-size: 12px; line-height: 1.4; }
        .oz-pop-head {
            padding: 10px 30px 8px 12px;
            border-top: 3px solid var(--accent, #38bdf8);
            background: rgba(255, 255, 255, 0.03);
        }
        .oz-pop-title { font-size: 15px; font-weight: 700; letter-spacing: 0.02em; display: flex; align-items: center; gap: 6px; }
        .oz-pop-sub { color: #94a3b8; margin-top: 2px; }
        .oz-chip {
            font-size: 10px;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.1);
            color: #cbd5e1;
        }
        .oz-pop-body { padding: 8px 12px 10px; }
        .oz-pop-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; }
        .oz-pop-section { margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.08); display: grid; gap: 6px; }
        .oz-stat-label { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: #7c8aa5; }
        .oz-stat-value { font-weight: 600; }
        .oz-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .oz-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .oz-badge-ok   { background: rgba(34, 197, 94, 0.15);  color: #86efac; }
        .oz-badge-warn { background: rgba(255, 140, 0, 0.18);  color: #ffb45c; }
        .oz-badge-none { background: rgba(148, 163, 184, 0.12); color: #94a3b8; }
        .oz-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

        #status-legend .legend-ring {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #e2e8f0;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

<div id="map"></div>

<div id="info-toggle" class="oz-panel" title="About this map">i</div>
<div id="event-overlay" class="oz-panel"></div>

<div id="status-legend" class="oz-panel">
    <div class="legend-row"><span class="legend-dot" style="background:green;"></span>Available</div>
    <div class="legend-row"><span class="legend-dot" style="background:#b37202;"></span>Reserved for arrival</div>
    <div class="legend-row"><span class="legend-dot" style="background:red;"></span>Occupied</div>
    <div class="legend-row"><span class="legend-ring" style="border-style:solid;"></span>Aircraft has a real-world bay</div>
    <div class="legend-row"><span class="legend-ring" style="border-style:dashed; border-color:#ff8c00;"></span>Real-world bay too small</div>
</div>

<script>
/* ----------------------------------------------------------
 * Map setup
 * -------------------------------------------------------- */
mapboxgl.accessToken = 'pk.eyJ1Ijoiam9zaHVhbWljYWxsZWZ5YnN1IiwiYSI6ImNsb241cndobzB6Y2oyam5ya3JvdzVndGMifQ.EEFitNQf_9gmMdnQO4ywXw';

const qs = new URLSearchParams(window.location.search);

const EMBED_DEFAULTS = {
    center: [133.7751, -25.2744], // [lon, lat]
    zoom: 3.8,
    hide_info: false,
};

// parse float safely
const floatOr = (value, fallback) => {
    const n = parseFloat(value);
    return Number.isFinite(n) ? n : fallback;
};

// parse boolean safely (supports 1/0, true/false, yes/no, on/off)
const boolOr = (value, fallback) => {
    if (value === null || value === undefined) return fallback;
    const v = String(value).toLowerCase();
    if (['1', 'true', 'yes', 'y', 'on'].includes(v)) return true;
    if (['0', 'false', 'no', 'n', 'off'].includes(v)) return false;
    return fallback;
};

// If provided, use ?lat=...&lon=... otherwise default center
const lat = floatOr(qs.get('lat') ?? null, EMBED_DEFAULTS.center[1]);
const lon = floatOr(qs.get('lon') ?? null, EMBED_DEFAULTS.center[0]);
const zoom = floatOr(qs.get('zoom') ?? null, EMBED_DEFAULTS.zoom);

// hide_info override (e.g. hide_info=1 / true)
const hideInfo = boolOr(qs.get('hide_info'), EMBED_DEFAULTS.hide_info);

// --- Your existing map init, now using resolved values ---
const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/dark-v11',
    center: [lon, lat],
    zoom: zoom,
    pitch: 0,
    bearing: 0,
    projection: 'mercator'
});

// Populate the event details in the overlay
    const eventOverlay = document.getElementById("event-overlay");
    const infoToggle = document.getElementById("info-toggle");
    const statusLegend = document.getElementById("status-legend");

    eventOverlay.innerHTML = `
        <h2>OzBays - Live Network Map</h2>
        <p>Welcome to OzBays. Automatic Gate Assignments for VATPAC's Busiest Airports.</p>
        <p>
            Each Airport has its own unique color, with a range of 200NM circled around it.
            All aircraft inside the circle will have an assigned bay, provided they are considered Airbourne (Above F120, GS &gt; 80k etc).
            Aircraft will appear outside of this area for visualisation purposes only. They are not considered until they pass inside the circle.
        </p>
        <p>
            Each airport has all bays mapped according to their specifications.
            Clicking on a bay will show its live status &mdash; see the legend in the bottom-left for what each colour means.
        </p>
        <p style="color:#94a3b8;">
            Aircraft icons by <a href="https://github.com/VATSIM-Radar/vatsim-radar" target="_blank" rel="noopener" style="color:#7dd3fc;">VATSIM Radar</a>,
            used under <a href="https://creativecommons.org/licenses/by-nc/4.0/" target="_blank" rel="noopener" style="color:#7dd3fc;">CC BY-NC 4.0</a>
            (shown as single-colour silhouettes).
        </p>
    `;

    // Info panel starts collapsed; the (i) button toggles it. hide_info=1 removes the button/legend entirely.
    if (hideInfo) {
        infoToggle.style.display = "none";
        eventOverlay.style.display = "none";
        statusLegend.style.display = "none";
    } else {
        infoToggle.addEventListener('click', () => {
            eventOverlay.classList.toggle('open');
        });
    }

map.dragRotate.disable();
map.touchZoomRotate.disableRotation();
map.doubleClickZoom.disable();

/* ----------------------------------------------------------
 * Data from controller
 * -------------------------------------------------------- */
const geojson      = {!! $geojson !!};
const airports     = {!! $airportsJson !!};
let aircraftData   = {!! $aircraftJson !!};

/* ----------------------------------------------------------
 * Airport → colour lookup
 * -------------------------------------------------------- */
const airportColourMap = {};
airports.forEach(ap => {
    airportColourMap[ap.icao] = ap.color;
});

/* ----------------------------------------------------------
 * BAY STATUS STORAGE + MAPPING ✅
 * -------------------------------------------------------- */
const bayMarkers = {}; // key: "YSSY:1A"

function bayStatusMap(status, arrCallsign, arr) {
    const time = formatTime(arr);

    switch (status) {
        case 1:
            return { color: '#b37202', label: `Reserved for ${arrCallsign}<br>EIBT ${time}z` };
        case 2:
            return { color: 'red', label: 'Occupied' };
        default:
            return { color: 'green', label: 'Available' };
    }
}

/* ----------------------------------------------------------
 * Popup (label) builders
 * -------------------------------------------------------- */
function bayPopupHtml(title, subtitle, statusColour, statusLabel) {
    return `
        <div class="oz-pop" style="--accent:${statusColour};">
            <div class="oz-pop-head">
                <div class="oz-pop-title">${title}</div>
                ${subtitle ? `<div class="oz-pop-sub">${subtitle}</div>` : ''}
            </div>
            <div class="oz-pop-body">
                <div class="oz-row"><span class="oz-dot" style="background:${statusColour};"></span><span style="flex:1;">${statusLabel}</span></div>
            </div>
        </div>`;
}

function aircraftPopupHtml(p) {
    const stat = (label, value) => `<div><span class="oz-stat-label">${label}</span><span class="oz-stat-value">${value}</span></div>`;
    const alt = Number(p.alt);

    let irlBadge = '<span class="oz-badge oz-badge-none">No data</span>';
    if (p.irl_state === 'fits') {
        irlBadge = `<span class="oz-badge oz-badge-ok">${p.irl_bay} &#10003; fits</span>`;
    } else if (p.irl_state === 'too_small') {
        irlBadge = `<span class="oz-badge oz-badge-warn">${p.irl_bay} &#9888; too small</span>`;
    }

    return `
        <div class="oz-pop" style="--accent:${p.colour};">
            <div class="oz-pop-head">
                <div class="oz-pop-title">${p.callsign} <span class="oz-chip">${p.ac ?? '?'}</span></div>
                <div class="oz-pop-sub">${p.dep ?? '????'} &rarr; ${p.arr ?? '????'}</div>
            </div>
            <div class="oz-pop-body">
                <div class="oz-pop-grid">
                    ${stat('Altitude', isNaN(alt) ? p.alt : alt.toLocaleString() + 'ft')}
                    ${stat('Ground Speed', p.speed + 'kt')}
                    ${stat('ELT', p.elt === 'N/A' ? 'N/A' : p.elt + 'z')}
                    ${stat('EIBT', p.eibt === 'N/A' ? 'N/A' : p.eibt + 'z')}
                </div>
                <div class="oz-pop-section">
                    <div class="oz-row"><span class="oz-stat-label">Status</span><span class="oz-stat-value">${p.status}</span></div>
                    ${p.ignored ? `
                    <div class="oz-row"><span class="oz-badge oz-badge-none" style="white-space:normal;">Aircraft ignored from OzBays</span></div>
                    ` : `
                    <div class="oz-row"><span class="oz-stat-label">Assigned Bay</span><span class="oz-stat-value">${p.bay}</span></div>
                    <div class="oz-row"><span class="oz-stat-label">Real-World Bay</span>${irlBadge}</div>
                    `}
                </div>
            </div>
        </div>`;
}

function refreshBayColours() {

    fetch('/api/v1/bays/live')
        .then(res => res.json())
        .then(data => {

            data.forEach(bay => {
                const key = `${bay.airport}:${bay.bay}`;
                if (!bayMarkers[key]) return;

                const slot = bay.arrival_slots?.[0];

                const { color, label } = bayStatusMap(bay.status, bay.callsign, slot?.eibt);

                bayMarkers[key].el.style.backgroundColor = color;

                const popup = bayMarkers[key].marker.getPopup();
                if (popup) {
                    popup.setHTML(bayPopupHtml(`Bay ${bay.display_name ?? bay.bay}`, bay.airport, color, label));
                }
            });

            console.log(bayMarkers);

        })
        .catch(err => console.error('Bay refresh failed', err));
}

function formatTime(datetime) {
    if (!datetime) return 'N/A';

    // Bay slot times come without a timezone (e.g. "2026-10-21 06:46:12") - they are UTC, so mark them as such
    // before parsing, otherwise the browser reads them as local time
    const iso = typeof datetime === 'string' && !/[zZ]|[+-]\d\d:?\d\d$/.test(datetime)
        ? datetime.replace(' ', 'T') + 'Z'
        : datetime;

    const d = new Date(iso);
    if (isNaN(d)) return 'N/A';

    const hh = String(d.getUTCHours()).padStart(2, '0');
    const mm = String(d.getUTCMinutes()).padStart(2, '0');

    return `${hh}${mm}`;   // → 2023
}

/* ----------------------------------------------------------
 * Utility: aviation-accurate geodesic circle
 * -------------------------------------------------------- */
function createCircle(center, radiusMeters, points = 64) {
    const [lng, lat] = center;
    const earthRadius = 6378137;
    const coords = [];
    const latRad = lat * Math.PI / 180;
    const lngRad = lng * Math.PI / 180;
    const d = radiusMeters / earthRadius;

    for (let i = 0; i <= points; i++) {
        const b = i * 2 * Math.PI / points;
        const lat2 = Math.asin(
            Math.sin(latRad) * Math.cos(d) +
            Math.cos(latRad) * Math.sin(d) * Math.cos(b)
        );
        const lng2 = lngRad + Math.atan2(
            Math.sin(b) * Math.sin(d) * Math.cos(latRad),
            Math.cos(d) - Math.sin(latRad) * Math.sin(lat2)
        );
        coords.push([lng2 * 180 / Math.PI, lat2 * 180 / Math.PI]);
    }

    return {
        type: "Feature",
        geometry: { type: "Polygon", coordinates: [coords] }
    };
}

/* ----------------------------------------------------------
 * Aircraft arrow icon
 * -------------------------------------------------------- */
function addAircraftArrowIcon() {
    const size = 64;
    const c = document.createElement('canvas');
    c.width = size; c.height = size;
    const ctx = c.getContext('2d');

    ctx.fillStyle = '#fff';
    ctx.beginPath();
    ctx.moveTo(size / 2, 4);
    ctx.lineTo(size - 10, size - 4);
    ctx.lineTo(size / 2, size - 20);
    ctx.lineTo(10, size - 4);
    ctx.closePath();
    ctx.fill();

    if (!map.hasImage('aircraft-arrow')) {
        map.addImage('aircraft-arrow', ctx.getImageData(0,0,size,size), { sdf: true });
    }
}

/* ----------------------------------------------------------
 * Aircraft type icons (public/img/aircraft-icon/<ICAO>.svg, picked per size group in aircraft.json)
 * Loaded on first use and drawn as a silhouette so they can be tinted in the arrival airport colour.
 * Until an icon has loaded the aircraft shows as the plain arrow.
 * -------------------------------------------------------- */
const AIRCRAFT_ICONS = @json($aircraftIcons ?? []);
// Built with asset() so the path is right on every server (the live site serves files from /public)
const AIRCRAFT_ICON_BASE = @json(asset('img/aircraft-icon'));
const aircraftIconLoads = new Map();
let lastAircraftPoints = null;

// Resolves once the icon is on the map (or failed - the aircraft then keeps the arrow)
function loadAircraftIcon(icao) {
    const name = `ac-${icao}`;
    if (aircraftIconLoads.has(name)) return aircraftIconLoads.get(name);

    const load = new Promise(resolve => {
        const img = new Image();
        img.onload = () => {
            const size = 64;
            const c = document.createElement('canvas');
            c.width = size; c.height = size;
            const ctx = c.getContext('2d');

            // Fit the icon inside the square, keeping its proportions
            const scale = Math.min(size / img.width, size / img.height);
            const w = img.width * scale, h = img.height * scale;
            ctx.drawImage(img, (size - w) / 2, (size - h) / 2, w, h);

            if (!map.hasImage(name)) {
                map.addImage(name, ctx.getImageData(0, 0, size, size), { sdf: true });
            }

            // Mapbox picks each aircraft's icon when the data is set - re-apply it so a late icon shows straight away
            if (lastAircraftPoints && map.getSource('aircraft')) {
                map.getSource('aircraft').setData(lastAircraftPoints);
            }
            resolve();
        };
        img.onerror = () => { console.warn(`Aircraft icon ${icao}.svg could not be loaded - using the arrow`); resolve(); };
        img.src = `${AIRCRAFT_ICON_BASE}/${icao}.svg`;
    });

    aircraftIconLoads.set(name, load);
    return load;
}

// Icon size per aircraft: zoom-based size x the aircraft's size-group scale
function aircraftIconSize() {
    return [
        'interpolate', ['linear'], ['zoom'],
        3, ['*', 0.22, ['get', 'icon_scale']],
        6, ['*', 0.35, ['get', 'icon_scale']],
        10, ['*', 0.5, ['get', 'icon_scale']]
    ];
}

/* ----------------------------------------------------------
 * Aircraft real-world bay rings (solid + dashed)
 * Larger canvas than the arrow so, at the same icon-size, the ring sits around it.
 * -------------------------------------------------------- */
const IRL_TOO_SMALL_COLOUR = '#ff8c00';
// Flight statuses (from FlightData) that mean the aircraft is on the ground
const GROUND_STATUSES = ['Arrived', 'Taxiing', 'On Ground', 'Departing another Airport'];

function addAircraftRingIcons() {
    const size = 112;

    [['aircraft-ring-solid', []], ['aircraft-ring-dashed', [14, 10]]].forEach(([name, dash]) => {
        if (map.hasImage(name)) return;

        const c = document.createElement('canvas');
        c.width = size; c.height = size;
        const ctx = c.getContext('2d');

        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 7;
        ctx.setLineDash(dash);
        ctx.beginPath();
        ctx.arc(size / 2, size / 2, size / 2 - 8, 0, Math.PI * 2);
        ctx.stroke();

        map.addImage(name, ctx.getImageData(0, 0, size, size), { sdf: true });
    });
}

/* ----------------------------------------------------------
 * Aircraft refresh logic
 * -------------------------------------------------------- */
function refreshAircraft() {

    if (!map.getSource('aircraft')) return;

    fetch('/api/v1/flights/live')
        .then(res => res.json())
        .then(data => {

            const aircraftPoints = {
                type: 'FeatureCollection',
                features: data.map(ac => ({
                    type: 'Feature',
                    geometry: {
                        type: 'Point',
                        coordinates: [Number(ac.lon), Number(ac.lat)]
                    },
                    properties: {
                        callsign: ac.callsign,
                        dep: ac.dep,
                        arr: ac.arr,
                        speed: Number(ac.speed),
                        alt: ac.alt,
                        status: ac.status ?? 'N/A',
                        colour: airportColourMap[ac.arr] ?? '#787777',
                        elt: toHHMM(ac.elt) ?? 'N/A',
                        eibt: toHHMM(ac.eibt) ?? 'N/A',
                        ac: ac.ac,
                        bay: ac.map_bay ? (ac.map_bay.display_name ?? ac.map_bay.bay) : 'N/A',
                        // Real-world bay: 'none' (no IRL data), 'fits', or 'too_small' for the network aircraft
                        irl_state: !ac.irl_bay ? 'none' : (ac.irl_bay.fits ? 'fits' : 'too_small'),
                        irl_bay: ac.irl_bay ? (ac.irl_bay.display_name ?? ac.irl_bay.bay) : null,
                        // On the ground (landed, taxiing, parked or yet to depart) - the IRL ring is hidden
                        on_ground: GROUND_STATUSES.includes(ac.status),
                        // Aircraft type icon for its size group - falls back to the arrow (see aircraft-arrows layer)
                        icon: ac.map_icon?.icon ? `ac-${ac.map_icon.icon}` : 'aircraft-arrow',
                        icon_scale: Number(ac.map_icon?.scale ?? 1),
                        ignored: ac.map_icon?.ignored === true,
                        bearing: Number(ac.hdg ?? 0)
                    }
                }))
            };

            lastAircraftPoints = aircraftPoints;
            data.forEach(ac => { if (ac.map_icon?.icon) loadAircraftIcon(ac.map_icon.icon); });

            map.getSource('aircraft').setData(aircraftPoints);
        })
        .catch(err => console.error('Aircraft refresh failed', err));
}

/* ----------------------------------------------------------
 * Map content
 * -------------------------------------------------------- */
map.on('load', () => {

    /* ================== AIRPORT RINGS ================== */
    const airportRings = {
        type: "FeatureCollection",
        features: []
    };

    geojson.features
    .filter(f => f.properties.type === 'airport')
    .forEach(a => {

        const ring = createCircle(a.geometry.coordinates, 200 * 1852);

        ring.properties = {
            colour: a.properties.color ?? '#F54927'
        };

        airportRings.features.push(ring);
    });

    map.addSource('airport-rings', { type: 'geojson', data: airportRings });

    map.addLayer({
        id: 'airport-rings-fill',
        type: 'fill',
        source: 'airport-rings',
        paint: {
            'fill-color': ['get','colour'],
            'fill-opacity': 0.04
        }
    });

    map.addLayer({
        id: 'airport-rings-outline',
        type: 'line',
        source: 'airport-rings',
        paint: {
            'line-color': ['get','colour'],
            'line-width': 0.6
        }
    });

    /* ================== PARKING BAYS (UPDATED ✅) ================== */
    geojson.features.filter(f => f.properties.type === 'parking').forEach(f => {

        const el = document.createElement('div');
        el.style.width = '8px';
        el.style.height = '8px';
        el.style.borderRadius = '50%';
        el.style.backgroundColor = f.properties.color;
        el.style.cursor = 'pointer';

        const marker = new mapboxgl.Marker(el)
            .setLngLat(f.geometry.coordinates)
            .setPopup(
                new mapboxgl.Popup({ offset: 10 }).setHTML(
                    bayPopupHtml(`Bay ${f.properties.display_name ?? f.properties.bay}`, f.properties.icao, f.properties.color, f.properties.status)
                )
            )
            .addTo(map);

        const key = `${f.properties.icao}:${f.properties.bay}`;
        bayMarkers[key] = { marker, el };
    });

    /* ================== AIRPORT MARKERS ================== */
    geojson.features.filter(f => f.properties.type === 'airport').forEach(f => {

        const el = document.createElement('div');
        el.style.width = '12px';
        el.style.height = '12px';
        el.style.borderRadius = '50%';
        el.style.backgroundColor = f.properties.color;
        el.style.cursor = 'pointer';

        new mapboxgl.Marker(el)
            .setLngLat(f.geometry.coordinates)
            .setPopup(
                new mapboxgl.Popup({ offset: 20 }).setHTML(
                    bayPopupHtml(f.properties.title, `${f.properties.name} Airport`, f.properties.color ?? '#F54927', 'Tracking radius: 200NM')
                )
            )
            .addTo(map);
    });

    /* ================== AIRCRAFT SOURCES ================== */
    map.addSource('aircraft', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] }
    });

    addAircraftArrowIcon();
    addAircraftRingIcons();

    // Real-world bay ring around the arrow: solid (airport colour) = IRL bay fits,
    // dashed orange = IRL bay too small for the network aircraft, no ring = no IRL data or on the ground.
    // Drawn as an icon rather than a ground circle so it stays the same size at every zoom.
    map.addLayer({
        id: 'aircraft-irl-rings',
        type: 'symbol',
        source: 'aircraft',
        filter: ['all', ['!=', ['get', 'irl_state'], 'none'], ['!', ['get', 'on_ground']]],
        layout: {
            'icon-image': ['match', ['get', 'irl_state'], 'too_small', 'aircraft-ring-dashed', 'aircraft-ring-solid'],
            'icon-size': aircraftIconSize(),
            'icon-allow-overlap': true,
            'icon-ignore-placement': true
        },
        paint: {
            'icon-color': ['match', ['get', 'irl_state'], 'too_small', IRL_TOO_SMALL_COLOUR, ['get', 'colour']]
        }
    });

    map.addLayer({
        id: 'aircraft-arrows',
        type: 'symbol',
        source: 'aircraft',
        layout: {
            // Aircraft type icon for its size group, or the arrow until/unless that icon is loaded
            'icon-image': ['coalesce', ['image', ['get', 'icon']], ['image', 'aircraft-arrow']],
            // Shrink icons at low zoom so busy airports don't turn into a solid blob,
            // and size them by aircraft group so an A388 looks bigger than a C172
            'icon-size': aircraftIconSize(),
            'icon-rotate': ['get','bearing'],
            'icon-rotation-alignment': 'map',
            // Always render every aircraft icon, even when overlapping - hiding
            // overlapping icons at low zoom made inbound traffic disappear.
            'icon-allow-overlap': true,
            'icon-ignore-placement': true
        },
        paint: { 'icon-color': ['get','colour'] }
    });

    /* ================== AIRCRAFT POPUPS ================== */
    map.on('click', 'aircraft-arrows', e => {
        if (!e.features || !e.features.length) return;

        const f = e.features[0];
        const p = f.properties;

        new mapboxgl.Popup({ offset: 15 })
            .setLngLat(f.geometry.coordinates)
            .setHTML(aircraftPopupHtml(p))
            .addTo(map);
    });

    map.on('mouseenter', 'aircraft-arrows', () => {
        map.getCanvas().style.cursor = 'pointer';
    });

    map.on('mouseleave', 'aircraft-arrows', () => {
        map.getCanvas().style.cursor = '';
    });

    /* ================== DOUBLE-CLICK LAT/LON PICKER ================== */
    map.on('dblclick', e => {
        const lat = e.lngLat.lat.toFixed(8);
        const lon = e.lngLat.lng.toFixed(8);

        const text = `"lat": ${lat},\n"lon": ${lon},`;

        const html = `
            <div style="font-size:13px;">
                <pre style="margin:0 0 6px 0;">${text}</pre>
                <button class="copy-coords-btn"
                    style="
                        padding:4px 8px;
                        font-size:12px;
                        cursor:pointer;
                        border:1px solid #555;
                        background:#222;
                        color:#fff;
                        border-radius:4px;
                    ">
                    Copy
                </button>
                <span class="copy-status" style="margin-left:6px; display:none;">✅ Copied</span>
            </div>
        `;

        const popup = new mapboxgl.Popup()
            .setLngLat(e.lngLat)
            .setHTML(html)
            .addTo(map);

        // Attach the click handler AFTER the popup is added
        const popupEl = popup.getElement();
        const btn = popupEl.querySelector('.copy-coords-btn');
        const status = popupEl.querySelector('.copy-status');

        if (btn) {
            btn.addEventListener('click', () => {
                // Try Clipboard API
                navigator.clipboard.writeText(text)
                    .then(() => {
                        if (status) {
                            status.style.display = 'inline';
                            setTimeout(() => status.style.display = 'none', 1000);
                        }
                    })
                    .catch(err => {
                        console.warn('Clipboard write failed', err);
                        // Fallback: select text in the <pre> so user can Ctrl+C
                        const pre = popupEl.querySelector('pre');
                        if (!pre) return;

                        const range = document.createRange();
                        range.selectNodeContents(pre);
                        const sel = window.getSelection();
                        sel.removeAllRanges();
                        sel.addRange(range);
                    });
            });
        }
    });




    /* ================== FIRST LOAD + REFRESH ================== */
    // Load every aircraft icon before the first aircraft are drawn, so they never show the arrow first
    Promise.all(AIRCRAFT_ICONS.map(loadAircraftIcon)).then(() => {
        refreshAircraft();
        setInterval(refreshAircraft, 5000);
    });

    refreshBayColours();
    setInterval(refreshBayColours, 15000);

});

function toHHMM(dt) {
    if (!dt) return null;

    const d = new Date(dt);
    if (isNaN(d)) return null;

    return String(d.getUTCHours()).padStart(2, '0') +
           String(d.getUTCMinutes()).padStart(2, '0');
}
</script>

</body>
</html>
