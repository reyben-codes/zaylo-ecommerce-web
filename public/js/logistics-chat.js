// Logistics and support chat: send without reloading, and show new messages every few seconds. A message can carry one
// photo or video.
(() => {
    const list = document.querySelector('[data-logistics-chat]');
    const form = document.querySelector('[data-logistics-chat-form]');
    if (!list || !form) return;

    const textarea = form.querySelector('textarea');
    const button = form.querySelector('button[type="submit"]');
    const error = document.querySelector('[data-chat-error]');
    const fileInput = form.querySelector('[data-chat-attach]');
    const fileName = document.querySelector('[data-chat-attach-name]');
    const fileIcon = fileName?.querySelector('[data-chat-attach-icon]');
    // The server's limits (App\Services\ChatMedia).
    const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    const MAX_VIDEO_BYTES = 25 * 1024 * 1024;
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
        if (message.video) {
            const video = document.createElement('video');
            video.className = 'logistics-chat__video';
            video.src = message.video;
            video.controls = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.setAttribute('aria-label', `Video from ${message.mine ? 'you' : message.sender}`);
            video.addEventListener('loadedmetadata', () => { if (list.scrollHeight - list.scrollTop - list.clientHeight < 400) scrollToEnd(); }, { once: true });
            item.append(video);
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

    const chosenFile = () => fileInput?.files?.[0] ?? null;
    const isVideo = (file) => file.type.startsWith('video/');
    const clearFile = () => {
        if (!fileInput) return;
        fileInput.value = '';
        fileInput.name = 'photo';
        fileName.hidden = true;
    };

    fileInput?.addEventListener('change', () => {
        const file = chosenFile();
        if (!file) {
            clearFile();
            return;
        }
        const video = isVideo(file);
        if (file.size > (video ? MAX_VIDEO_BYTES : MAX_PHOTO_BYTES)) {
            clearFile();
            showError(video ? 'The video must be 25 MB or smaller.' : 'The photo must be 5 MB or smaller.');
            return;
        }
        showError('');
        // The server reads a video from the "video" field and a photo from "photo".
        fileInput.name = video ? 'video' : 'photo';
        fileIcon.className = `fas ${video ? 'fa-video' : 'fa-image'}`;
        fileName.hidden = false;
        fileName.querySelector('span').textContent = file.name;
    });
    fileName?.querySelector('[data-chat-attach-clear]').addEventListener('click', () => {
        clearFile();
        fileInput.focus();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = textarea.value.trim();
        const file = chosenFile();
        if (!body && !file) return;
        button.disabled = true;
        showError('');
        try {
            // A photo or video goes as form data; text alone stays JSON.
            const payload = file ? new FormData(form) : JSON.stringify({ body });
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, ...(file ? {} : { 'Content-Type': 'application/json' }) },
                body: payload,
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                showError(data.errors?.body?.[0] || data.errors?.photo?.[0] || data.errors?.video?.[0]
                    || (response.status === 413 ? 'The file is too large to send.' : 'Your message was not sent. Try again.'));
                return;
            }
            const { message } = await response.json();
            await poll();
            render(message);
            textarea.value = '';
            textarea.style.height = '';
            clearFile();
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
