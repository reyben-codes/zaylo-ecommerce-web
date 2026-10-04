(() => {
    const selector = document.querySelector('.product-buy-form select[name="product_variant_id"]');
    if (!selector) return;
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const update = () => {
        const option = selector.selectedOptions[0];
        if (!option) return;
        const price = Number(option.dataset.price), regular = Number(option.dataset.regular);
        document.querySelector('[data-sale-price]').textContent = money.format(price);
        const original = document.querySelector('[data-sale-regular]');
        const savings = document.querySelector('[data-sale-saving]');
        original.textContent = money.format(regular);
        original.hidden = savings.hidden = price >= regular;
        savings.textContent = `${regular > 0 ? Math.round((1 - price / regular) * 100) : 0}% off`;
        selector.form.querySelector('[name="quantity"]').max = option.dataset.stock;
    };
    selector.addEventListener('change', update);
    update();
})();
