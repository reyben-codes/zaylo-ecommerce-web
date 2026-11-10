document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('checkout-vouchers-dialog');
    const trigger = document.querySelector('[data-vouchers-open]');
    const vouchers = document.querySelector('[data-checkout-vouchers]');
    if (dialog && trigger && vouchers && typeof dialog.showModal === 'function') {
        dialog.querySelector('[data-vouchers-dialog-content]').append(vouchers);
        trigger.hidden = false;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let animation;
        let previousOverflow;
        const animate = (opening) => {
            const from = getComputedStyle(dialog).opacity;
            animation?.cancel();
            animation = dialog.animate(
                [{ opacity: from }, { opacity: opening ? 1 : 0 }],
                { duration: reducedMotion.matches ? 0 : 300, easing: 'ease-out', fill: 'forwards' }
            );
            const current = animation;
            current.finished.then(() => {
                if (animation !== current) return;
                if (!opening) dialog.close();
                current.cancel();
            }).catch(() => {});
        };
        trigger.addEventListener('click', () => {
            if (!dialog.open) {
                previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                dialog.showModal();
                dialog.style.opacity = '0';
                animate(true);
                dialog.style.removeProperty('opacity');
            } else {
                animate(true);
            }
        });
        dialog.querySelector('[data-vouchers-close]').addEventListener('click', () => animate(false));
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            animate(false);
        });
        dialog.addEventListener('click', (event) => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) {
                animate(false);
            }
        });
        dialog.addEventListener('close', () => {
            animation?.cancel();
            document.body.style.overflow = previousOverflow;
            trigger.focus({ preventScroll: true });
        });
    }
    ['payment_method', 'courier_choice', 'logistics_provider_id'].forEach(name => {
        document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
            radio.addEventListener('change', () => {
                radio.closest('fieldset').querySelectorAll('.payment-method-option').forEach(option => {
                    option.classList.toggle('selected', option.querySelector('input').checked);
                });
            });
        });
    });
});
