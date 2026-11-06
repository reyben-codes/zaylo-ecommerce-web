// Shared map helpers for Leaflet with OpenStreetMap tiles: the address pin picker and the parcel tracking map.
window.ZayloMap = (() => {
    // The middle of the Philippines, for maps without a pin yet.
    const PHILIPPINES = { center: [12.8797, 121.774], zoom: 6 };

    const marker = kind => L.divIcon({
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

    return { create, marker, PHILIPPINES };
})();
