// Exercise the shared counter updater for every web portal, including repeat initialization.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

class Badge {
  attributes = new Map();
  textContent = '';
  hidden = false;
  setAttribute(name, value) { this.attributes.set(name, value); }
}
function link(path, attribute, value = '') {
  const children = [];
  if (attribute) {
    const badge = new Badge();
    badge.setAttribute(attribute, value);
    badge.textContent = '3';
    children.push(badge);
  }
  return {
    href: `https://example.test${path}`, children,
    append(badge) { children.push(badge); },
    querySelector(selector) {
      return children.find(badge => selector.split(',').some(part => {
        const [, name, value] = part.trim().match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/) || [];
        return badge.attributes.has(name) && (value === undefined || badge.attributes.get(name) === value);
      })) || null;
    },
  };
}
const links = [
  link('/seller/orders', 'data-sidebar-count', 'orders'),
  link('/seller/returns', 'data-sidebar-count', 'returns'),
  link('/admin/rider-withdrawals', 'data-sidebar-count', 'rider_withdrawals'),
  link('/admin/seller-withdrawals', 'data-sidebar-count', 'seller_withdrawals'),
  link('/admin/account-reports', 'data-sidebar-count', 'account_reports'),
  link('/logistics/cash-handovers', 'data-sidebar-count', 'cash'),
  link('/logistics/rider-withdrawals', 'data-sidebar-count', 'withdrawals'),
  link('/admin/notifications', 'data-sidebar-count', 'notifications'),
  link('/seller/notifications', 'data-sidebar-count', 'notifications'),
  link('/logistics/notifications', 'data-sidebar-count', 'notifications'),
  link('/buyer/notifications', 'data-notification-count'),
  link('/buyer/cart', 'data-cart-count'),
  link('/buyer/wishlist', 'data-wishlist-count'),
  link('/buyer/orders'), link('/seller/wallet'),
];
const root = { dataset: { url: '/updates', readUrl: '/read' } };
let initialize;
let polls = [];
let total = 7;
const context = {
  document: {
    hidden: false,
    querySelector: selector => selector === '[data-account-updates]' ? root : null,
    querySelectorAll: () => links,
    createElement: () => new Badge(),
    addEventListener(name, callback) { if (name === 'DOMContentLoaded') initialize = callback; },
  },
  window: { setInterval(callback) { polls.push(callback); return polls.length; }, clearInterval() {}, addEventListener() {} },
  location: { pathname: '/seller/dashboard' }, URL,
  fetch: async () => ({ ok: true, json: async () => ({ unread: total, notification_count: total, counts: { orders: total, wallet: total, payouts: total, withdrawals: total } }) }),
};
const source = readFileSync(new URL('../../public/js/account-updates.js', import.meta.url), 'utf8');
for (let attempt = 0; attempt < 2; attempt++) {
  vm.runInNewContext(source, context);
  initialize();
  await new Promise(resolve => setImmediate(resolve));
  links.forEach(item => assert.equal(item.children.length, 1, `Exactly one counter on ${item.href}`));
}
for (const item of links.slice(0, 7)) assert.equal(item.children[0].textContent, '3', 'Workload counters retain their own count source');
total = 0;
for (const poll of polls) await poll();
for (const item of links.filter(item => /notifications|buyer\/orders|seller\/wallet/.test(item.href))) assert.equal(item.children[0].hidden, true);
links.forEach(item => assert.equal(item.children.length, 1));
console.log('Buyer, seller, admin and logistics counters: one badge per item, correct source, repeat initialization and zero counts passed.');
