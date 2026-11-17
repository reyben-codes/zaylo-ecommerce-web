// Nonmodal account actions: kept beside their trigger and outside scrolling tables.
(() => {
    'use strict';
    let active = null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    document.querySelectorAll('[data-account-actions]').forEach((wrapper, index) => {
        const trigger = wrapper.querySelector('[data-account-actions-toggle]');
        const panel = wrapper.querySelector('[data-account-actions-panel]');
        const closeButton = panel.querySelector('[data-account-actions-close]');
        const title = panel.querySelector('[data-account-actions-title]');
        panel.id = `account-actions-panel-${index}`;
        title.id = `account-actions-title-${index}`;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-labelledby', title.id);
        trigger.setAttribute('aria-controls', panel.id);
        panel.classList.add('account-actions__panel--floating');
        panel.hidden = true;
        panel.inert = true;
        document.body.appendChild(panel);
        trigger.hidden = false;
        closeButton.hidden = false;
        let animation = null;
        let opened = false;

        const position = () => {
            if (!opened) return;
            const gap = 8;
            const margin = 12;
            const anchor = trigger.getBoundingClientRect();
            panel.style.maxHeight = `${Math.max(0, window.innerHeight - margin * 2)}px`;
            const box = panel.getBoundingClientRect();
            const right = anchor.right + gap;
            const left = right + box.width <= window.innerWidth - margin ? right : anchor.left - gap - box.width;
            panel.style.left = `${Math.max(margin, Math.min(left, window.innerWidth - box.width - margin))}px`;
            panel.style.top = `${Math.max(margin, Math.min(anchor.top, window.innerHeight - box.height - margin))}px`;
        };

        const transition = (show) => {
            const current = getComputedStyle(panel);
            const from = panel.hidden ? { opacity: 0, transform: 'translateY(-6px)' }
                : { opacity: current.opacity, transform: current.transform };
            animation?.cancel();
            panel.hidden = false;
            if (reducedMotion.matches || typeof panel.animate !== 'function') {
                panel.hidden = !show;
                return;
            }
            animation = panel.animate([from, { opacity: show ? 1 : 0, transform: show ? 'none' : 'translateY(-6px)' }],
                { duration: 300, easing: 'ease-out' });
            animation.onfinish = () => {
                panel.hidden = !opened;
                animation = null;
            };
        };

        const close = (restoreFocus = true) => {
            if (!opened) return;
            opened = false;
            panel.inert = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (active?.panel === panel) active = null;
            if (restoreFocus) trigger.focus();
            transition(false);
        };

        trigger.addEventListener('click', () => {
            if (opened) return close();
            active?.close(false);
            opened = true;
            panel.inert = false;
            trigger.setAttribute('aria-expanded', 'true');
            transition(true);
            position();
            closeButton.focus({ preventScroll: true });
            active = { panel, trigger, close, position };
        });
        closeButton.addEventListener('click', () => close());
        if (typeof ResizeObserver === 'function') new ResizeObserver(position).observe(panel);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('dialog[open]')) return;
        if (active && !active.panel.contains(event.target) && !active.trigger.contains(event.target)) active.close(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && active && !document.querySelector('dialog[open]')) {
            event.preventDefault();
            active.close();
        }
    });
    window.addEventListener('resize', () => active?.position());
    document.addEventListener('scroll', () => active?.position(), { passive: true, capture: true });
})();
