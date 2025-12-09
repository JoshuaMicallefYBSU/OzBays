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
        pre {
            font-family: monospace;
            font-size: 13px;
        }
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
map.doubleClickZoom.disable(); // ✅ REQUIRED

/* ----------------------------------------------------------
 * Data from controller
 * -------------------------------------------------------- */
const geojson      = {!! $geojson !!};
const airports     = {!! $airportsJson !!};
const aircraftData = {!! $aircraftJson !!};

/* ----------------------------------------------------------
 * Airport → colour lookup
 * -------------------------------------------------------- */
const airportColourMap = {};
Object.entries(airports).forEach(([icao, ap]) => {
    airportColourMap[icao] = ap.color;
});

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
        const bearing = i * 2 * Math.PI / points;

        const lat2 = Math.asin(
            Math.sin(latRad) * Math.cos(d) +
            Math.cos(latRad) * Math.sin(d) * Math.cos(bearing)
        );

        const lng2 = lngRad + Math.atan2(
            Math.sin(bearing) * Math.sin(d) * Math.cos(latRad),
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
 * Utility: aircraft arrow icon
 * -------------------------------------------------------- */
function addAircraftArrowIcon() {
    const size = 64;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.moveTo(size / 2, 4);
    ctx.lineTo(size - 10, size - 4);
    ctx.lineTo(size / 2, size - 20);
    ctx.lineTo(10, size - 4);
    ctx.closePath();
    ctx.fill();

    if (!map.hasImage('aircraft-arrow')) {
        map.addImage(
            'aircraft-arrow',
            ctx.getImageData(0, 0, size, size),
            { sdf: true }
        );
    }
}

/* ----------------------------------------------------------
 * Map content
 * -------------------------------------------------------- */
map.on('load', () => {

    /* ================== AIRPORT 600 NM RINGS ================== */
    const airportRings = {
        type: "FeatureCollection",
        features: []
    };

    geojson.features
        .filter(f => f.properties.type === 'airport')
        .forEach(a => {
            const ring = createCircle(a.geometry.coordinates, 600 * 1852);
            ring.properties = {
                colour: airportColourMap[a.properties.icao] ?? '#F54927'
            };
            airportRings.features.push(ring);
        });

    map.addSource('airport-rings', {
        type: 'geojson',
        data: airportRings
    });

    map.addLayer({
        id: 'airport-rings-fill',
        type: 'fill',
        source: 'airport-rings',
        paint: {
            'fill-color': ['get', 'colour'],
            'fill-opacity': 0.04
        }
    });

    map.addLayer({
        id: 'airport-rings-outline',
        type: 'line',
        source: 'airport-rings',
        paint: {
            'line-color': ['get', 'colour'],
            'line-width': 0.6
        }
    });

    /* ================== PARKING BAYS ================== */
    geojson.features
        .filter(f => f.properties.type === 'parking')
        .forEach(f => {
            const el = document.createElement('div');
            el.style.width = '8px';
            el.style.height = '8px';
            el.style.borderRadius = '50%';
            el.style.backgroundColor = '#ff9800';
            el.style.cursor = 'pointer';

            new mapboxgl.Marker(el)
                .setLngLat(f.geometry.coordinates)
                .setPopup(
                    new mapboxgl.Popup({ offset: 10 }).setHTML(`
                        <strong>Bay ${f.properties.title}</strong><br>
                        Terminal: ${f.properties.terminal}<br>
                        Aircraft: ${f.properties.ac}<br>
                        Priority: ${f.properties.priority}
                    `)
                )
                .addTo(map);
        });

    /* ================== AIRPORT MARKERS ================== */
    geojson.features
        .filter(f => f.properties.type === 'airport')
        .forEach(f => {
            const el = document.createElement('div');
            el.style.width = '12px';
            el.style.height = '12px';
            el.style.borderRadius = '50%';
            el.style.backgroundColor =
                airportColourMap[f.properties.icao] ?? '#F54927';

            new mapboxgl.Marker(el)
                .setLngLat(f.geometry.coordinates)
                .setPopup(
                    new mapboxgl.Popup({ offset: 20 })
                        .setHTML(`<strong>${f.properties.title}</strong>`)
                )
                .addTo(map);
        });

    /* ================== AIRCRAFT ================== */
    const aircraftPoints = {
        type: 'FeatureCollection',
        features: aircraftData.map(ac => ({
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [parseFloat(ac.lon), parseFloat(ac.lat)]
            },
            properties: {
                callsign: ac.callsign,
                dep: ac.dep,
                arr: ac.arr,
                speed: Number(ac.speed),
                status: ac.status,
                colour: airportColourMap[ac.arr] ?? '#ff9800',
                bearing: Number(ac.hdg ?? 0)
            }
        }))
    };

    const aircraftRings = {
        type: 'FeatureCollection',
        features: []
    };

    aircraftPoints.features.forEach(f => {
        const ring = createCircle(f.geometry.coordinates, 40);
        ring.properties = f.properties;
        aircraftRings.features.push(ring);
    });

    map.addSource('aircraft', {
        type: 'geojson',
        data: aircraftPoints
    });

    map.addSource('aircraft-rings', {
        type: 'geojson',
        data: aircraftRings
    });

    map.addLayer({
        id: 'aircraft-rings-fill',
        type: 'fill',
        source: 'aircraft-rings',
        paint: {
            'fill-color': ['get', 'colour'],
            'fill-opacity': 0.25
        }
    });

    map.addLayer({
        id: 'aircraft-rings-outline',
        type: 'line',
        source: 'aircraft-rings',
        paint: {
            'line-color': ['get', 'colour'],
            'line-width': 1
        }
    });

    /* ================== AIRCRAFT ARROWS ================== */
    addAircraftArrowIcon();

    map.addLayer({
        id: 'aircraft-arrows',
        type: 'symbol',
        source: 'aircraft',
        layout: {
            'icon-image': 'aircraft-arrow',
            'icon-size': 0.4,
            'icon-rotate': ['get', 'bearing'],
            'icon-rotation-alignment': 'map',
            'icon-allow-overlap': true
        },
        paint: {
            'icon-color': ['get', 'colour']
        }
    });

    map.on('click', 'aircraft-arrows', e => {
        const p = e.features[0].properties;
        new mapboxgl.Popup()
            .setLngLat(e.features[0].geometry.coordinates)
            .setHTML(`
                <strong>${p.callsign}</strong><br>
                ${p.dep} → ${p.arr}<br>
                Speed: ${p.speed} kt<br>
                Status: ${p.status}
            `)
            .addTo(map);
    });

    /* ================== DOUBLE-CLICK LAT/LON PICKER ================== */
    map.on('dblclick', e => {
        const lat = e.lngLat.lat.toFixed(8);
        const lon = e.lngLat.lng.toFixed(8);

        const text = `"lat": ${lat},\n"lon": ${lon},`;
        navigator.clipboard.writeText(text);

        new mapboxgl.Popup()
            .setLngLat(e.lngLat)
            .setHTML(`<pre>${text}</pre>`)
            .addTo(map);
    });

});
</script>

</body>
</html>
