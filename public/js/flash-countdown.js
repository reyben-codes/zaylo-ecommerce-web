(() => {
    // Counts down to the end of the soonest flash sale on the home page, then reloads to show what is still on sale.
    document.querySelectorAll('[data-flash-countdown]').forEach((timer) => {
        const ends = Date.parse(timer.dataset.flashCountdown);
        if (Number.isNaN(ends)) return;
        const parts = {
            hours: timer.querySelector('[data-unit="hours"]'),
            minutes: timer.querySelector('[data-unit="minutes"]'),
            seconds: timer.querySelector('[data-unit="seconds"]'),
        };
        const pad = (value) => String(value).padStart(2, '0');
        let interval;

        const tick = () => {
            const left = Math.max(0, Math.floor((ends - Date.now()) / 1000));
            parts.hours.textContent = pad(Math.floor(left / 3600));
            parts.minutes.textContent = pad(Math.floor((left % 3600) / 60));
            parts.seconds.textContent = pad(left % 60);
            if (left === 0) {
                window.clearInterval(interval);
                window.setTimeout(() => location.reload(), 1500);
            }
        };

        tick();
        interval = window.setInterval(tick, 1000);
    });
})();
