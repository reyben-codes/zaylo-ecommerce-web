document.addEventListener('DOMContentLoaded', () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const closeButton = toast.querySelector('[data-toast-close]');
        const timeout = Number.parseInt(toast.dataset.timeout, 10) || 5200;
        let timer;
        let startedAt;
        let remaining = timeout;
        let dismissed = false;

        const remove = () => {
            toast.remove();

            const region = document.querySelector('[data-toast-region]');
            if (region && !region.querySelector('[data-toast]')) {
                region.remove();
            }
        };

        const dismiss = () => {
            if (dismissed) return;

            dismissed = true;
            window.clearTimeout(timer);
            toast.classList.add('is-leaving');
            window.setTimeout(remove, reducedMotion ? 0 : 460);
        };

        const pauseTimer = () => {
            if (!timer || dismissed) return;

            window.clearTimeout(timer);
            remaining = Math.max(0, remaining - (Date.now() - startedAt));
            timer = undefined;
        };

        const startTimer = () => {
            if (timer || dismissed) return;

            startedAt = Date.now();
            timer = window.setTimeout(dismiss, remaining);
        };

        closeButton?.addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', pauseTimer);
        toast.addEventListener('mouseleave', startTimer);
        toast.addEventListener('focusin', pauseTimer);
        toast.addEventListener('focusout', (event) => {
            if (!toast.contains(event.relatedTarget)) startTimer();
        });

        startTimer();
    });
});
