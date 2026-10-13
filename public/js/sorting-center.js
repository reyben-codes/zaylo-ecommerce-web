document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-sc-shell]');
    const menuButton = document.querySelector('[data-sc-menu]');

    if (shell && menuButton) {
        menuButton.addEventListener('click', () => {
            const isOpen = shell.classList.toggle('is-menu-open');
            menuButton.setAttribute('aria-expanded', String(isOpen));
        });

        document.addEventListener('click', (event) => {
            if (!shell.classList.contains('is-menu-open')) return;
            if (event.target instanceof Element && (event.target.closest('[data-sc-menu]') || event.target.closest('.sc-sidebar'))) return;
            shell.classList.remove('is-menu-open');
            menuButton.setAttribute('aria-expanded', 'false');
        });
    }

    document.querySelectorAll('[data-demo-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-demo-row]');
            const status = row?.querySelector('[data-demo-status]');
            if (!status) return;

            const nextStatus = button.dataset.demoAction;
            status.textContent = nextStatus;
            status.className = `sc-badge ${['confirmed', 'approved', 'verified'].includes(nextStatus) ? 'sc-badge-success' : 'sc-badge-warning'}`;
            button.closest('[data-demo-actions]')?.remove();

            const notice = document.querySelector('[data-demo-feedback]');
            if (notice) notice.hidden = false;
        });
    });

    const chatForm = document.querySelector('[data-demo-chat-form]');
    if (chatForm instanceof HTMLFormElement) {
        chatForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const input = chatForm.querySelector('input[name="message"]');
            const thread = document.querySelector('[data-demo-chat-thread]');
            if (!(input instanceof HTMLInputElement) || !thread || !input.value.trim()) return;

            const bubble = document.createElement('div');
            bubble.className = 'sc-chat-bubble is-outgoing';
            bubble.textContent = input.value.trim();
            thread.append(bubble);
            input.value = '';
            thread.scrollTop = thread.scrollHeight;
        });
    }
});
