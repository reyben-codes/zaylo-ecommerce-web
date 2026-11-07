// Checkout: the GCash instructions and reference fields show only while GCash is the chosen payment method.
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-gcash-pay]');
    const methods = document.querySelectorAll('input[name="payment_method"]');
    if (!panel || methods.length === 0) {
        return;
    }
    const sync = () => {
        const gcash = document.querySelector('input[name="payment_method"]:checked')?.value === 'gcash';
        panel.hidden = !gcash;
        // Hidden fields are not sent, so a reference typed before switching back to COD is not kept.
        panel.querySelectorAll('input').forEach(input => {
            input.disabled = !gcash;
        });
    };
    methods.forEach(method => method.addEventListener('change', sync));
    sync();
});
