(() => {
    // Seller Center keeps itself current: every 20 seconds (while the tab is visible) it asks whether an order, parcel
    // or return changed. The sidebar badges update in place; pages marked [data-auto-refresh] reload, unless the seller
    // is busy (typing, or a details panel or dialog is open), in which case a "Refresh" prompt appears instead.
    const url = document.body.dataset.sellerUpdatesUrl;
    if (!url) return;

    const INTERVAL = 20000;
    const SCROLL_KEY = 'seller-live-updates-scroll';
    const autoRefresh = document.querySelector('[data-auto-refresh]') !== null;
    const prompt = document.querySelector('[data-live-update]');
    let signature = null;
    let timer;
    let pending = false;

    // Come back to the same place after an automatic reload.
    try {
        const saved = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || 'null');
        sessionStorage.removeItem(SCROLL_KEY);
        if (saved && saved.path === location.pathname + location.search) window.scrollTo(0, saved.y);
    } catch (error) { /* storage unavailable */ }

    const busy = () => {
        const active = document.activeElement;
        return (active && active.matches('input, select, textarea, [contenteditable="true"]'))
            || document.querySelector('main details[open], dialog[open]') !== null
            || String(window.getSelection?.() || '') !== '';
    };

    const reload = () => {
        try {
            sessionStorage.setItem(SCROLL_KEY, JSON.stringify({ path: location.pathname + location.search, y: window.scrollY }));
        } catch (error) { /* storage unavailable */ }
        location.reload();
    };

    const setBadges = (badges) => {
        Object.entries(badges || {}).forEach(([key, total]) => {
            document.querySelectorAll(`[data-sidebar-count="${key}"]`).forEach((badge) => {
                badge.replaceChildren();
                badge.hidden = total < 1;
                if (total < 1) return;
                const label = document.createElement('span');
                label.className = 'sr-only';
                label.textContent = ` ${badge.dataset.sidebarCountLabel || ''}`;
                badge.append(total > 99 ? '99+' : String(total), label);
            });
        });
    };

    const changed = () => {
        if (!autoRefresh) return;
        if (!pending && !busy()) {
            reload();
            return;
        }
        pending = true;
        if (prompt) prompt.hidden = false;
    };

    const check = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            setBadges(data.badges);
            if (signature !== null && data.signature !== signature) changed();
            signature = data.signature;
        } catch (error) { /* offline: try again next time */ }
    };

    const schedule = () => {
        window.clearInterval(timer);
        timer = window.setInterval(check, INTERVAL);
    };

    prompt?.querySelector('button')?.addEventListener('click', reload);
    // A form being sent reloads the page anyway; never interrupt it.
    document.addEventListener('submit', () => window.clearInterval(timer));
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) check();
    });

    check();
    schedule();
})();
