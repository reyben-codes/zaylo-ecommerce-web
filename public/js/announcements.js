// Hide announcements the viewer dismissed and open the pop-ups they haven't seen, one at a time (remembered per browser;
// a changed announcement shows again).
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

    // Not inside the chat bubble's frame, which loads a whole page: the page around it already showed the pop-up.
    if (window.self !== window.top) return;
    const SEEN_KEY = 'zaylo.seenAnnouncementModals';
    const readSeen = () => {
        try { return JSON.parse(localStorage.getItem(SEEN_KEY) || '[]'); } catch (_) { return []; }
    };
    const seen = readSeen();
    const unseen = [...document.querySelectorAll('[data-announcement-modal]')].filter((modal) => !seen.includes(modal.dataset.announcementModal));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const showNext = () => {
        const modal = unseen.shift();
        if (!modal || typeof modal.showModal !== 'function') return;
        modal.addEventListener('close', showNext, { once: true });
        modal.querySelector('[data-announcement-modal-close]').addEventListener('click', () => {
            if (reducedMotion.matches) { modal.close(); return; }
            modal.classList.add('is-closing');
            window.setTimeout(() => { modal.classList.remove('is-closing'); modal.close(); }, 200);
        }, { once: true });
        modal.showModal();
        // Marked as soon as it opens, so it can't come back however the viewer leaves it.
        try { localStorage.setItem(SEEN_KEY, JSON.stringify([...readSeen(), modal.dataset.announcementModal].slice(-50))); } catch (_) { /* storage blocked: it opens again next page */ }
    };
    showNext();
})();
