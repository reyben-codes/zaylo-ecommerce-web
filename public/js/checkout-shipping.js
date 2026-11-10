// Checkout uses the server-defined fixed ₱120 shipping fee per seller order.
// The server recalculates every amount from locked cart rows when it places an order.
(() => {
    const summary = document.querySelector('[data-checkout-summary]');
    if (!summary) return;

    const partners = Array.from(document.querySelectorAll('input[name="logistics_provider_id"]'));
    const money = amount => '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const subtotal = Number(summary.dataset.subtotal) || 0;
    const sellers = Number(summary.dataset.sellers) || 0;
    const standardFee = Number(summary.dataset.standardFee) || 0;
    const freeShipping = summary.dataset.freeShipping === '1';
    const voucherDiscount = Number(summary.dataset.voucherDiscount) || 0;

    const update = () => {
        const shipping = standardFee * sellers;
        const method = document.querySelector('input[name="payment_method"]:checked')?.value;
        const discount = freeShipping && method !== 'cod' ? shipping : 0;
        const total = subtotal + shipping - discount - voucherDiscount;

        partners.forEach(input => {
            const label = input.closest('label')?.querySelector('[data-shipping-rate]');
            if (label) label.textContent = `Fixed ${money(standardFee)} per seller`;
        });

        summary.querySelector('[data-summary-shipping]').textContent = money(shipping);
        const discountRow = summary.querySelector('[data-summary-discount]');
        if (discountRow) discountRow.textContent = '−' + money(discount);
        summary.querySelector('[data-free-shipping-row]')?.toggleAttribute('hidden', discount === 0);
        summary.querySelector('[data-summary-total]').textContent = money(total);
        summary.querySelector('[data-summary-total-label]').textContent = method === 'cod' ? 'Total COD Amount' : 'Total to pay';

        document.querySelectorAll('[data-gcash-amount]').forEach(amount => {
            amount.textContent = money((Number(amount.dataset.subtotal) || 0) + (freeShipping ? 0 : standardFee));
        });
    };

    document.querySelectorAll('input[name="address_id"], input[name="logistics_provider_id"], input[name="payment_method"]')
        .forEach(input => input.addEventListener('change', update));
    update();
})();
