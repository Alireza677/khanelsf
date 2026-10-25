import test from 'node:test';
import assert from 'node:assert/strict';
import picker from '../../resources/js/filament/destination-picker.js';

globalThis.window = { location: { origin: 'https://cms.test' } };

function component(overrides = {}) {
    let focused = '';
    const events = [];
    const watches = {};
    const instance = picker({ endpoint: '/admin/internal-links/search', ...overrides });
    Object.assign(instance, {
        $refs: {
            dialog: { open: false, showModal() { this.open = true; }, close() { this.open = false; } },
            search: { focus() { focused = 'search'; } },
            trigger: { focus() { focused = 'trigger'; } },
        },
        $nextTick(callback) { callback(); },
        $watch(key, callback) { watches[key] = callback; },
        $dispatch(name, detail) { events.push({ name, detail }); },
    });
    instance.init();
    return { instance, events, watches, focus: () => focused };
}

const response = (data) => ({ ok: true, json: async () => data });
const page = (id, current = 1, total = 42) => ({
    items: [{ reference_id: id, title: 'Destination', selectable: true }],
    total, page: current, last_page: 3,
});

test('opening immediately requests page one with an empty search, then supports paging and category search', async (t) => {
    const requests = [];
    t.mock.method(globalThis, 'fetch', async (url) => {
        requests.push(new URL(url));
        return response(page(requests.length, Number(url.searchParams.get('page'))));
    });
    const { instance, focus } = component({ target: 'project' });
    await instance.open();
    assert.equal(requests[0].searchParams.get('q'), '');
    assert.equal(requests[0].searchParams.get('page'), '1');
    assert.equal(instance.total, 42);
    assert.equal(instance.$refs.dialog.open, true);
    assert.equal(focus(), 'search');
    await instance.load(2);
    assert.equal(instance.page, 2);
    instance.query = 'title';
    instance.category = '7';
    await instance.load(1);
    assert.equal(requests[2].searchParams.get('category_id'), '7');
    assert.equal(requests[2].searchParams.get('q'), 'title');
});

test('select, clear and focus return affect only the owning instance', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => response(page(42)));
    const first = component({ state: 1 });
    const second = component({ state: 2 });
    await first.instance.open();
    first.instance.choose({ reference_id: 42, title: '<img onerror=alert(1)>', selectable: true });
    assert.equal(first.instance.state, 42);
    assert.equal(first.instance.label, '<img onerror=alert(1)>');
    assert.equal(first.instance.$refs.dialog.open, false);
    assert.equal(first.focus(), 'trigger');
    assert.equal(second.instance.state, 2);
    first.instance.clear();
    assert.equal(first.instance.state, null);
    assert.equal(second.instance.state, 2);
});

test('disabled rows and fields cannot alter references; rich text emits a URL row without reference state', () => {
    const { instance } = component({ state: 3 });
    instance.choose({ reference_id: 4, selectable: false });
    assert.equal(instance.state, 3);
    const disabled = component({ state: 5, disabled: true }).instance;
    disabled.clear();
    disabled.open();
    disabled.choose({ reference_id: 4, selectable: true });
    assert.equal(disabled.state, 5);
    assert.equal(disabled.modalOpen, false);
    const richText = component({ mode: 'url' });
    richText.instance.choose({ reference_id: 42, url: '/current-slug', selectable: true });
    assert.equal(richText.instance.state, null);
    assert.equal(richText.events[0].name, 'destination-picked');
    assert.equal(richText.events[0].detail.url, '/current-slug');
});

test('stale responses cannot overwrite newer results or resurrect a destroyed picker', async (t) => {
    const pending = [];
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => pending.push(resolve)));
    const { instance } = component();
    const old = instance.load(1);
    const recent = instance.load(2);
    pending[1](response(page(22, 2)));
    await recent;
    pending[0](response(page(11)));
    await old;
    assert.equal(instance.items[0].reference_id, 22);
    const final = instance.load(3);
    instance.destroy();
    pending[2](response(page(33, 3)));
    await final;
    assert.equal(instance.items.length, 0);
});

test('empty and failed pages remain distinct; retry replaces the error', async (t) => {
    t.mock.method(globalThis, 'fetch', async () => ({ ok: false }));
    const { instance } = component();
    await instance.load(1);
    assert.ok(instance.error);
    assert.equal(instance.loading, false);
    globalThis.fetch.mock.mockImplementation(async () => response({ items: [], total: 0, page: 1, last_page: 1 }));
    await instance.load(1);
    assert.equal(instance.error, '');
    assert.equal(instance.total, 0);
    assert.deepEqual(instance.items, []);
});

test('reference updates from undo/redo refresh the label and clearing cancels an old label response', async (t) => {
    const pending = [];
    t.mock.method(globalThis, 'fetch', () => new Promise((resolve) => pending.push(resolve)));
    const { instance, watches } = component({ state: 11 });
    const first = watches.state();
    instance.state = 22;
    const second = watches.state();
    pending[1](response({ label: 'Restored destination' }));
    await second;
    pending[0](response({ label: 'Old destination' }));
    await first;
    assert.equal(instance.label, 'Restored destination');
    const third = watches.state();
    instance.state = null;
    await watches.state();
    pending[2](response({ label: 'Stale label' }));
    await third;
    assert.equal(instance.label, 'موردی انتخاب نشده است');
});
