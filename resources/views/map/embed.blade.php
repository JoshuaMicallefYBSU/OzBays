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
                    popup.setHTML(`
                        <strong>Bay ${bay.bay}</strong><br>
                        ${label}
                    `);
                }
            });

            console.log(bayMarkers);

        })
        .catch(err => console.error('Bay refresh failed', err));
}

function formatTime(datetime) {
    if (!datetime) return 'N/A';

    const d = new Date(datetime);
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');

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
                        bay: ac.map_bay ? ac.map_bay.bay : 'N/A',
                        bearing: Number(ac.hdg ?? 0)
                    }
                }))
            };

            const aircraftRings = {
                type: 'FeatureCollection',
                features: aircraftPoints.features.map(f => {
                    const ring = createCircle(f.geometry.coordinates, 30);
                    ring.properties = f.properties;
                    return ring;
                })
            };

            map.getSource('aircraft').setData(aircraftPoints);
            map.getSource('aircraft-rings').setData(aircraftRings);
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
                new mapboxgl.Popup({ offset: 10 }).setHTML(`
                    <strong>${f.properties.icao} &mdash; Bay ${f.properties.bay}</strong><br>
                    Status: ${f.properties.status}
                `)
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
                new mapboxgl.Popup({ offset: 20 }).setHTML(`
                    <strong><span style="color:${f.properties.color ?? '#F54927'};">&#9679;</span> ${f.properties.title}</strong><br>
                    ${f.properties.name} Airport<br>
                    Tracking radius: 200NM
                `)
            )
            .addTo(map);
    });

    /* ================== AIRCRAFT SOURCES ================== */
    map.addSource('aircraft', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] }
    });

    map.addSource('aircraft-rings', {
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] }
    });

    map.addLayer({
        id: 'aircraft-rings-fill',
        type: 'fill',
        source: 'aircraft-rings',
        paint: { 'fill-color': ['get','colour'], 'fill-opacity': 0.25 }
    });

    map.addLayer({
        id: 'aircraft-rings-outline',
        type: 'line',
        source: 'aircraft-rings',
        paint: { 'line-color': ['get','colour'], 'line-width': 1 }
    });

    addAircraftArrowIcon();

    map.addLayer({
        id: 'aircraft-arrows',
        type: 'symbol',
        source: 'aircraft',
        layout: {
            'icon-image': 'aircraft-arrow',
            // Shrink arrows at low zoom so busy airports don't turn into a solid blob
            'icon-size': [
                'interpolate', ['linear'], ['zoom'],
                3, 0.22,
                6, 0.35,
                10, 0.5
            ],
            'icon-rotate': ['get','bearing'],
            'icon-rotation-alignment': 'map',
            // Let Mapbox hide overlapping arrows instead of stacking them illegibly;
            // they reappear as you zoom in and space opens up.
            'icon-allow-overlap': false,
            'icon-ignore-placement': false
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
            .setHTML(`
                <strong>${p.callsign}</strong><br>
                ${p.dep} → ${p.arr}<br>
                ${p.alt}ft | ${p.speed}kt | ${p.ac}<br>
                Status: ${p.status}<br>
                ELT: ${p.elt}z | EIBT: ${p.eibt}z<br>
                Assigned Bay: ${p.bay}<br>
            `)
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
    refreshAircraft();
    setInterval(refreshAircraft, 5000);

    refreshBayColours();
    setInterval(refreshBayColours, 15000);

});

function toHHMM(dt) {
    if (!dt) return null;

    const d = new Date(dt);
    if (isNaN(d)) return null;

    return String(d.getHours()).padStart(2, '0') +
           String(d.getMinutes()).padStart(2, '0');
}
</script>

</body>
</html>
