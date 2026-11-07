// Address form: the buyer pins the exact spot of the address by tapping the map, dragging the pin, or using the
// device's location. The pin is saved in the hidden latitude and longitude fields; removing it clears them.
// Starts after every deferred script has run (DOMContentLoaded), so Leaflet and zaylo-map.js are loaded whatever order
// the page lists the scripts in.
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-map-pin]');
    if (!root || !window.L || !window.ZayloMap) {
        return;
    }
    const latitude = root.querySelector('input[name="latitude"]');
    const longitude = root.querySelector('input[name="longitude"]');
    const status = root.querySelector('[data-map-status]');
    const clear = root.querySelector('[data-map-clear]');
    const locate = root.querySelector('[data-map-locate]');
    const map = ZayloMap.create(root.querySelector('[data-map]'));
    let pin = null;

    const say = message => {
        status.textContent = message;
    };

    const place = (point, zoom) => {
        const lat = Number(point.lat.toFixed(7));
        const lng = Number(point.lng.toFixed(7));
        latitude.value = lat;
        longitude.value = lng;
        if (!pin) {
            pin = L.marker([lat, lng], { draggable: true, icon: ZayloMap.marker('home'), keyboard: true, title: 'Delivery pin' }).addTo(map);
            pin.on('dragend', () => place(pin.getLatLng()));
        } else {
            pin.setLatLng([lat, lng]);
        }
        if (zoom) {
            map.setView([lat, lng], zoom);
        }
        clear.hidden = false;
        say('Pinned. Drag the pin to move it.');
    };

    map.on('click', event => place(event.latlng));

    clear.addEventListener('click', () => {
        pin?.remove();
        pin = null;
        latitude.value = '';
        longitude.value = '';
        clear.hidden = true;
        say('No pin. The rider will use the written address.');
    });

    if (!('geolocation' in navigator)) {
        locate.hidden = true;
    }
    locate.addEventListener('click', () => {
        say('Finding your location…');
        navigator.geolocation.getCurrentPosition(
            position => place({ lat: position.coords.latitude, lng: position.coords.longitude }, 17),
            () => say('Could not get your location. Allow location access, or tap the map instead.'),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    });

    if (latitude.value && longitude.value) {
        place({ lat: Number(latitude.value), lng: Number(longitude.value) }, 17);
    } else {
        clear.hidden = true;
        say('Tap the map to drop a pin on your door.');
    }
});
