/**
 * Logistics Attendance page: keeps the hub's attendance QR code current. Each code works for about two minutes,
 * with a countdown to the next 30-second rotation, and refreshes when the tab comes back into view.
 * "Full screen" shows the code large on a hub monitor or tablet.
 */
(function () {
    const panel = document.querySelector('[data-attendance-qr]');
    if (!panel) return;

    const image = panel.querySelector('[data-attendance-qr-image]');
    const status = panel.querySelector('[data-attendance-qr-status]');
    const fullscreen = panel.querySelector('[data-attendance-fullscreen]');
    const countdown = panel.querySelector('[data-attendance-countdown]');
    const countdownLabel = panel.querySelector('[data-attendance-countdown-label]');
    const progress = panel.querySelector('[data-attendance-progress]');
    const windowSeconds = Number(panel.dataset.windowSeconds) || 30;
    const url = panel.dataset.codeUrl;
    const note = status.textContent;
    let timer = null;
    let failures = 0;
    let inFlight = false;
    let deadline = 0;
    let phase = 'refresh';
    let lastLabel = '';

    function updateCountdown() {
        const remaining = Math.max(0, (deadline - performance.now()) / 1000);
        const seconds = Math.ceil(remaining);
        const label = phase === 'retry'
            ? `Retrying in ${seconds}s`
            : phase === 'loading' || remaining === 0
                ? 'Loading next QR code…'
                : `Next QR code in ${seconds}s`;
        progress.max = windowSeconds;
        progress.value = phase === 'refresh' ? Math.min(windowSeconds, remaining) : 0;
        // Avoid announcing every tick or repeatedly replacing the timer's text node.
        if (label !== lastLabel) {
            countdownLabel.textContent = label;
            progress.setAttribute('aria-valuetext', label);
            lastLabel = label;
        }
    }

    function schedule(seconds, nextPhase = 'refresh', startedAt = performance.now()) {
        clearTimeout(timer);
        phase = nextPhase;
        deadline = startedAt + Math.max(0, Number(seconds) || 0) * 1000;
        updateCountdown();
        // Wait for the boundary; asking early can return the same QR code again.
        timer = setTimeout(refresh, Math.max(0, deadline - performance.now()) + 100);
    }

    async function refresh() {
        if (document.hidden || inFlight) return;
        clearTimeout(timer);
        inFlight = true;
        const startedAt = performance.now();
        if (startedAt >= deadline) {
            phase = 'loading';
            updateCountdown();
        }
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: AbortSignal.timeout(10000),
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (!data.image || !Number.isFinite(Number(data.refresh_in))) throw new Error('Invalid QR response');
            image.src = data.image;
            image.classList.remove('is-stale');
            status.textContent = note;
            failures = 0;
            schedule(data.refresh_in, 'refresh', startedAt);
        } catch (error) {
            // Older codes stay valid for a while, so keep retrying quietly before warning.
            failures += 1;
            if (failures >= 3) {
                image.classList.add('is-stale');
                status.textContent = 'Could not load a new code. Check the connection; retrying…';
            }
            schedule(10, 'retry');
        } finally {
            inFlight = false;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    schedule(Number(panel.dataset.refreshIn) || 30);
    countdown.hidden = false;
    setInterval(() => {
        if (!document.hidden) updateCountdown();
    }, 100);
    // Synchronize after page rendering and loading rather than starting a fresh full window.
    refresh();

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
