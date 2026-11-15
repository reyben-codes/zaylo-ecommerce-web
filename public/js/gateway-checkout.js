document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-gateway-checkout]');
    if (form) form.requestSubmit();
});
