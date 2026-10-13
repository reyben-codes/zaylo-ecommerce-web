(() => {
    const boxes = [...document.querySelectorAll('.cart-item-checkbox')];
    const selectAll = document.getElementById('cart-select-all');
    if (!selectAll) return;

    // Selection: keep "Select all", the summary, and the selection buttons in step.
    const count = document.getElementById('cart-selected-count');
    const subtotal = document.getElementById('cart-selected-subtotal');
    const selectionButtons = [document.getElementById('cart-remove-selected'), document.getElementById('cart-checkout-button')];
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const refreshSelection = () => {
        const selected = boxes.filter((box) => box.checked);
        const quantity = selected.reduce((sum, box) => sum + Number(box.closest('.cart-product').dataset.cartQuantity), 0);
        const total = selected.reduce((sum, box) => sum + Number(box.closest('.cart-product').dataset.cartPrice), 0);
        count.textContent = `(${quantity})`;
        subtotal.textContent = money.format(total);
        selectAll.checked = selected.length === boxes.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
        selectionButtons.forEach((button) => { if (button) button.disabled = selected.length === 0; });
    };
    selectAll.addEventListener('change', () => { boxes.forEach((box) => { box.checked = selectAll.checked; }); refreshSelection(); });
    boxes.forEach((box) => box.addEventListener('change', refreshSelection));
    refreshSelection();

    // Update all: saves every quantity that differs from what's in the cart, in one request.
    const updateAll = document.getElementById('cart-update-all');
    const updateForm = document.getElementById('cart-update-all-form');
    const updateCount = updateAll?.querySelector('[data-update-count]');
    if (!updateAll || !updateForm) return;

    const quantityInputs = [...document.querySelectorAll('.cart-product .quantity-form input[name="quantity"]')];
    const changedInputs = () => quantityInputs.filter((input) => input.value !== '' && input.valueAsNumber !== Number(input.defaultValue));
    const refreshUpdateAll = () => {
        const changed = changedInputs();
        updateAll.disabled = changed.length === 0;
        updateCount.textContent = changed.length ? `(${changed.length})` : '';
        updateAll.setAttribute('aria-label', changed.length
            ? `Update all ${changed.length} changed ${changed.length === 1 ? 'item' : 'items'}`
            : 'Update all (no quantities changed yet)');
    };
    quantityInputs.forEach((input) => {
        input.addEventListener('input', refreshUpdateAll);
        input.addEventListener('change', refreshUpdateAll);
    });

    updateForm.addEventListener('submit', (event) => {
        const changed = changedInputs();
        if (!changed.length) {
            event.preventDefault();
            return;
        }
        updateForm.querySelectorAll('[data-update-quantity]').forEach((field) => field.remove());
        changed.forEach((input) => {
            const id = input.closest('.cart-product').dataset.cartItemId;
            const field = Object.assign(document.createElement('input'), { type: 'hidden', name: `quantities[${id}]`, value: input.value });
            field.dataset.updateQuantity = '';
            updateForm.append(field);
        });
        updateAll.setAttribute('aria-busy', 'true');
        updateAll.disabled = true;
    });
    // Restore the button if the page comes back from the browser's back/forward cache.
    window.addEventListener('pageshow', () => { updateAll.removeAttribute('aria-busy'); refreshUpdateAll(); });

    updateAll.hidden = false;
    refreshUpdateAll();
})();
