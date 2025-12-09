<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Airport Parking Map</title>

    <!-- Mapbox -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />

    <style>
        body {
            margin: 0;
        }

        #map {
            height: 100vh;
            width: 100%;
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
    zoom: 3.8
});

/* ----------------------------------------------------------
 * Data from controller
 * -------------------------------------------------------- */
const geojson      = {!! $geojson !!};
const airports     = {!! $airportsJson !!};
const aircraftData = {!! $aircraftJson !!};

/* ----------------------------------------------------------
 * Utility: aviation-accurate 400 NM circle
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

        coords.push([
            lng2 * 180 / Math.PI,
            lat2 * 180 / Math.PI
        ]);
    }

    return {
        type: "Feature",
        geometry: {
            type: "Polygon",
            coordinates: [coords]
        }
    };
}

/* ----------------------------------------------------------
 * Utility: add aircraft arrow icon (CANVAS → PNG)
 * -------------------------------------------------------- */
function addAircraftArrowIcon() {
    const size = 64;
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');

    ctx.clearRect(0, 0, size, size);
    ctx.fillStyle = '#ffffff';

    // Arrow pointing north
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

    /* ------------------ 400 NM Rings ------------------ */
    const rings = {
        type: "FeatureCollection",
        features: []
    };

    geojson.features
        .filter(f => f.properties.type === 'airport')
        .forEach(airport => {

            const ring = createCircle(
                airport.geometry.coordinates,
                400 * 1852
            );

            ring.properties = {
                title: airport.properties.title
            };

            rings.features.push(ring);
        });

    map.addSource('airport-rings', {
        type: 'geojson',
        data: rings
    });

    map.addLayer({
        id: 'airport-rings-fill',
        type: 'fill',
        source: 'airport-rings',
        paint: {
            'fill-color': '#00c2ff',
            'fill-opacity': 0.01
        }
    });

    map.addLayer({
        id: 'airport-rings-outline',
        type: 'line',
        source: 'airport-rings',
        paint: {
            'line-color': '#00c2ff',
            'line-width': 0.1,
            'line-opacity': 1
        }
    });

    /* ------------------ Airport → Colour Lookup ------------------ */
    const airportColourMap = {};
    Object.values(airports).forEach(ap => {
        airportColourMap[ap.icao] = ap.color || '#ffffff';
    });

  /* ------------------ Airport & Parking Markers ------------------ */
geojson.features.forEach(feature => {

    const el = document.createElement('div');
    el.style.width = '10px';
    el.style.height = '10px';
    el.style.borderRadius = '50%';

    if (feature.properties.type === 'airport') {
        el.style.backgroundColor =
            airportColourMap[feature.properties.icao] ?? '#F54927';
    } else {
        el.style.backgroundColor = '#ff9800';
    }

    new mapboxgl.Marker(el)
        .setLngLat(feature.geometry.coordinates)
        .setPopup(
            new mapboxgl.Popup({ offset: 20 }).setHTML(`
                <strong>${feature.properties.title}</strong><br>
                ${feature.properties.terminal ?? ''}<br>
                AC: ${feature.properties.aircraft ?? ''}
            `)
        )
        .addTo(map);
});


    /* ------------------ Aircraft GeoJSON ------------------ */
    const aircraftGeoJSON = {
        type: 'FeatureCollection',
        features: aircraftData.map(ac => ({
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [
                    parseFloat(ac.lon),
                    parseFloat(ac.lat)
                ]
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

    /* ------------------ Aircraft Arrow Icon ------------------ */
    addAircraftArrowIcon();

    /* ------------------ Aircraft Source & Layer ------------------ */
    map.addSource('aircraft', {
        type: 'geojson',
        data: aircraftGeoJSON
    });

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

    /* ------------------ Aircraft Popup ------------------ */
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

    map.on('mouseenter', 'aircraft-arrows', () => {
        map.getCanvas().style.cursor = 'pointer';
    });
    map.on('mouseleave', 'aircraft-arrows', () => {
        map.getCanvas().style.cursor = '';
    });
});
</script>

</body>
</html>
