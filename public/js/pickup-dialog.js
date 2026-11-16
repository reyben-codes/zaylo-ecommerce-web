(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const states = new WeakMap();
    const stateFor = dialog => {
        if (!states.has(dialog)) states.set(dialog, { animation: null, closing: false, trigger: null });
        return states.get(dialog);
    };
    const close = dialog => {
        if (!dialog?.open) return;
        const state = stateFor(dialog);
        if (state.closing) return;
        state.closing = true;
        const style = getComputedStyle(dialog);
        const start = { opacity: style.opacity, transform: style.transform };
        state.animation?.cancel();
        const finish = () => {
            state.animation?.cancel();
            state.animation = null;
            state.closing = false;
            dialog.close();
            if (state.trigger?.isConnected) state.trigger.focus({ preventScroll: true });
        };
        if (reducedMotion.matches || !dialog.animate) return finish();
        state.animation = dialog.animate([start, { opacity: 0, transform: 'translateY(8px) scale(.98)' }],
            { duration: 160, easing: 'ease-out' });
        state.animation.onfinish = finish;
    };
    const open = (dialog, trigger) => {
        if (!dialog || dialog.open || typeof dialog.showModal !== 'function') return;
        const state = stateFor(dialog);
        state.trigger = trigger;
        state.closing = false;
        state.animation?.cancel();
        dialog.showModal();
        if (!reducedMotion.matches && dialog.animate) {
            state.animation = dialog.animate([
                { opacity: 0, transform: 'translateY(8px) scale(.98)' },
                { opacity: 1, transform: 'translateY(0) scale(1)' },
            ], { duration: 200, easing: 'ease-out' });
        }
    };
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-pickup-dialog-open]');
        if (trigger) open(document.getElementById(trigger.dataset.pickupDialogOpen), trigger);
        const closeButton = event.target.closest('[data-pickup-dialog-close]');
        if (closeButton) close(closeButton.closest('[data-pickup-dialog]'));
    });
    document.querySelectorAll('[data-pickup-dialog]').forEach(dialog => {
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(dialog); });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close(dialog);
        });
        dialog.addEventListener('close', () => {
            const state = stateFor(dialog);
            state.animation?.cancel();
            state.animation = null;
            state.closing = false;
            if (state.trigger?.isConnected) state.trigger.focus({ preventScroll: true });
        });
        if (dialog.hasAttribute('data-open-on-load')) {
            open(dialog, document.querySelector(`[data-pickup-dialog-open="${dialog.id}"]`));
            dialog.querySelector('[aria-invalid="true"], .seller-field__error')?.scrollIntoView({ block: 'nearest' });
        }
    });
})();
