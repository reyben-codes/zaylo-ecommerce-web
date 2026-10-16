// Logistics chat: send without reloading, and show new messages every few seconds.
(() => {
    const list = document.querySelector('[data-logistics-chat]');
    const form = document.querySelector('[data-logistics-chat-form]');
    if (!list || !form) return;

    const textarea = form.querySelector('textarea');
    const button = form.querySelector('button[type="submit"]');
    const error = document.querySelector('[data-chat-error]');
    const token = form.querySelector('input[name="_token"]').value;
    let lastId = Number(list.dataset.lastId || 0);
    let polling = false;

    const scrollToEnd = () => { list.scrollTop = list.scrollHeight; };

    const render = (message) => {
        if (message.id <= lastId || list.querySelector(`[data-id="${message.id}"]`)) return;
        list.querySelector('[data-empty]')?.remove();
        const item = document.createElement('li');
        item.className = 'logistics-chat__message' + (message.mine ? ' is-mine' : '');
        item.dataset.id = message.id;
        const body = document.createElement('p');
        body.textContent = message.body;
        const meta = document.createElement('span');
        meta.textContent = `${message.mine ? 'You' : message.sender} · ${message.time}`;
        item.append(body, meta);
        list.append(item);
        lastId = Math.max(lastId, message.id);
    };

    const showError = (text) => {
        error.textContent = text || '';
        error.hidden = !text;
    };

    const poll = async () => {
        if (polling || document.hidden) return;
        polling = true;
        try {
            const response = await fetch(`${list.dataset.fetchUrl}?after=${lastId}`, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                const { messages } = await response.json();
                const atEnd = list.scrollHeight - list.scrollTop - list.clientHeight < 80;
                messages.forEach(render);
                if (messages.length && atEnd) scrollToEnd();
            }
        } catch (_) {
            // Offline for a moment; the next poll tries again.
        } finally {
            polling = false;
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = textarea.value.trim();
        if (!body) return;
        button.disabled = true;
        showError('');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ body }),
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                showError(data.errors?.body?.[0] || 'Your message was not sent. Try again.');
                return;
            }
            const { message } = await response.json();
            await poll();
            render(message);
            textarea.value = '';
            textarea.style.height = '';
            scrollToEnd();
        } catch (_) {
            showError('You seem to be offline. Your message was not sent.');
        } finally {
            button.disabled = false;
            textarea.focus();
        }
    });

    // Enter sends; Shift+Enter adds a new line.
    textarea.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    textarea.addEventListener('input', () => {
        textarea.style.height = '';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
    });

    scrollToEnd();
    setInterval(poll, 4000);
    document.addEventListener('visibilitychange', poll);
})();
