// Checkout: the GCash instructions and reference fields show only while GCash is the chosen payment method.
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-gcash-pay]');
    const methods = document.querySelectorAll('input[name="payment_method"]');
    const submit = document.querySelector('[data-checkout-submit]');
    const syncSubmit = () => {
        const gcash = document.querySelector('input[name="payment_method"]:checked')?.value === 'gcash';
        if (submit) {
            submit.textContent = gcash ? 'Pay via GCash' : 'Place order';
            submit.classList.toggle('is-gcash', gcash);
        }
        methods.forEach(method => method.closest('.payment-method-option')?.classList.toggle('selected', method.checked));
    };
    methods.forEach(method => method.addEventListener('change', syncSubmit));
    syncSubmit();
    if (panel && methods.length > 0) {
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
    }

    // GCash reference numbers: only accept up to 13 digits
    const restrictToDigits = (input) => {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 13);
        });
        input.addEventListener('keypress', (e) => {
            if (!/[0-9]/.test(e.key) && e.key !== 'Enter' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
            }
        });
    };

    document.querySelectorAll('input[name="gcash_reference"], input[name^="gcash_references["]').forEach(restrictToDigits);
});
