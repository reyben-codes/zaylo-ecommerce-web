// Checkout uses the server-defined fixed ₱120 shipping fee per seller order.
// The server recalculates every amount from locked cart rows when it places an order.
(() => {
    const summary = document.querySelector('[data-checkout-summary]');
    if (!summary) return;

    const money = amount => '₱' + amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const subtotal = Number(summary.dataset.subtotal) || 0;
    const sellers = Number(summary.dataset.sellers) || 0;
    const standardFee = Number(summary.dataset.standardFee) || 0;
    const freeShipping = summary.dataset.freeShipping === '1';
    const freeShippingSellers = Number(summary.dataset.freeShippingSellers) || 0;
    const voucherDiscount = Number(summary.dataset.voucherDiscount) || 0;

    const update = () => {
        const shipping = standardFee * sellers;
        const method = document.querySelector('input[name="payment_method"]:checked')?.value;
        const discount = freeShipping ? standardFee * freeShippingSellers : 0;
        const total = subtotal + shipping - discount - voucherDiscount;

        summary.querySelector('[data-summary-shipping]').textContent = money(shipping);
        const discountRow = summary.querySelector('[data-summary-discount]');
        if (discountRow) discountRow.textContent = '−' + money(discount);
        summary.querySelector('[data-free-shipping-row]')?.toggleAttribute('hidden', discount === 0);
        summary.querySelector('[data-summary-total]').textContent = money(total);
        const totalLabel = summary.querySelector('[data-summary-total-label]');
        if (totalLabel) totalLabel.textContent = method === 'cod' ? 'Total COD Amount' : 'Total to pay';

        document.querySelectorAll('[data-gcash-amount]').forEach(amount => {
            amount.textContent = money((Number(amount.dataset.subtotal) || 0) + (amount.dataset.freeShipping === '1' ? 0 : standardFee));
        });
    };

    document.querySelectorAll('input[name="address_id"], input[name="payment_method"]')
        .forEach(input => input.addEventListener('change', update));
    update();
})();
