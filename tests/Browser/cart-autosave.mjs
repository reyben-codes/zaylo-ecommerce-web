import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

class Element {
    listeners = {};
    dataset = {};
    hidden = false;
    disabled = false;
    checked = true;
    textContent = '';
    classList = { toggle() {} };
    addEventListener(name, listener) { (this.listeners[name] ??= []).push(listener); }
    emit(name, extra = {}) { for (const listener of this.listeners[name] || []) listener({ preventDefault() {}, ...extra }); }
    setAttribute(name, value) { this[name] = value; }
}
const row = new Element();
Object.assign(row.dataset, { cartItemId: '1', cartQuantity: '1', cartPrice: '100', cartUnitPrice: '100' });
const total = new Element(), price = new Element(), error = new Element();
error.id = 'error-1';
row.querySelector = selector => ({ '.cart-line-total': total, '[data-cart-unit-price-label]': price, '[data-cart-quantity-error]': error })[selector];
const input = new Element();
Object.assign(input, { value: '1', defaultValue: '1', max: '10', form: new Element(), closest: () => row });
Object.defineProperties(input, {
    valueAsNumber: { get() { return this.value === '' ? NaN : Number(this.value); } },
    validity: { get() { return { valid: Number.isInteger(this.valueAsNumber) && this.valueAsNumber >= 1 && this.valueAsNumber <= Number(this.max) }; } },
});
const box = new Element();
box.closest = () => row;
const ids = Object.fromEntries(['cart-select-all', 'cart-selected-count', 'cart-selected-subtotal', 'cart-checkout-button', 'cart-remove-selected', 'cart-auto-save-form', 'cart-selection-form'].map(id => [id, new Element()]));
ids['cart-auto-save-form'].action = '/buyer/cart/items';
ids['cart-auto-save-form'].querySelector = () => ({ value: 'csrf' });
const status = new Element(), retry = new Element(), badge = new Element(), link = new Element();
link.querySelector = () => badge;
let timerId = 0;
const timers = new Map(), requests = [];
const fetch = (url, options) => new Promise((resolve, reject) => requests.push({ resolve, reject, options, body: JSON.parse(options.body) }));
const window = new Element();
Object.assign(window, { fetch, setTimeout(callback) { timers.set(++timerId, callback); return timerId; }, clearTimeout(id) { timers.delete(id); } });
const document = {
    getElementById: id => ids[id],
    querySelector: selector => ({ '[data-cart-save-status]': status, '[data-cart-save-retry]': retry })[selector],
    querySelectorAll: selector => ({ '.cart-item-checkbox': [box], '.cart-remove-form button': [], '.cart-product .quantity-form input[name="quantity"]': [input], '[data-cart-link]': [link] })[selector] || [],
};
vm.runInNewContext(readFileSync(new URL('../../public/js/cart-autosave.js', import.meta.url), 'utf8'), { document, window, fetch, Intl, queueMicrotask });
const settle = () => new Promise(resolve => setImmediate(resolve));
const edit = value => { input.value = String(value); input.emit('input'); };
const flushTimer = async () => { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); await settle(); };
const respond = async (request, quantity) => {
    request.resolve({ ok: true, json: async () => ({ items: [{ id: 1, quantity, unit_price: 100, available_stock: 10 }], cart_quantity: quantity }) });
    await settle();
};

// Rapid edits coalesce; totals update immediately and checkout waits for persistence.
edit(2); edit(3); edit(4);
assert.equal(timers.size, 1);
assert.equal(ids['cart-checkout-button'].disabled, true);
assert.match(total.textContent, /400/);
await flushTimer();
assert.equal(requests.length, 1);
assert.equal(requests[0].body.quantities['1'], 4);

// A slow response cannot overwrite a newer edit or race a second request.
edit(5);
assert.equal(requests.length, 1);
await respond(requests[0], 4);
assert.equal(input.value, '5');
assert.equal(ids['cart-checkout-button'].disabled, true);
await flushTimer();
assert.equal(requests[1].body.quantities['1'], 5);
await respond(requests[1], 5);
assert.equal(ids['cart-checkout-button'].disabled, false);
assert.equal(badge.textContent, '5');

// Failed saves retain entered values and require retry, without a request loop.
edit(6); await flushTimer();
requests[2].reject(new Error('Network unavailable'));
await settle();
assert.equal(input.value, '6');
assert.equal(retry.hidden, false);
assert.equal(timers.size, 0);
assert.equal(ids['cart-checkout-button'].disabled, true);
retry.emit('click'); await flushTimer();
await respond(requests[3], 6);
assert.equal(retry.hidden, true);
assert.equal(ids['cart-checkout-button'].disabled, false);

// Blank and out-of-stock values do not reach the server or allow checkout.
edit(''); await flushTimer();
edit(11); await flushTimer();
assert.equal(requests.length, 4);
assert.equal(ids['cart-checkout-button'].disabled, true);
edit(6);
assert.equal(ids['cart-checkout-button'].disabled, false);
box.checked = false; box.emit('change');
assert.equal(ids['cart-checkout-button'].disabled, true);
console.log('Cart autosave: debounce, serialization, totals, retry, validation and checkout checks passed.');
