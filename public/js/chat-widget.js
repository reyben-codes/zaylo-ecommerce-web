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
    // While minimized after opening a conversation, the bubble shows that person (photo, or initials) with a small
    // chat badge instead of the plain chat icon. The person and the open conversation are remembered across pages.
    const icon = bubble.querySelector('svg');
    const face = document.createElement('span');
    face.className = 'chat-widget-face';
    face.setAttribute('aria-hidden', 'true');
    const badge = document.createElement('span');
    badge.className = 'chat-widget-face-badge';
    badge.setAttribute('aria-hidden', 'true');
    if (icon) badge.append(icon.cloneNode(true));
    bubble.append(face, badge);
    const initialsOf = name => name.trim().split(/\s+/).slice(0, 2).map(word => [...word][0] || '').join('').toUpperCase() || '?';
    const cleanPartner = partner => {
        const name = typeof partner?.name === 'string' ? partner.name.trim().slice(0, 80) : '';
        if (!name) return null;
        let avatar = null;
        try {
            const url = new URL(partner.avatar, location.href);
            if (url.protocol === 'https:' || (url.protocol === 'http:' && url.origin === location.origin)) avatar = url.href;
        } catch { /* No usable photo: initials are shown. */ }
        return { name, avatar };
    };
    const bubbleLabel = () => (state.partner ? `Open chat with ${state.partner.name}` : 'Open messages');
    const showPartner = () => {
        const partner = state.partner;
        bubble.classList.toggle('has-partner', Boolean(partner));
        face.hidden = badge.hidden = !partner;
        if (icon) icon.style.display = partner ? 'none' : '';
        face.replaceChildren();
        if (partner) {
            const initials = document.createElement('span');
            initials.textContent = initialsOf(partner.name);
            if (partner.avatar) {
                const photo = document.createElement('img');
                photo.alt = '';
                // Otherwise the browser drags a copy of the photo instead of the bubble moving.
                photo.draggable = false;
                photo.referrerPolicy = 'no-referrer';
                photo.addEventListener('error', () => photo.replaceWith(initials), { once: true });
                photo.src = partner.avatar;
                face.append(photo);
            } else {
                face.append(initials);
            }
        }
        if (bubble.getAttribute('aria-expanded') !== 'true') bubble.setAttribute('aria-label', bubbleLabel());
    };

    // The panel grows from the corner when opened and shrinks back when minimized. A reversal mid-way starts from
    // wherever the panel is, so rapid clicks never jump.
    const PANEL_HIDDEN = { opacity: 0, transform: 'translateY(14px) scale(.94)' };
    let panelAnimation = null;
    const isOpen = () => bubble.getAttribute('aria-expanded') === 'true';
    const animatePanel = opening => {
        const from = panelAnimation
            ? { opacity: getComputedStyle(panel).opacity, transform: getComputedStyle(panel).transform }
            : (opening ? PANEL_HIDDEN : { opacity: 1, transform: 'none' });
        if (panelAnimation) { panelAnimation.onfinish = null; panelAnimation.cancel(); panelAnimation = null; }
        if (opening) panel.hidden = false;
        aimAtBubble();
        if (reducedMotion.matches || !panel.animate) { panel.hidden = !opening; return; }
        panelAnimation = panel.animate([from, opening ? { opacity: 1, transform: 'none' } : PANEL_HIDDEN], {
            duration: opening ? 300 : 260, easing: 'cubic-bezier(.22, 1, .36, 1)', fill: 'forwards',
        });
        panelAnimation.onfinish = () => {
            if (!opening) panel.hidden = true;
            panelAnimation.cancel();
            panelAnimation = null;
        };
    };
    // The panel can be dragged by its header and is magnetized to the nearest screen corner on release; it can be
    // resized from the inner corner within set limits. Phones keep the full-width panel without either.
    const PANEL_MIN = { width: 320, height: 420 };
    const PANEL_MAX = { width: 560, height: 760 };
    const PANEL_DEFAULT = { width: 400, height: 600 };
    const CORNERS = ['br', 'bl', 'tr', 'tl'];
    const handle = find('[data-chat-drag-handle]');
    const mover = find('[data-chat-move]');
    const resizer = find('[data-chat-resize]');
    const smallScreen = window.matchMedia('(max-width: 600px)');
    let panelGlide = null, panelGesture = null;
    const EDGE = 24;
    const layout = () => {
        const saved = state.panel && typeof state.panel === 'object' ? state.panel : {};
        return {
            corner: CORNERS.includes(saved.corner) ? saved.corner : 'br',
            width: Number.isFinite(saved.width) ? saved.width : PANEL_DEFAULT.width,
            height: Number.isFinite(saved.height) ? saved.height : PANEL_DEFAULT.height,
        };
    };
    const clampSize = (width, height) => {
        const view = bounds();
        const maxWidth = Math.min(PANEL_MAX.width, view.width - EDGE * 2), maxHeight = Math.min(PANEL_MAX.height, view.height - EDGE * 2);
        return {
            width: Math.round(Math.max(Math.min(PANEL_MIN.width, maxWidth), Math.min(maxWidth, width))),
            height: Math.round(Math.max(Math.min(PANEL_MIN.height, maxHeight), Math.min(maxHeight, height))),
        };
    };
    const cornerPosition = (corner, size) => {
        const view = bounds();
        return { left: corner[1] === 'l' ? EDGE : view.width - size.width - EDGE, top: corner[0] === 't' ? EDGE : view.height - size.height - EDGE };
    };
    // The corner the bubble is nearest to, and pointing the panel's open/close animation at the bubble.
    const bubbleCorner = () => {
        const rect = bubble.getBoundingClientRect(), view = bounds();
        return `${rect.top + rect.height / 2 < view.height / 2 ? 't' : 'b'}${rect.left + rect.width / 2 < view.width / 2 ? 'l' : 'r'}`;
    };
    function aimAtBubble() {
        if (bubble.hidden) return;
        const from = panel.getBoundingClientRect(), to = bubble.getBoundingClientRect();
        panel.style.transformOrigin = `${Math.round(to.left + to.width / 2 - from.left)}px ${Math.round(to.top + to.height / 2 - from.top)}px`;
    }
    // When the panel moves to a corner the bubble goes with it, so minimizing returns it to where the chat was.
    const moveBubbleTo = corner => {
        const size = bubble.offsetWidth || 60, view = bounds();
        place(corner[1] === 'l' ? EDGE : view.width - size - EDGE, corner[0] === 't' ? EDGE : view.height - size - EDGE);
        save();
    };
    const stopPanelGlide = () => {
        if (!panelGlide) return;
        panelGlide.onfinish = null; panelGlide.cancel(); panelGlide = null;
    };
    const applyPanel = () => {
        const props = ['left', 'top', 'right', 'bottom', 'width', 'height', 'transformOrigin'];
        if (smallScreen.matches) { props.forEach(prop => { panel.style[prop] = ''; }); delete panel.dataset.corner; return; }
        const { corner, width, height } = layout();
        const size = clampSize(width, height);
        const position = cornerPosition(corner, size);
        Object.assign(panel.style, {
            left: `${position.left}px`, top: `${position.top}px`, right: 'auto', bottom: 'auto',
            width: `${size.width}px`, height: `${size.height}px`,
            transformOrigin: `${corner[0] === 't' ? 'top' : 'bottom'} ${corner[1] === 'l' ? 'left' : 'right'}`,
        });
        panel.dataset.corner = corner;
    };
    // Glides from where the panel is now to its (new) corner.
    const snapPanel = corner => {
        const start = panel.getBoundingClientRect();
        stopPanelGlide();
        state.panel = { ...layout(), corner }; save();
        applyPanel();
        moveBubbleTo(corner);
        if (reducedMotion.matches || !panel.animate) return;
        panelGlide = panel.animate([
            { left: `${start.left}px`, top: `${start.top}px` },
            { left: panel.style.left, top: panel.style.top },
        ], { duration: 340, easing: 'cubic-bezier(.22, 1, .36, 1)' });
        panelGlide.onfinish = () => { panelGlide = null; };
    };
    const endPanelGesture = event => {
        if (!panelGesture || event.pointerId !== panelGesture.id) return;
        const gesture = panelGesture; panelGesture = null;
        root.classList.remove('is-adjusting'); panel.classList.remove('is-moving', 'is-resizing');
        const owner = gesture.type === 'move' ? handle : resizer;
        if (owner.hasPointerCapture(event.pointerId)) owner.releasePointerCapture(event.pointerId);
        if (gesture.type === 'move' && gesture.moved) {
            const rect = panel.getBoundingClientRect(), view = bounds();
            const corner = `${rect.top + rect.height / 2 < view.height / 2 ? 't' : 'b'}${rect.left + rect.width / 2 < view.width / 2 ? 'l' : 'r'}`;
            snapPanel(corner);
        }
        if (gesture.type === 'resize') save();
    };
    handle.addEventListener('pointerdown', event => {
        if (smallScreen.matches || !event.isPrimary || event.button !== 0 || event.target.closest('a, button:not([data-chat-move])')) return;
        const rect = panel.getBoundingClientRect();
        stopPanelGlide();
        panelGesture = { type: 'move', id: event.pointerId, x: event.clientX, y: event.clientY, left: rect.left, top: rect.top, moved: false };
        handle.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    handle.addEventListener('pointermove', event => {
        if (panelGesture?.type !== 'move' || event.pointerId !== panelGesture.id) return;
        const dx = event.clientX - panelGesture.x, dy = event.clientY - panelGesture.y;
        if (!panelGesture.moved && Math.hypot(dx, dy) < 4) return;
        panelGesture.moved = true;
        root.classList.add('is-adjusting'); panel.classList.add('is-moving');
        const view = bounds(), rect = panel.getBoundingClientRect();
        panel.style.left = `${Math.max(0, Math.min(view.width - rect.width, panelGesture.left + dx))}px`;
        panel.style.top = `${Math.max(0, Math.min(view.height - rect.height, panelGesture.top + dy))}px`;
    });
    resizer.addEventListener('pointerdown', event => {
        if (smallScreen.matches || !event.isPrimary || event.button !== 0) return;
        const rect = panel.getBoundingClientRect();
        stopPanelGlide();
        panelGesture = { type: 'resize', id: event.pointerId, x: event.clientX, y: event.clientY, width: rect.width, height: rect.height };
        resizer.setPointerCapture(event.pointerId);
        root.classList.add('is-adjusting'); panel.classList.add('is-resizing');
        event.preventDefault();
    });
    resizer.addEventListener('pointermove', event => {
        if (panelGesture?.type !== 'resize' || event.pointerId !== panelGesture.id) return;
        const { corner } = layout();
        // The grip sits on the corner away from the anchored one, so moving away from the anchor grows the panel.
        const growX = corner[1] === 'r' ? -1 : 1, growY = corner[0] === 'b' ? -1 : 1;
        const size = clampSize(panelGesture.width + (event.clientX - panelGesture.x) * growX, panelGesture.height + (event.clientY - panelGesture.y) * growY);
        state.panel = { corner, ...size };
        applyPanel();
    });
    [handle, resizer].forEach(element => {
        element.addEventListener('pointerup', endPanelGesture);
        element.addEventListener('pointercancel', endPanelGesture);
        element.addEventListener('lostpointercapture', endPanelGesture);
    });
    mover.addEventListener('keydown', event => {
        const { corner } = layout();
        const next = { ArrowLeft: `${corner[0]}l`, ArrowRight: `${corner[0]}r`, ArrowUp: `t${corner[1]}`, ArrowDown: `b${corner[1]}` }[event.key];
        if (!next || smallScreen.matches) return;
        event.preventDefault();
        if (next !== corner) { snapPanel(next); announce(`Chat moved to the ${next[0] === 't' ? 'top' : 'bottom'} ${next[1] === 'l' ? 'left' : 'right'}.`); }
    });
    resizer.addEventListener('keydown', event => {
        if (smallScreen.matches) return;
        const current = layout();
        const change = { ArrowRight: [24, 0], ArrowLeft: [-24, 0], ArrowUp: [0, 24], ArrowDown: [0, -24] }[event.key];
        if (!change && event.key !== 'Home') return;
        event.preventDefault();
        const size = event.key === 'Home' ? clampSize(PANEL_DEFAULT.width, PANEL_DEFAULT.height) : clampSize(current.width + change[0], current.height + change[1]);
        state.panel = { corner: current.corner, ...size }; save();
        applyPanel();
    });
    window.addEventListener('resize', () => { stopPanelGlide(); applyPanel(); });
    smallScreen.addEventListener('change', applyPanel);

    // While the chat is open the bubble fades away (chat-widget.css .is-tucked) and leaves the tab order.
    const tuckBubble = tucked => {
        bubble.classList.toggle('is-tucked', tucked);
        if (tucked) bubble.setAttribute('tabindex', '-1'); else bubble.removeAttribute('tabindex');
    };
    const minimize = (focus = true) => {
        const wasOpen = isOpen();
        bubble.setAttribute('aria-expanded', 'false');
        bubble.setAttribute('aria-label', bubbleLabel());
        if (wasOpen) animatePanel(false);
        tuckBubble(false);
        if (focus && wasOpen && !bubble.hidden) bubble.focus({ preventScroll: true });
    };
    const open = url => {
        if (state.dismissed) { const { width, height } = bounds(); place(width - 76, height - 76); }
        state.dismissed = false; save();
        bubble.hidden = false; notice.hidden = true;
        if (!frame.hasAttribute('src') || url) {
            let remembered = null;
            try { remembered = state.chatPath ? new URL(state.chatPath, location.href) : null; } catch { remembered = null; }
            if (remembered && (remembered.origin !== chatUrl.origin || remembered.pathname !== chatUrl.pathname)) remembered = null;
            const destination = new URL(url || remembered?.href || chatUrl.href);
            destination.searchParams.set('compact', '1');
            frame.src = destination.href;
        }
        const wasOpen = isOpen();
        bubble.setAttribute('aria-expanded', 'true');
        bubble.setAttribute('aria-label', 'Minimize messages');
        if (!wasOpen) {
            state.panel = { ...layout(), corner: bubbleCorner() }; save();
            applyPanel();
            animatePanel(true);
        }
        tuckBubble(true);
        find('[data-chat-minimize]').focus({ preventScroll: true });
        frame.contentWindow?.postMessage({ type: 'zaylo-chat-open' }, chatUrl.origin);
    };
    const dismiss = () => {
        stopReturn();
        minimize(false); tuckBubble(false); bubble.hidden = true;
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
    // The bubble moves with our own pointer drag; never start the browser's native drag of its contents.
    bubble.addEventListener('dragstart', event => event.preventDefault());
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
        isOpen() ? minimize() : open();
    });
    bubble.addEventListener('keydown', event => {
        if (event.key === 'Delete' || event.key === 'Backspace') { event.preventDefault(); dismiss(); }
    });
    find('[data-chat-minimize]').addEventListener('click', () => minimize());
    find('[data-chat-dismiss]').addEventListener('click', dismiss);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !event.isComposing && isOpen()) minimize();
    });
    // On the full Messages page itself, conversation links open in the page, not in the bubble.
    const onMessagesPage = location.origin === chatUrl.origin && location.pathname === chatUrl.pathname;
    // Keep ordinary links as a working fallback and preserve Ctrl/Cmd-click for a full page.
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (onMessagesPage || !link || root.contains(link) || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== chatUrl.origin || url.pathname !== chatUrl.pathname || url.searchParams.has('compact')) return;
        event.preventDefault();
        // A double click goes to the full Messages page, like the expand icon, keeping the open conversation.
        if (event.detail >= 2) {
            location.assign(url.searchParams.has('conversation') ? url.href : expand.href);
            return;
        }
        open(url.searchParams.has('conversation') ? url.href : undefined);
    });
    window.addEventListener('message', event => {
        if (event.origin !== chatUrl.origin || event.source !== frame.contentWindow) return;
        if (event.data?.type === 'zaylo-chat-minimize') minimize();
        if (event.data?.type === 'zaylo-chat-partner') {
            state.partner = cleanPartner(event.data.partner);
            save();
            showPartner();
        }
    });
    frame.addEventListener('load', () => {
        try {
            const current = new URL(frame.contentWindow.location.href);
            if (current.origin === chatUrl.origin && current.pathname === chatUrl.pathname) {
                state.chatPath = current.pathname + current.search; save();
                current.searchParams.delete('compact'); expand.href = current.href;
            }
        } catch { /* The full Messages link remains available if the session expires. */ }
    });
    // Typing dots: while the chat is closed, the bubble checks every few seconds whether anyone is typing to this
    // account and shows animated dots beside itself, like Messenger's chat heads. The open chat shows its own.
    const typingUrl = root.dataset.typingUrl;
    const TYPING_POLL_MS = 3000;
    const typingDots = document.createElement('span');
    typingDots.className = 'chat-widget-typing';
    typingDots.setAttribute('aria-hidden', 'true');
    typingDots.hidden = true;
    typingDots.append(...[0, 1, 2].map(() => document.createElement('i')));
    bubble.append(typingDots);
    const typingStatus = document.createElement('span');
    typingStatus.className = 'chat-widget-sr';
    typingStatus.setAttribute('role', 'status');
    root.append(typingStatus);
    let typingNames = [], typingTimer = null, typingBusy = false;
    const typingLabel = names => (names.length === 1 ? `${names[0]} is typing…` : `${names.length} people are typing…`);
    const showTyping = names => {
        const changed = names.join('\n') !== typingNames.join('\n');
        typingNames = names;
        typingDots.hidden = names.length === 0;
        bubble.classList.toggle('is-typing', names.length > 0);
        // The dots sit on the side of the bubble that faces the middle of the screen.
        const rect = bubble.getBoundingClientRect();
        typingDots.classList.toggle('is-right', rect.left + rect.width / 2 < bounds().width / 2);
        if (!isOpen()) bubble.setAttribute('aria-label', names.length ? `${bubbleLabel()}. ${typingLabel(names)}` : bubbleLabel());
        // Screen readers hear it once when it starts, not on every check.
        if (changed) typingStatus.textContent = names.length ? typingLabel(names) : '';
    };
    const stopTyping = () => { clearInterval(typingTimer); typingTimer = null; };
    const checkTyping = async () => {
        if (bubble.hidden || isOpen()) { if (typingNames.length) showTyping([]); return; }
        if (typingBusy || document.visibilityState !== 'visible') return;
        typingBusy = true;
        try {
            const response = await fetch(typingUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: AbortSignal.timeout(8000),
            });
            // Signed out or no longer allowed to chat: stop asking.
            if ([401, 403, 419].includes(response.status)) { stopTyping(); showTyping([]); return; }
            if (!response.ok) return;
            const data = await response.json();
            const names = (Array.isArray(data?.typing) ? data.typing : []).map(item => String(item?.name || 'Someone').trim().slice(0, 80));
            showTyping(names);
        } catch { /* Offline or slow: the next check tries again. */ } finally { typingBusy = false; }
    };
    const startTyping = () => {
        if (typingTimer || !typingUrl) return;
        typingTimer = setInterval(checkTyping, TYPING_POLL_MS);
        checkTyping();
    };
    document.addEventListener('visibilitychange', () => (document.visibilityState === 'visible' ? startTyping() : stopTyping()));

    window.addEventListener('resize', () => { stopReturn(); if (Number.isFinite(state.x) && Number.isFinite(state.y)) place(state.x, state.y); });
    reducedMotion.addEventListener('change', () => { if (reducedMotion.matches) stopReturn(); });
    state.partner = cleanPartner(state.partner);
    showPartner();
    root.hidden = false; bubble.hidden = Boolean(state.dismissed);
    if (Number.isFinite(state.x) && Number.isFinite(state.y)) place(state.x, state.y);
    startTyping();
})();
