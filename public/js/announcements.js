// Hide announcements the viewer dismissed (remembered per browser; a changed announcement shows again).
(() => {
    const KEY = 'zaylo.dismissedAnnouncements';
    const read = () => {
        try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (_) { return []; }
    };
    const save = (ids) => {
        try { localStorage.setItem(KEY, JSON.stringify(ids.slice(-50))); } catch (_) { /* storage blocked: dismiss for this page only */ }
    };

    const dismissed = read();
    document.querySelectorAll('[data-announcement]').forEach((item) => {
        if (dismissed.includes(item.dataset.announcement)) {
            item.hidden = true;
        }
        item.querySelector('[data-announcement-close]')?.addEventListener('click', () => {
            item.hidden = true;
            save([...read(), item.dataset.announcement]);
        });
    });
})();
