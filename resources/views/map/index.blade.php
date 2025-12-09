<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Airport Parking Map</title>

    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />

    <style>
        body { margin: 0; }
        #map { height: 100vh; width: 100%; }
        pre { font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>

<div id="map"></div>

<script>
/* ----------------------------------------------------------
 * Map setup
 * -------------------------------------------------------- */
mapboxgl.accessToken = 'pk.eyJ1Ijoiam9zaHVhbWljYWxsZWZ5YnN1IiwiYSI6ImNsb241cndobzB6Y2oyam5ya3JvdzVndGMifQ.EEFitNQf_9gmMdnQO4ywXw';

const map = new mapboxgl.Map({
    container: 'map',
    style: 'mapbox://styles/mapbox/dark-v11',
    center: [133.7751, -25.2744],
    zoom: 3.8,
    pitch: 0,
    bearing: 0,
    projection: 'mercator'
});

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

function bayStatusMap(status) {
    switch (status) {
        case 1:
            return { color: 'orange', label: 'Booked' };
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

                const { color, label } = bayStatusMap(bay.status);

                bayMarkers[key].el.style.backgroundColor = color;

                const popup = bayMarkers[key].marker.getPopup();
                if (popup) {
                    popup.setHTML(`
                        <strong>Bay ${bay.bay}</strong><br>
                        Status: ${label}
                    `);
                }
            });

            console.log(bayMarkers);

        })
        .catch(err => console.error('Bay refresh failed', err));
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
                        status: ac.status,
                        colour: airportColourMap[ac.arr] ?? '#787777',
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

        const ring = createCircle(a.geometry.coordinates, 600 * 1852);

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
                    <strong>Bay ${f.properties.bay}</strong><br>
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
                    <strong>${f.properties.title}</strong><br>
                    Name: ${f.properties.name} Airport
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
            'icon-size': 0.4,
            'icon-rotate': ['get','bearing'],
            'icon-rotation-alignment': 'map',
            'icon-allow-overlap': true
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
                Speed: ${p.speed} kt<br>
                Status: ${p.status}<br>
                Assigned Bay: N/A
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
</script>

</body>
</html>
