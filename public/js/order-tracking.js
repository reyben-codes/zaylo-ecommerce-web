// Order tracking map: the buyer's pin and, while the parcel is out for delivery, the rider's last reported position.
// It refreshes as often as the server says while the page is visible, and stops once the parcel is delivered.
(() => {
    const root = document.querySelector('[data-order-tracking]');
    if (!root || !window.L || !window.ZayloMap) {
        return;
    }
    const map = ZayloMap.create(root.querySelector('[data-map]'));
    const stage = root.querySelector('[data-tracking-stage]');
    const message = root.querySelector('[data-tracking-message]');
    const updated = root.querySelector('[data-tracking-updated]');
    let home = null;
    let rider = null;
    let fitted = false;
    let timer = null;

    const show = (marker, point, kind, title) => {
        if (!point) {
            marker?.remove();
            return null;
        }
        const at = [point.latitude, point.longitude];
        if (marker) {
            marker.setLatLng(at);
            return marker;
        }
        return L.marker(at, { icon: ZayloMap.marker(kind), title, keyboard: false }).addTo(map);
    };

    const render = tracking => {
        stage.textContent = tracking.stage_label;
        message.textContent = tracking.message;
        home = show(home, tracking.destination, 'home', 'Your delivery pin');
        rider = show(rider, tracking.rider, 'rider', 'Your rider');
        updated.textContent = tracking.rider?.updated_at
            ? 'Rider location updated ' + new Date(tracking.rider.updated_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
            : '';
        const points = [home, rider].filter(Boolean).map(marker => marker.getLatLng());
        if (points.length && !fitted) {
            points.length === 1 ? map.setView(points[0], 16) : map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
            fitted = true;
        } else if (rider && home) {
            // Keep both in view as the rider moves.
            const bounds = L.latLngBounds([home.getLatLng(), rider.getLatLng()]);
            if (!map.getBounds().contains(bounds)) {
                map.fitBounds(bounds, { padding: [40, 40] });
            }
        }
        return tracking;
    };

    const refresh = async () => {
        try {
            const response = await fetch(root.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) {
                return;
            }
            const { tracking } = await response.json();
            render(tracking);
            if (['delivered', 'returned', 'cancelled'].includes(tracking.stage)) {
                stop();
            }
        } catch {
            // Try again on the next tick.
        }
    };

    const start = () => {
        stop();
        timer = window.setInterval(refresh, (Number(root.dataset.refreshSeconds) || 15) * 1000);
    };
    const stop = () => {
        window.clearInterval(timer);
        timer = null;
    };

    try {
        render(JSON.parse(root.dataset.initial));
    } catch {
        refresh();
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stop();
        } else {
            refresh();
            start();
        }
    });
    start();
})();
