// Print button on the seller's parcel label page.
document.querySelectorAll('[data-print-label]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});
