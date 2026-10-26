/**
 * Handles return method selection (Pickup at Home vs Drop-off at Hub)
 * and courier truck selection on the return/dispute filing page.
 */
(function () {
    const methodRadios = document.querySelectorAll('input[name="return_method"]');
    const pickupSection = document.querySelector('[data-return-pickup-section]');
    const dropoffSection = document.querySelector('[data-return-dropoff-section]');
    const pickupDateInput = document.querySelector('input[name="pickup_date"]');

    if (!methodRadios.length || !pickupSection || !dropoffSection) return;

    function updateMethod() {
        const selected = document.querySelector('input[name="return_method"]:checked')?.value || 'pickup';
        if (selected === 'pickup') {
            pickupSection.hidden = false;
            dropoffSection.hidden = true;
            if (pickupDateInput) pickupDateInput.required = true;
        } else {
            pickupSection.hidden = true;
            dropoffSection.hidden = false;
            if (pickupDateInput) pickupDateInput.required = false;
        }
    }

    methodRadios.forEach((radio) => {
        radio.addEventListener('change', updateMethod);
    });

    updateMethod();
})();
