// Checkout: the shipping fee depends on the chosen logistics partner's distance to the chosen address. Each partner
// option carries its fee to every saved address, so the fees and the order summary follow either choice. The server
// prices the order again when it is placed.
(() => {
    const summary = document.querySelector('[data-checkout-summary]');
    const partners = Array.from(document.querySelectorAll('input[name="logistics_provider_id"][data-shipping-rates]'));
    if (!summary || partners.length === 0) {
        return;
    }

    const money = amount => '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const subtotal = Number(summary.dataset.subtotal) || 0;
    const sellers = Number(summary.dataset.sellers) || 0;
    const standardFee = Number(summary.dataset.standardFee) || 0;
    const freeShipping = summary.dataset.freeShipping === '1';
    const ratesOf = input => {
        try {
            return JSON.parse(input.dataset.shippingRates) || {};
        } catch {
            return {};
        }
    };

    const update = () => {
        const address = document.querySelector('input[name="address_id"]:checked')?.value;
        partners.forEach(input => {
            const rate = ratesOf(input)[address];
            const label = input.closest('label')?.querySelector('[data-shipping-rate]');
            if (label) {
                label.textContent = `${money(rate ? rate.fee : standardFee)} per seller · ${rate ? rate.label : 'Standard rate'}`;
            }
        });

        const chosen = partners.find(input => input.checked);
        const rate = chosen ? ratesOf(chosen)[address] : null;
        const shipping = (rate ? rate.fee : standardFee) * sellers;
        const discount = freeShipping ? shipping : 0;
        summary.querySelector('[data-summary-shipping]').textContent = money(shipping);
        const discountRow = summary.querySelector('[data-summary-discount]');
        if (discountRow) {
            discountRow.textContent = '−' + money(discount);
        }
        summary.querySelector('[data-summary-total]').textContent = money(subtotal + shipping - discount);
    };

    document.querySelectorAll('input[name="address_id"], input[name="logistics_provider_id"]').forEach(input => {
        input.addEventListener('change', update);
    });
    update();
})();
