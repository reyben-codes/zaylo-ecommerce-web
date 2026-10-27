// Logistics chat: send without reloading, and show new messages every few seconds.
(() => {
    const list = document.querySelector('[data-logistics-chat]');
    const form = document.querySelector('[data-logistics-chat-form]');
    if (!list || !form) return;

    const textarea = form.querySelector('textarea');
    const button = form.querySelector('button[type="submit"]');
    const error = document.querySelector('[data-chat-error]');
    // Support chats can carry one photo per message (partials/logistics-chat $photos).
    const photoInput = form.querySelector('[data-chat-photo]');
    const photoName = document.querySelector('[data-chat-photo-name]');
    const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
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
        if (message.photo) {
            const link = document.createElement('a');
            link.className = 'logistics-chat__photo';
            link.href = message.photo;
            link.target = '_blank';
            link.rel = 'noopener';
            const image = document.createElement('img');
            image.src = message.photo;
            image.alt = `Photo from ${message.mine ? 'you' : message.sender}`;
            image.loading = 'lazy';
            // Keep the newest message in view once the photo has its height.
            image.addEventListener('load', () => { if (list.scrollHeight - list.scrollTop - list.clientHeight < 400) scrollToEnd(); }, { once: true });
            link.append(image);
            item.append(link);
        }
        if (message.body) {
            const body = document.createElement('p');
            body.textContent = message.body;
            item.append(body);
        }
        const meta = document.createElement('span');
        meta.textContent = `${message.mine ? 'You' : message.sender} · ${message.time}`;
        item.append(meta);
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

    const chosenPhoto = () => photoInput?.files?.[0] ?? null;
    const clearPhoto = () => {
        if (!photoInput) return;
        photoInput.value = '';
        photoName.hidden = true;
    };

    photoInput?.addEventListener('change', () => {
        const file = chosenPhoto();
        if (file && file.size > MAX_PHOTO_BYTES) {
            clearPhoto();
            showError('The photo must be 5 MB or smaller.');
            return;
        }
        showError('');
        photoName.hidden = !file;
        photoName.querySelector('span').textContent = file ? file.name : '';
    });
    photoName?.querySelector('[data-chat-photo-clear]').addEventListener('click', () => {
        clearPhoto();
        photoInput.focus();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = textarea.value.trim();
        const photo = chosenPhoto();
        if (!body && !photo) return;
        button.disabled = true;
        showError('');
        try {
            // A photo goes as form data; text alone stays JSON.
            const payload = photo ? new FormData(form) : JSON.stringify({ body });
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, ...(photo ? {} : { 'Content-Type': 'application/json' }) },
                body: payload,
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                showError(data.errors?.body?.[0] || data.errors?.photo?.[0]
                    || (response.status === 413 ? 'The photo must be 5 MB or smaller.' : 'Your message was not sent. Try again.'));
                return;
            }
            const { message } = await response.json();
            await poll();
            render(message);
            textarea.value = '';
            textarea.style.height = '';
            clearPhoto();
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
