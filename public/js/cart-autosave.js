(() => {
    const boxes = [...document.querySelectorAll('.cart-item-checkbox')];
    const selectAll = document.getElementById('cart-select-all');
    if (!selectAll) return;

    const count = document.getElementById('cart-selected-count');
    const subtotal = document.getElementById('cart-selected-subtotal');
    const checkout = document.getElementById('cart-checkout-button');
    const removeSelected = document.getElementById('cart-remove-selected');
    const removeButtons = [...document.querySelectorAll('.cart-remove-form button')];
    const saveForm = document.getElementById('cart-auto-save-form');
    const status = document.querySelector('[data-cart-save-status]');
    const retry = document.querySelector('[data-cart-save-retry]');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    let timer = null;
    let saving = false;
    let failed = false;
    let failureMessage = '';
    let revision = 0;
    const states = [...document.querySelectorAll('.cart-product .quantity-form input[name="quantity"]')].map(input => {
        const row = input.closest('.cart-product');
        const error = row.querySelector('[data-cart-quantity-error]');
        if (error) input.setAttribute('aria-describedby', error.id);
        return { input, row, error, id: row.dataset.cartItemId, saved: Number(input.defaultValue), price: Number(row.dataset.cartUnitPrice) };
    });
    const valid = state => state.input.validity.valid && Number.isSafeInteger(state.input.valueAsNumber) && state.input.valueAsNumber >= 1;
    const dirty = state => state.input.valueAsNumber !== state.saved;
    const enhanced = Boolean(window.fetch && saveForm && status);

    const refresh = () => {
        for (const state of states) {
            const quantity = valid(state) ? state.input.valueAsNumber : state.saved;
            state.row.dataset.cartQuantity = String(quantity);
            state.row.dataset.cartPrice = String(quantity * state.price);
            state.row.querySelector('.cart-line-total').textContent = money.format(quantity * state.price);
        }
        const selected = boxes.filter(box => box.checked);
        const quantity = selected.reduce((sum, box) => sum + Number(box.closest('.cart-product').dataset.cartQuantity), 0);
        const total = selected.reduce((sum, box) => sum + Number(box.closest('.cart-product').dataset.cartPrice), 0);
        count.textContent = quantity > 0 ? `(${quantity})` : '';
        count.hidden = quantity < 1;
        subtotal.textContent = money.format(total);
        selectAll.checked = selected.length === boxes.length;
        selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
        const pending = enhanced && (saving || states.some(state => dirty(state) || !valid(state)));
        if (checkout) checkout.disabled = selected.length === 0 || pending;
        const busy = saving || timer !== null;
        if (removeSelected) removeSelected.disabled = selected.length === 0 || busy;
        removeButtons.forEach(button => { button.disabled = busy; });
        if (!enhanced) return;
        status.hidden = false;
        status.classList.toggle('is-error', failed || states.some(state => !valid(state)));
        status.textContent = saving || timer !== null ? 'Saving quantities…'
            : failed ? failureMessage
            : states.some(state => !valid(state)) ? 'Enter a valid quantity for each item before checkout.'
            : states.some(dirty) ? 'Changes have not been saved yet.' : 'Quantities save automatically.';
        if (retry) retry.hidden = !failed || !states.some(state => dirty(state) && valid(state));
    };
    selectAll.addEventListener('change', () => { boxes.forEach(box => { box.checked = selectAll.checked; }); refresh(); });
    boxes.forEach(box => box.addEventListener('change', refresh));
    if (!enhanced) {
        document.querySelectorAll('[data-cart-manual-save]').forEach(button => { button.hidden = false; });
        refresh();
        return;
    }

    const setError = (state, message) => {
        if (state.error) {
            state.error.textContent = message || '';
            state.error.hidden = !message;
        }
        state.input.setAttribute('aria-invalid', message ? 'true' : 'false');
    };
    const updateNav = quantity => {
        document.querySelectorAll('[data-cart-link]').forEach(link => {
            let badge = link.querySelector('[data-cart-count]');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'nav-item-count';
                badge.setAttribute('data-cart-count', '');
                badge.setAttribute('aria-hidden', 'true');
                link.append(badge);
            }
            badge.textContent = quantity > 99 ? '99+' : String(quantity);
            badge.hidden = quantity < 1;
            link.setAttribute('aria-label', `Shopping cart (${quantity} items)`);
        });
    };
    const schedule = (delay = 300) => {
        window.clearTimeout(timer);
        timer = null;
        failed = false;
        if (!saving && states.some(state => dirty(state) && valid(state))) timer = window.setTimeout(save, delay);
        refresh();
    };

    async function save() {
        window.clearTimeout(timer);
        timer = null;
        if (saving) return;
        const batch = states.filter(state => dirty(state) && valid(state)).slice(0, 200)
            .map(state => ({ state, quantity: state.input.valueAsNumber }));
        if (!batch.length) { refresh(); return; }
        saving = true;
        const requestRevision = revision;
        refresh();
        let changedWhileSaving = false;
        try {
            // Serialize requests: later edits are sent after this response, never overwritten by it.
            const response = await fetch(saveForm.action, {
                method: 'PATCH', credentials: 'same-origin', keepalive: true,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': saveForm.querySelector('[name="_token"]').value },
                body: JSON.stringify({ quantities: Object.fromEntries(batch.map(({ state, quantity }) => [state.id, quantity])) }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !Array.isArray(data.items)) {
                const errors = data.errors || {};
                const message = response.status === 419 ? 'Your session expired. Refresh the page before changing quantities.'
                    : Object.values(errors).flat()[0] || data.message || 'Could not save quantities. Check your connection and retry.';
                for (const { state, quantity } of batch) {
                    if (state.input.valueAsNumber === quantity) setError(state, errors[`quantities.${state.id}`]?.[0] || message);
                }
                throw new Error(message);
            }
            const items = new Map(data.items.map(item => [String(item.id), item]));
            if (batch.some(({ state }) => !items.has(state.id))) throw new Error('Your cart changed. Refresh the page before checkout.');
            for (const { state } of batch) {
                const item = items.get(state.id);
                state.saved = Number(item.quantity);
                state.price = Number(item.unit_price);
                state.input.max = String(Math.max(1, Number(item.available_stock)));
                state.row.dataset.cartUnitPrice = String(state.price);
                state.row.querySelector('[data-cart-unit-price-label]').textContent = `${money.format(state.price)} each`;
                setError(state, null);
            }
            failed = false;
            updateNav(Number(data.cart_quantity));
        } catch (error) {
            failed = true;
            failureMessage = error.message || 'Could not save quantities. Check your connection and retry.';
            changedWhileSaving = revision !== requestRevision;
        } finally {
            saving = false;
            // Let the stepper apply new stock limits before scheduling another save.
            queueMicrotask(() => {
                if ((!failed || changedWhileSaving) && states.some(state => dirty(state) && valid(state))) schedule(0);
                else refresh();
            });
        }
    }

    states.forEach(state => {
        state.input.addEventListener('input', () => { revision++; setError(state, null); schedule(); });
        state.input.addEventListener('change', () => { revision++; queueMicrotask(() => { setError(state, null); schedule(); }); });
        state.input.form.addEventListener('submit', event => { event.preventDefault(); schedule(0); });
    });
    retry?.addEventListener('click', () => schedule(0));
    window.addEventListener('online', () => { if (states.some(dirty)) schedule(0); });
    window.addEventListener('pageshow', () => { if (states.some(dirty) && !saving) schedule(0); else refresh(); });
    document.getElementById('cart-selection-form')?.addEventListener('submit', event => {
        if (saving || timer !== null || (event.submitter?.value === 'checkout' && states.some(state => dirty(state) || !valid(state)))) {
            event.preventDefault();
            if (!failed) schedule(0);
        }
    });
    refresh();
})();
