// Shared map helpers for Leaflet with OpenStreetMap tiles: the address pin picker and the parcel tracking map.
window.ZayloMap = (() => {
    // The middle of the Philippines, for maps without a pin yet.
    const PHILIPPINES = { center: [12.8797, 121.774], zoom: 6 };

    // The rider is shown with the ZAYLO favicon in a bronze ring (public/images/map-rider.png, next to this script's js/ folder).
    const script = document.currentScript?.src;
    const riderImage = script ? new URL('../images/map-rider.png', script).href : '/images/map-rider.png';

    const marker = kind => kind === 'rider' ? L.icon({
        iconUrl: riderImage,
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        className: 'map-marker-rider',
    }) : L.divIcon({
        className: '',
        html: `<div class="map-marker map-marker--${kind}" style="width:22px;height:22px"></div>`,
        iconSize: [22, 22],
        iconAnchor: kind === 'home' ? [11, 22] : [11, 11],
    });

    const create = element => {
        const map = L.map(element, { scrollWheelZoom: false });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        map.setView(PHILIPPINES.center, PHILIPPINES.zoom);
        return map;
    };

    // Road geometry is supplied by OSRM as GeoJSON (longitude, latitude).
    const roadRoute = (map, status) => {
        const layers = L.layerGroup().addTo(map);
        let controller = null;
        let key = null;
        let points = [];
        let fitted = false;
        const clear = () => {
            controller?.abort();
            controller = null;
            key = null;
            points = [];
            fitted = false;
            layers.clearLayers();
            status.textContent = '';
        };
        const update = async (from, to) => {
            if (!from || !to) {
                clear();
                return;
            }
            const next = `${from.longitude},${from.latitude};${to.longitude},${to.latitude}`;
            if (next === key) return;
            controller?.abort();
            const request = new AbortController();
            controller = request;
            key = next;
            status.textContent = 'Loading road route…';
            const timeout = window.setTimeout(() => request.abort(), 10000);
            try {
                const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${next}?overview=full&geometries=geojson&steps=false`, {
                    signal: request.signal,
                    credentials: 'omit',
                });
                if (!response.ok) throw new Error('Route unavailable');
                const data = await response.json();
                const coordinates = data.routes?.[0]?.geometry?.coordinates;
                if (data.code !== 'Ok' || !Array.isArray(coordinates) || coordinates.length < 2
                    || !coordinates.every(at => Array.isArray(at) && Number.isFinite(at[0]) && Number.isFinite(at[1])
                        && Math.abs(at[0]) <= 180 && Math.abs(at[1]) <= 90)) {
                    throw new Error('No road route');
                }
                if (controller !== request) return;
                points = coordinates.map(([lng, lat]) => [lat, lng]);
                layers.clearLayers();
                L.polyline(points, { color: '#fff', weight: 9, opacity: 1, interactive: false }).addTo(layers);
                L.polyline(points, { color: '#2563eb', weight: 5, opacity: 1, interactive: false }).addTo(layers);
                const bounds = L.latLngBounds([...points, [from.latitude, from.longitude], [to.latitude, to.longitude]]);
                if (!fitted || !map.getBounds().contains(bounds)) {
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
                    fitted = true;
                }
                status.textContent = 'Blue road: suggested route to your delivery pin.';
            } catch {
                if (controller !== request) return;
                layers.clearLayers();
                points = [];
                key = null; // Retry on the next location refresh, even if the rider is stationary.
                status.textContent = 'Road route unavailable. Retrying with the next location update.';
            } finally {
                window.clearTimeout(timeout);
            }
        };
        return { update, clear };
    };

    return { create, marker, roadRoute, PHILIPPINES };
})();
