/**
 * Logistics Attendance page: keeps the hub's attendance QR code current. Each code works for about two minutes,
 * so the page loads the next one just before the shown one changes, and again when the tab comes back into view.
 * "Full screen" shows the code large on a hub monitor or tablet.
 */
(function () {
    const panel = document.querySelector('[data-attendance-qr]');
    if (!panel) return;

    const image = panel.querySelector('[data-attendance-qr-image]');
    const status = panel.querySelector('[data-attendance-qr-status]');
    const fullscreen = panel.querySelector('[data-attendance-fullscreen]');
    const url = panel.dataset.codeUrl;
    const note = status.textContent;
    let timer = null;
    let failures = 0;

    function schedule(seconds) {
        clearTimeout(timer);
        // A second early, so the new code is up before the old one changes.
        timer = setTimeout(refresh, Math.max(1, seconds - 1) * 1000);
    }

    async function refresh() {
        if (document.hidden) return;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            image.src = data.image;
            image.classList.remove('is-stale');
            status.textContent = note;
            failures = 0;
            schedule(data.refresh_in);
        } catch (error) {
            // Older codes stay valid for a while, so keep retrying quietly before warning.
            failures += 1;
            if (failures >= 3) {
                image.classList.add('is-stale');
                status.textContent = 'Could not load a new code. Check the connection; retrying…';
            }
            schedule(10);
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    schedule(Number(panel.dataset.refreshIn) || 30);

    if (fullscreen && panel.requestFullscreen) {
        const label = fullscreen.querySelector('span');
        const icon = fullscreen.querySelector('i');
        fullscreen.hidden = false;
        fullscreen.addEventListener('click', () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                panel.requestFullscreen().catch(() => {});
            }
        });
        document.addEventListener('fullscreenchange', () => {
            const on = document.fullscreenElement === panel;
            label.textContent = on ? 'Exit full screen' : 'Full screen';
            icon.className = on ? 'fas fa-compress' : 'fas fa-expand';
        });
    }
})();
