(() => {
    const root = document.querySelector('[data-messaging]');
    if (!root) return;
    const find = selector => root.querySelector(selector);
    const list = find('[data-message-list]');
    const form = find('[data-message-form]');
    const input = form?.querySelector('textarea');
    const sendButton = form?.querySelector('button');
    const olderButton = find('[data-load-older]');
    const feedback = find('[data-chat-feedback]');
    const inboxScroll = find('[data-inbox-scroll]');
    const inboxStatus = find('[data-inbox-status]');
    const conversations = new Map();
    const ids = new Set();
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const visible = () => !document.hidden && (!window.frameElement || window.frameElement.getClientRects().length > 0);
    let newest = 0, oldest = 0, readThrough = 0, nextPage = 1;
    let inboxLoading = false, hasMoreConversations = true;
    let inboxRetryAt = 0;
    let initialized = false, refreshing = false, sending = false, reading = false, stopped = false;
    let retry = null;
    const showError = (text, source = 'action') => { feedback.textContent = text; feedback.dataset.source = source; feedback.hidden = false; };
    const clearError = () => { feedback.hidden = true; };
    const request = async (url, data) => {
        const response = await fetch(url, {
            method: data ? 'POST' : 'GET', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(data ? { 'Content-Type': 'application/json' } : {}) },
            body: data ? JSON.stringify(data) : undefined,
            signal: AbortSignal.timeout(15000),
        });
        if ([401, 403, 419].includes(response.status)) {
            stopped = true;
            throw new Error('Your session is no longer available. Refresh the page and sign in again.');
        }
        if (!response.ok) {
            const json = await response.json().catch(() => ({}));
            throw new Error(Object.values(json.errors ?? {}).flat()[0] ?? (response.status === 429 ? 'Please wait a moment before trying again.' : json.message || 'Messages could not be loaded. Please try again.'));
        }
        return response.status === 204 ? null : response.json();
    };
    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const renderInbox = () => {
        const container = find('[data-inbox-list]');
        const fragment = document.createDocumentFragment();
        let unread = 0;
        const chats = [...conversations.values()].sort((a, b) => (Date.parse(b.updated_at) || 0) - (Date.parse(a.updated_at) || 0) || b.id - a.id);
        const focusedId = document.activeElement?.closest('[data-chat-id]')?.dataset.chatId;
        for (const chat of chats) {
            const link = element('a', 'messaging-conversation');
            link.dataset.chatId = chat.id;
            link.href = chat.url;
            if (root.dataset.compact) {
                const url = new URL(link.href);
                url.searchParams.set('compact', '1');
                link.href = url.href;
            }
            if (String(chat.id) === root.dataset.conversation) { link.classList.add('is-active'); link.setAttribute('aria-current', 'page'); }
            const avatar = element('span', 'messaging-avatar', [...chat.name][0] ?? '?');
            avatar.setAttribute('aria-hidden', 'true');
            const copy = element('div', 'messaging-conversation-copy');
            copy.append(element('strong', '', chat.name), element('p', '', chat.preview));
            link.append(avatar, copy);
            if (chat.unread) {
                const badge = element('span', 'messaging-unread', chat.unread > 99 ? '99+' : String(chat.unread));
                badge.setAttribute('aria-label', `${chat.unread} unread messages`);
                link.append(badge); unread += chat.unread;
            }
            fragment.append(link);
        }
        if (!chats.length) fragment.append(element('p', 'messaging-empty', 'No conversations yet.'));
        const scroll = inboxScroll.scrollTop;
        container.replaceChildren(fragment);
        if (focusedId) container.querySelector(`[data-chat-id="${focusedId}"]`)?.focus({ preventScroll: true });
        inboxScroll.scrollTop = scroll;
        find('[data-unread-count]').textContent = unread ? `${unread} unread` : '';
    };
    const nearInboxBottom = () => inboxScroll.scrollHeight - inboxScroll.scrollTop - inboxScroll.clientHeight < 100;
    const inbox = async (loadMore = false) => {
        if (inboxLoading || stopped || (loadMore && (!hasMoreConversations || Date.now() < inboxRetryAt))) return;
        inboxLoading = true;
        inboxScroll.setAttribute('aria-busy', 'true');
        if (loadMore) { inboxStatus.hidden = false; inboxStatus.textContent = 'Loading more conversations…'; }
        let succeeded = false;
        try {
            const page = loadMore ? nextPage : 1;
            const data = await request(`${root.dataset.inboxUrl}?page=${page}`);
            inboxRetryAt = 0;
            for (const chat of data.conversations) conversations.set(chat.id, chat);
            if (loadMore || nextPage === 1) nextPage = data.page + 1;
            hasMoreConversations = nextPage <= data.last_page;
            renderInbox();
            inboxStatus.hidden = !conversations.size;
            inboxStatus.textContent = hasMoreConversations ? 'Scroll for more conversations' : 'You’re all caught up';
            succeeded = true;
        } catch (error) {
            // Layout changes can fire scroll events too; avoid hammering a failing endpoint.
            inboxRetryAt = Date.now() + 5000;
            inboxStatus.hidden = false;
            inboxStatus.textContent = 'Unable to load conversations. Scroll to retry.';
            throw error;
        } finally {
            inboxLoading = false;
            inboxScroll.setAttribute('aria-busy', 'false');
        }
        // Fill short lists automatically, including when a larger viewport has no scrollbar yet.
        if (succeeded && hasMoreConversations && nearInboxBottom()) await inbox(true);
    };
    const atBottom = () => list && list.scrollHeight - list.scrollTop - list.clientHeight < 70;
    const markRead = async () => {
        if (reading || !list || !visible() || !document.hasFocus() || !atBottom() || !newest || newest <= readThrough) return;
        const through = newest;
        reading = true;
        try {
            await request(root.dataset.readUrl, { through });
            readThrough = through;
        } finally { reading = false; }
    };
    const addMessages = (messages, prepend = false) => {
        const follow = !initialized || atBottom();
        const previousHeight = list.scrollHeight;
        const fragment = document.createDocumentFragment();
        for (const message of messages) {
            if (ids.has(message.id)) continue;
            ids.add(message.id);
            newest = Math.max(newest, message.id);
            oldest = oldest ? Math.min(oldest, message.id) : message.id;
            const row = element('article', `messaging-message${message.mine ? ' is-mine' : ''}`);
            row.dataset.messageId = message.id;
            row.setAttribute('aria-label', message.mine ? 'You' : 'Reply');
            const bubble = element('div', 'messaging-bubble', message.body);
            const time = element('time', '', new Date(message.created_at).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }));
            time.dateTime = message.created_at;
            row.append(bubble, time); fragment.append(row);
        }
        if (ids.size) find('[data-message-empty]')?.remove();
        prepend ? list.prepend(fragment) : list.append(fragment);
        if (prepend) list.scrollTop += list.scrollHeight - previousHeight;
        else if (follow) list.scrollTop = list.scrollHeight;
        if (!ids.size) find('[data-message-empty]').textContent = 'Say hello and ask your first question.';
    };
    const refresh = async () => {
        if (refreshing || stopped || !visible()) return;
        refreshing = true;
        try {
            if (list) {
                const data = await request(root.dataset.messagesUrl + (initialized ? `?after=${newest}` : ''));
                addMessages(data.messages);
                if (!initialized) olderButton.hidden = !data.has_older;
                initialized = true;
                if (!sending) { input.disabled = false; sendButton.disabled = false; }
                await markRead();
            }
            await inbox();
            if (feedback.dataset.source === 'refresh') clearError();
        } catch (error) { showError(error.message, 'refresh'); }
        finally { refreshing = false; }
    };
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending || stopped) return;
        const body = input.value.trim();
        if (!body) { showError('Write a message first.'); input.focus(); return; }
        if (!retry || retry.body !== body) retry = { body, client_id: crypto.randomUUID() };
        sending = true; input.disabled = true; sendButton.disabled = true;
        clearError();
        try {
            await request(root.dataset.sendUrl, retry);
            retry = null; input.value = '';
            list.scrollTop = list.scrollHeight;
            // Fetch from the previous cursor so simultaneous incoming messages are not skipped.
            await refresh();
        } catch (error) { showError(`${error.message} Your draft has been kept.`); }
        finally {
            sending = false; input.disabled = stopped; sendButton.disabled = stopped;
            if (!stopped) input.focus();
        }
    });
    olderButton?.addEventListener('click', async () => {
        olderButton.disabled = true;
        try {
            const data = await request(`${root.dataset.messagesUrl}?before=${oldest}`);
            addMessages(data.messages, true); olderButton.hidden = !data.has_older;
        } catch (error) { showError(error.message); }
        finally { olderButton.disabled = false; }
    });
    inboxScroll.addEventListener('scroll', () => {
        if (nearInboxBottom()) inbox(true).catch(error => showError(error.message, 'refresh'));
    }, { passive: true });
    list?.addEventListener('scroll', () => markRead().catch(() => {}), { passive: true });
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('focus', refresh);
    window.addEventListener('message', event => {
        if (event.origin === location.origin && event.source === window.parent && event.data?.type === 'zaylo-chat-open') refresh();
    });
    if (root.dataset.compact) document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !event.isComposing) window.parent.postMessage({ type: 'zaylo-chat-minimize' }, location.origin);
    });
    let timer = setInterval(refresh, 5000);
    window.addEventListener('pagehide', () => clearInterval(timer));
    window.addEventListener('pageshow', event => {
        if (event.persisted) { timer = setInterval(refresh, 5000); refresh(); }
    });
    refresh();
})();
