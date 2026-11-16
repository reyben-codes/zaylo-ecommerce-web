// No browser/server needed: exercise the actual polling script with changing API counts.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

let counts = { all: 0, cancelled: 0 };
let poll;
let initialize;
const nodes = ['all', 'cancelled'].map(key => ({ dataset: { orderCount: key }, textContent: '0', hidden: false }));
const root = { dataset: { url: '/updates', readUrl: '/read' } };
const tabs = { dataset: { orderCountsUrl: '/order-counts' }, querySelectorAll: () => nodes };
const context = {
  document: {
    hidden: false,
    querySelector: selector => selector === '[data-account-updates]' ? root : selector === '[data-order-counts-url]' ? tabs : null,
    querySelectorAll: () => [],
    addEventListener: (name, callback) => { if (name === 'DOMContentLoaded') initialize = callback; },
  },
  window: { setInterval: callback => { poll = callback; return 1; }, clearInterval() {}, addEventListener() {} },
  location: { pathname: '/buyer/orders' }, URL,
  fetch: async url => ({ ok: true, json: async () => url === '/updates' ? { counts: {}, unread: 0 } : { counts } }),
};
vm.runInNewContext(readFileSync(new URL('../../public/js/account-updates.js', import.meta.url), 'utf8'), context);
initialize();
await new Promise(resolve => setImmediate(resolve));
assert(nodes.every(node => node.hidden && node.textContent === ''));
counts = { all: 9, cancelled: 0 };
await poll();
assert.equal(nodes[0].textContent, '9');
assert.equal(nodes[0].hidden, false);
assert.equal(nodes[1].hidden, true);
counts = { all: 0, cancelled: 2 };
await poll();
assert.equal(nodes[0].hidden, true);
assert.equal(nodes[0].textContent, '');
assert.equal(nodes[1].hidden, false);
assert.equal(nodes[1].textContent, '2');
counts = { all: -1 };
await poll();
assert(nodes.every(node => node.hidden && node.textContent === ''));
console.log('Web count badges: zero, positive, cleared, missing and negative counts passed.');
