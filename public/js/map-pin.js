// Pinning map helper for buyer delivery addresses and logistics hub sorting locations.
// Supports both latitude/longitude and hub_latitude/hub_longitude inputs.
(() => {
    const init = (scope = document) => {
        if (!window.L || !window.ZayloMap) {
            return;
        }

        const roots = scope.querySelectorAll ? scope.querySelectorAll('[data-map-pin]') : [];
        roots.forEach((root) => {
            if (root.dataset.mapPinBound) {
                return;
            }
            root.dataset.mapPinBound = 'true';

            const latitude = root.querySelector('input[name="hub_latitude"], input[name="latitude"]');
            const longitude = root.querySelector('input[name="hub_longitude"], input[name="longitude"]');
            const status = root.querySelector('[data-map-status]');
            const clear = root.querySelector('[data-map-clear]');
            const locate = root.querySelector('[data-map-locate]');
            const mapEl = root.querySelector('[data-map]');
            if (!latitude || !longitude || !mapEl) {
                return;
            }

            const markerType = root.dataset.markerIcon || 'home';
            const pinTitle = root.dataset.pinTitle || (markerType === 'hub' ? 'Hub sorting location pin' : 'Delivery pin');
            const map = ZayloMap.create(mapEl);
            let pin = null;

            const say = message => {
                if (status) status.textContent = message;
            };

            const place = (point, zoom) => {
                const lat = Number(point.lat.toFixed(7));
                const lng = Number(point.lng.toFixed(7));
                latitude.value = lat;
                longitude.value = lng;
                if (!pin) {
                    pin = L.marker([lat, lng], { draggable: true, icon: ZayloMap.marker(markerType), keyboard: true, title: pinTitle }).addTo(map);
                    pin.on('dragend', () => place(pin.getLatLng()));
                } else {
                    pin.setLatLng([lat, lng]);
                }
                if (zoom) {
                    map.setView([lat, lng], zoom);
                }
                if (clear) clear.hidden = false;
                say('Pinned. Drag the pin to adjust your exact location.');
            };

            map.on('click', event => place(event.latlng));

            clear?.addEventListener('click', () => {
                pin?.remove();
                pin = null;
                latitude.value = '';
                longitude.value = '';
                clear.hidden = true;
                say(markerType === 'hub' ? 'No pin. Road distances will use office city coordinates.' : 'No pin. The rider will use the written address.');
            });

            if (!('geolocation' in navigator) && locate) {
                locate.hidden = true;
            }
            locate?.addEventListener('click', () => {
                say('Finding your location…');
                navigator.geolocation.getCurrentPosition(
                    position => place({ lat: position.coords.latitude, lng: position.coords.longitude }, 17),
                    () => say('Could not get your location. Allow location access, or tap the map instead.'),
                    { enableHighAccuracy: true, timeout: 15000 },
                );
            });

            if (latitude.value && longitude.value) {
                place({ lat: Number(latitude.value), lng: Number(longitude.value) }, 16);
            } else {
                if (clear) clear.hidden = true;
                say(markerType === 'hub' ? 'Tap the map to drop a pin on your hub or warehouse.' : 'Tap the map to drop a pin on your door.');
            }

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            map.invalidateSize();
                        }
                    });
                }, { threshold: 0.1 });
                observer.observe(mapEl);
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => init(document));
    document.addEventListener('zaylo:content', (event) => init(event.target));
    window.initMapPin = init;
})();
