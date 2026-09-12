// Run with PHP serving port 8765 and a headless Chrome debug endpoint on port 9224.
// No account/address writes or external PSGC requests are made by this test.
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const tabs = await (await fetch('http://127.0.0.1:9224/json')).json();
const socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
let sequence = 0;
const pending = new Map();
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (!message.id) return;
    const { resolve, reject } = pending.get(message.id);
    pending.delete(message.id);
    message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
});
function send(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++sequence;
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
async function evaluate(expression) {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
}
const data = {
    regions: [{ code: '040000000', name: 'CALABARZON' }, { code: '130000000', name: 'National Capital Region' }, { code: '010000000', name: 'Test mixed region' }],
    'regions/040000000/provinces': [{ code: '042100000', name: 'Cavite' }],
    'regions/040000000/cities-municipalities': [],
    'provinces/042100000/cities-municipalities': [{ code: '042103000', name: 'City of Bacoor' }],
    'cities-municipalities/042103000/barangays': [{ code: '042103001', name: 'Alima' }],
    'regions/130000000/provinces': [],
    'regions/130000000/cities-municipalities': [{ code: '137404000', name: 'Quezon City' }],
    'cities-municipalities/137404000/barangays': [{ code: '137404001', name: 'Alicia' }],
    'regions/010000000/provinces': [{ code: '012800000', name: 'Test province' }],
    'regions/010000000/cities-municipalities': [{ code: '013300000', name: 'Test province-free locality' }],
    'cities-municipalities/013300000/barangays': [{ code: '013300001', name: 'Test barangay' }],
};
const script = readFileSync('public/js/address-selector.js', 'utf8');
let html = execFileSync('php', ['tests/Support/render-address-form.php'], { encoding: 'utf8' });
// Install the script explicitly after the mock, not via its external script tag.
html = html.replace(/<script src="[^"]*address-selector.js" defer><\/script>/, '');
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
async function waitFor(expression) {
    for (let attempt = 0; attempt < 100; attempt++) {
        if (await evaluate(expression)) return;
        await pause(30);
    }
    throw new Error('Timed out: ' + expression);
}
async function change(field, value) {
    await evaluate("document.querySelector('[data-location=\"" + field + "\"]').value = " + JSON.stringify(value) + "; document.querySelector('[data-location=\"" + field + "\"]').dispatchEvent(new Event('change'))");
}
const value = field => evaluate("document.querySelector('[data-location=\"" + field + "\"]').value");
async function render(initial = {}, fail = false) {
    await send('Page.navigate', { url: 'http://127.0.0.1:8765/register' });
    await pause(200);
    const { frameTree } = await send('Page.getFrameTree');
    await send('Page.setDocumentContent', { frameId: frameTree.frame.id, html });
    await evaluate('window.fixtureData = ' + JSON.stringify(data) + '; window.failLocations = ' + fail + '; window.locationDelay = 0;');
    await evaluate("(window.fetch = async (url, options) => { const path = url.split('/api/locations/')[1]; const delay = window.locationDelay; const fail = window.failLocations; await new Promise(resolve => setTimeout(resolve, delay)); return { ok: !fail, json: async () => window.fixtureData[path] ?? [] }; })");
    for (const [field, selected] of Object.entries(initial)) {
        await evaluate("document.querySelector('[data-location=\"" + field + "\"]').dataset.selected = " + JSON.stringify(selected));
    }
    await evaluate(script);
    await waitFor(fail ? "!document.querySelector('[data-location-retry]').hidden" : "!document.querySelector('[data-location=\"region\"]').disabled && document.querySelector('[data-location-status]').textContent.startsWith('Locations loaded')");
}
try {
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await render();
    await change('region', '040000000');
    await waitFor("!document.querySelector('[data-location=\"province\"]').disabled");
    await change('province', '042100000');
    await waitFor("!document.querySelector('[data-location=\"city\"]').disabled");
    await change('city', '042103000');
    await waitFor("!document.querySelector('[data-location=\"barangay\"]').disabled");
    await change('barangay', '042103001');
    assert.equal(await evaluate("document.querySelector('[data-address-save]').disabled"), false);
    await change('province', '');
    assert.equal(await value('city'), '');
    assert.equal(await value('barangay'), '');
    assert.equal(await evaluate("document.querySelector('[data-address-save]').disabled"), true);

    await change('region', '130000000');
    await waitFor("!document.querySelector('[data-location=\"city\"]').disabled");
    assert.equal(await evaluate("document.querySelector('[data-location=\"province\"]').disabled"), true);
    await change('city', '137404000');
    await waitFor("!document.querySelector('[data-location=\"barangay\"]').disabled");
    await change('barangay', '137404001');
    assert.equal(await evaluate("document.querySelector('[data-address-save]').disabled"), false);
    assert.equal(await evaluate('document.documentElement.scrollWidth'), 390);
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.address-form .form-row')).gridTemplateColumns.split(' ').length"), 1);
    const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    writeFileSync('storage/app/address-selector-mobile.png', Buffer.from(screenshot.data, 'base64'));
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
    assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.address-form .form-row')).gridTemplateColumns.split(' ').length"), 2);

    await render({ region: '040000000', province: '042100000', city: '042103000', barangay: '042103001' });
    assert.equal(await value('barangay'), '042103001');
    assert.equal(await evaluate("document.querySelector('[data-address-save]').disabled"), false);
    await render({ region: '130000000', city: '137404000', barangay: '137404001' });
    assert.equal(await value('barangay'), '137404001');
    await render({ region: '010000000', city: '013300000', barangay: '013300001' });
    assert.equal(await value('province'), '__none__');
    assert.equal(await value('barangay'), '013300001');
    await evaluate("document.querySelector('[data-address-form]').dispatchEvent(new Event('submit', { cancelable: true }))");
    assert.equal(await evaluate("new FormData(document.querySelector('[data-address-form]')).has('province_code')"), false);

    await render({}, true);
    assert.equal(await evaluate("document.querySelector('[data-address-save]').disabled"), true);
    await evaluate("window.failLocations = false; document.querySelector('[data-location-retry]').click()");
    await waitFor("document.querySelector('[data-location-status]').textContent.startsWith('Locations loaded')");
    // Mock intentionally ignores AbortSignal to prove stale responses cannot overwrite a newer selection.
    await evaluate('window.locationDelay = 200');
    await change('region', '040000000');
    await evaluate('window.locationDelay = 0');
    await change('region', '130000000');
    await waitFor("!document.querySelector('[data-location=\"city\"]').disabled");
    await pause(250);
    assert.equal(await value('region'), '130000000');
    assert.equal(await evaluate("document.querySelector('[data-location=\"province\"]').disabled"), true);
    assert.equal(await evaluate("document.querySelector('[data-location=\"city\"]').options[1].value"), '137404000');
    console.log('PASS: dependent resets, NCR, province-free localities, edit hydration, failure/retry, stale-response protection, and 390px layout.');
} finally {
    await send('Browser.close');
    socket.close();
}
