document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-account-updates]');
    if (!root) return;
    const sectionFor = path => {
        if (/notifications/.test(path)) return 'notifications';
        if (/rider-withdrawals/.test(path)) return 'withdrawals';
        if (/seller-withdrawals|logistics-payouts/.test(path)) return 'payouts';
        if (/wallet|settlement|cash-handovers|logistics\/earnings/.test(path)) return 'wallet';
        if (/orders/.test(path)) return 'orders';
        return null;
    };
    const links = [...document.querySelectorAll('.seller-sidebar__link[href], .buyer-nav-icon[href]')]
        .map(link => ({ link, section: sectionFor(new URL(link.href).pathname) }))
        // Sidebar workload counts already have their own live updater. Adding an unread-update
        // badge beside them duplicates the counter and gives the same link two different totals.
        .filter(item => item.section && (item.section === 'notifications' || !item.link.querySelector('[data-sidebar-count]')));
    for (const item of links) {
        item.badge = item.section === 'notifications' ? item.link.querySelector('[data-sidebar-count="notifications"], [data-notification-count]') : null;
        if (!item.badge) {
            item.badge = document.createElement('span');
            item.badge.className = 'account-update-count';
            item.badge.hidden = true;
            item.link.append(item.badge);
        }
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let busy = false;
    const refresh = async () => {
        if (document.hidden || busy) return;
        busy = true;
        try {
            const response = await fetch(root.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            for (const item of links) {
                const count = item.section === 'notifications' ? (data.notification_count ?? data.unread) : (data.counts[item.section] || 0);
                item.badge.textContent = count > 99 ? '99+' : count;
                item.badge.hidden = count < 1;
                item.badge.setAttribute('aria-label', `${count} unread updates`);
            }
            const active = sectionFor(location.pathname);
            if (active && active !== 'notifications' && data.counts[active] > 0 && token) {
                await fetch(root.dataset.readUrl, { method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ section: active, through: data.through }) });
            }
            const tabs = document.querySelector('[data-order-counts-url]');
            if (tabs) {
                const counters = await fetch(tabs.dataset.orderCountsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (counters.ok) {
                    const { counts } = await counters.json();
                    tabs.querySelectorAll('[data-order-count]').forEach(node => {
                        const count = Math.max(0, Number(counts?.[node.dataset.orderCount]) || 0);
                        node.textContent = count > 0 ? String(count) : '';
                        node.hidden = count < 1;
                    });
                }
            }
        } catch { /* Keep existing counts during network failures. */ }
        finally { busy = false; }
    };
    refresh();
    let timer = window.setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pagehide', () => window.clearInterval(timer), { once: true });
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            window.clearInterval(timer);
            timer = window.setInterval(refresh, 15000);
            refresh();
        }
    });
});
