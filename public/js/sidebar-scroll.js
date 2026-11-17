// Keep navigation in place across pages and refreshes, separately for each portal and account.
(() => {
    'use strict';

    document.querySelectorAll('[data-sidebar-scroll]').forEach((sidebar) => {
        const key = `zaylo:sidebar-scroll:${sidebar.dataset.sidebarScroll}`;
        let interacted = false;

        const restore = () => {
            if (interacted || !sidebar.clientHeight) return;
            try {
                const value = sessionStorage.getItem(key);
                const position = Number(value);
                if (value !== null && Number.isFinite(position) && position >= 0) {
                    // Assign directly so navigation never animates from the top of the menu.
                    sidebar.scrollTop = position;
                }
            } catch (error) { /* Navigation remains usable when browser storage is unavailable. */ }
        };

        const save = () => {
            // A hidden or non-scrolling mobile menu must not erase its desktop position.
            if (!interacted || !sidebar.clientHeight || sidebar.scrollHeight <= sidebar.clientHeight) return;
            try {
                sessionStorage.setItem(key, String(sidebar.scrollTop));
            } catch (error) { /* Browser storage may be disabled. */ }
        };

        // Late fonts and layout changes may affect the available scroll range. Stop restoring
        // as soon as the user starts interacting, so their new position always wins.
        ['pointerdown', 'wheel', 'touchstart', 'keydown'].forEach((type) => {
            sidebar.addEventListener(type, () => { interacted = true; }, { passive: true });
        });
        restore();
        sidebar.addEventListener('scroll', save, { passive: true });
        sidebar.addEventListener('click', save, { capture: true });
        window.addEventListener('pagehide', save);
        window.addEventListener('load', restore, { once: true });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                interacted = false;
                restore();
            }
        });
        if (typeof ResizeObserver === 'function') new ResizeObserver(restore).observe(sidebar);
        document.fonts?.ready.then(restore);
    });
})();
