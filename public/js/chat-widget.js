(() => {
    const root = document.querySelector('[data-chat-widget]');
    if (!root) return;
    const find = selector => root.querySelector(selector);
    const bubble = find('[data-chat-bubble]');
    const panel = find('[data-chat-panel]');
    const frame = find('[data-chat-frame]');
    const drop = find('[data-chat-drop]');
    const target = find('[data-chat-drop-target]');
    const notice = find('[data-chat-notice]');
    const expand = find('[data-chat-expand]');
    const chatUrl = new URL(root.dataset.chatUrl, location.href);
    const key = `zaylo-chat-bubble:${root.dataset.userId}`;
    let state = {}, gesture = null, suppressClickUntil = 0, noticeTimer;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let returnAnimation = null;
    const stopReturn = () => {
        if (!returnAnimation) return;
        returnAnimation.onfinish = null;
        returnAnimation.cancel();
        returnAnimation = null;
    };
    try { state = JSON.parse(sessionStorage.getItem(key)) || {}; } catch { /* Storage may be unavailable. */ }
    if (typeof state !== 'object' || Array.isArray(state)) state = {};
    const save = () => { try { sessionStorage.setItem(key, JSON.stringify(state)); } catch { /* Keep working without storage. */ } };
    const bounds = () => ({ width: document.documentElement.clientWidth, height: window.innerHeight });
    const place = (x, y) => {
        const { width, height } = bounds();
        const size = bubble.offsetWidth || 60;
        state.x = Math.max(8, Math.min(width - size - 8, x));
        state.y = Math.max(8, Math.min(height - size - 8, y));
        Object.assign(bubble.style, { left: `${state.x}px`, top: `${state.y}px`, right: 'auto', bottom: 'auto' });
    };
    const announce = text => {
        clearTimeout(noticeTimer);
        notice.textContent = text; notice.hidden = false;
        noticeTimer = setTimeout(() => { notice.hidden = true; }, 5000);
    };
    const glideTo = (x, y) => {
        const start = bubble.getBoundingClientRect();
        stopReturn();
        place(x, y);
        if (reducedMotion.matches || !bubble.animate) return;
        returnAnimation = bubble.animate([
            { left: `${start.left}px`, top: `${start.top}px` },
            { left: `${state.x}px`, top: `${state.y}px` },
        ], { duration: 320, easing: 'cubic-bezier(.22, 1, .36, 1)' });
        returnAnimation.onfinish = () => { returnAnimation = null; };
    };
    const minimize = (focus = true) => {
        panel.hidden = true;
        bubble.setAttribute('aria-expanded', 'false');
        bubble.setAttribute('aria-label', 'Open messages');
        if (focus && !bubble.hidden) bubble.focus({ preventScroll: true });
    };
    const open = url => {
        if (state.dismissed) { const { width, height } = bounds(); place(width - 76, height - 76); }
        state.dismissed = false; save();
        bubble.hidden = false; notice.hidden = true;
        if (!frame.hasAttribute('src') || url) {
            const destination = new URL(url || chatUrl.href);
            destination.searchParams.set('compact', '1');
            frame.src = destination.href;
        }
        panel.hidden = false;
        bubble.setAttribute('aria-expanded', 'true');
        bubble.setAttribute('aria-label', 'Minimize messages');
        find('[data-chat-minimize]').focus({ preventScroll: true });
        frame.contentWindow?.postMessage({ type: 'zaylo-chat-open' }, chatUrl.origin);
    };
    const dismiss = () => {
        stopReturn();
        minimize(false); bubble.hidden = true;
        state.dismissed = true; save();
        announce('Chat bubble hidden. Open Messages to bring it back.');
        // Dismissal only hides the UI; the existing conversation and draft stay intact.
        const opener = [...document.querySelectorAll('a[href]')].find(link => !root.contains(link) && link.href === chatUrl.href);
        opener?.focus({ preventScroll: true });
    };
    const resetDrop = () => {
        drop.hidden = true; drop.classList.remove('is-active');
        bubble.classList.remove('is-dragging', 'is-over-drop');
    };
    const overDrop = () => {
        const a = bubble.getBoundingClientRect(), b = target.getBoundingClientRect();
        return Math.hypot(a.left + a.width / 2 - b.left - b.width / 2, a.top + a.height / 2 - b.top - b.height / 2) < 60;
    };
    bubble.addEventListener('pointerdown', event => {
        if (!event.isPrimary || event.button !== 0) return;
        const rect = bubble.getBoundingClientRect();
        // Picking up a returning bubble starts at its visible position, without a jump.
        if (returnAnimation) { stopReturn(); place(rect.left, rect.top); }
        gesture = { id: event.pointerId, x: event.clientX, y: event.clientY, left: rect.left, top: rect.top, moved: false };
        bubble.setPointerCapture(event.pointerId);
    });
    bubble.addEventListener('pointermove', event => {
        if (!gesture || event.pointerId !== gesture.id) return;
        const dx = event.clientX - gesture.x, dy = event.clientY - gesture.y;
        if (!gesture.moved && Math.hypot(dx, dy) < 6) return;
        gesture.moved = true; minimize(false);
        notice.hidden = true; drop.hidden = false; bubble.classList.add('is-dragging');
        place(gesture.left + dx, gesture.top + dy);
        const active = overDrop();
        drop.classList.toggle('is-active', active); bubble.classList.toggle('is-over-drop', active);
    });
    const finishDrag = (event, cancelled = false) => {
        if (!gesture || event.pointerId !== gesture.id) return;
        const previous = gesture; gesture = null;
        if (previous.moved) {
            suppressClickUntil = performance.now() + 400;
            if (cancelled) { glideTo(previous.left, previous.top); save(); }
            else if (overDrop()) dismiss();
            else {
                const { width } = bounds();
                glideTo(state.x + 30 < width / 2 ? 12 : width - 72, state.y);
                save();
            }
        }
        resetDrop();
        if (bubble.hasPointerCapture(event.pointerId)) bubble.releasePointerCapture(event.pointerId);
    };
    bubble.addEventListener('pointerup', event => finishDrag(event));
    bubble.addEventListener('pointercancel', event => finishDrag(event, true));
    bubble.addEventListener('lostpointercapture', event => finishDrag(event, true));
    bubble.addEventListener('click', event => {
        if (event.detail && performance.now() < suppressClickUntil) return;
        panel.hidden ? open() : minimize();
    });
    bubble.addEventListener('keydown', event => {
        if (event.key === 'Delete' || event.key === 'Backspace') { event.preventDefault(); dismiss(); }
    });
    find('[data-chat-minimize]').addEventListener('click', () => minimize());
    find('[data-chat-dismiss]').addEventListener('click', dismiss);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !event.isComposing && !panel.hidden) minimize();
    });
    // Keep ordinary links as a working fallback and preserve Ctrl/Cmd-click for a full page.
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || root.contains(link) || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== chatUrl.origin || url.pathname !== chatUrl.pathname || url.searchParams.has('compact')) return;
        event.preventDefault();
        open(url.searchParams.has('conversation') ? url.href : undefined);
    });
    window.addEventListener('message', event => {
        if (event.origin === chatUrl.origin && event.source === frame.contentWindow && event.data?.type === 'zaylo-chat-minimize') minimize();
    });
    frame.addEventListener('load', () => {
        try {
            const current = new URL(frame.contentWindow.location.href);
            if (current.origin === chatUrl.origin && current.pathname === chatUrl.pathname) {
                current.searchParams.delete('compact'); expand.href = current.href;
            }
        } catch { /* The full Messages link remains available if the session expires. */ }
    });
    window.addEventListener('resize', () => { stopReturn(); if (Number.isFinite(state.x) && Number.isFinite(state.y)) place(state.x, state.y); });
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) stopReturn(); });
    root.hidden = false; bubble.hidden = Boolean(state.dismissed);
    if (Number.isFinite(state.x) && Number.isFinite(state.y)) place(state.x, state.y);
})();
