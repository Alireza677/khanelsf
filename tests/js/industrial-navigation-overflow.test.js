import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const initialization = source.slice(
    source.indexOf('const initDesktopNavigationOverflow ='),
    source.indexOf('const initActionPlaceholders ='),
);

// Controlled browser geometry, not a browser rendering test. The deliberately collapsed
// list and oversized scrollWidth reproduce the old measurement feedback problem.
function fixture(itemWidths, initialViewport = 1920, loadingFonts = false) {
    const state = { viewport: initialViewport, utilityExtra: 0 };
    const frames = new Map();
    const events = {};
    const fontEvents = {};
    const styles = new Map();
    const observers = [];
    let frameId = 0;
    let fontsReady;
    const fonts = {
        status: loadingFonts ? 'loading' : 'loaded',
        ready: new Promise((resolve) => { fontsReady = resolve; }),
        addEventListener: (name, callback) => { fontEvents[name] = callback; },
    };
    if (! loadingFonts) fontsReady();
    const classes = () => ({ remove() {}, toggle() {}, contains: () => false });
    const detach = (item) => {
        if (item.parentElement) {
            const siblings = item.parentElement.children;
            siblings.splice(siblings.indexOf(item), 1);
        }
    };
    const moreItems = {
        children: [],
        querySelectorAll: () => [],
        prepend(item) { detach(item); this.children.unshift(item); item.parentElement = this; },
    };
    const trigger = { addEventListener() {}, setAttribute() {}, focus() {} };
    const more = {
        hidden: true,
        classList: classes(),
        getBoundingClientRect: () => ({ width: 52 }),
        contains: () => false,
        querySelector: (selector) => selector.includes('trigger') ? trigger : moreItems,
    };
    const toggle = {};
    const header = { querySelector: () => toggle };
    const utilities = {
        getBoundingClientRect: () => ({ width: (state.viewport < 1360 ? 460 : 469.6) + state.utilityExtra }),
    };
    const panel = {
        get clientWidth() {
            const container = Math.min(1440, state.viewport);
            const gutter = Math.max(16, Math.min(32, state.viewport * 0.02));
            return container - 2 * gutter - 54 - (state.viewport < 1360 ? 16 : 24);
        },
        querySelector: () => utilities,
    };
    const nav = { style: {
        setProperty: (key, value) => styles.set(key, value),
        removeProperty: (key) => styles.delete(key),
    } };
    const navigation = {
        dataset: {}, parentElement: nav, children: [], clientWidth: 16, scrollWidth: 5000,
        querySelector: () => more,
        querySelectorAll: () => [],
        closest: (selector) => selector === '[data-site-header]' ? header : panel,
        addEventListener() {},
        insertBefore(item, before) {
            detach(item);
            this.children.splice(this.children.indexOf(before), 0, item);
            item.parentElement = this;
        },
    };
    navigation.children = itemWidths.map((width, id) => ({
        id, parentElement: navigation, getBoundingClientRect: () => ({ width }),
    }));
    navigation.children.push(more);
    const sandbox = {
        document: { fonts, querySelectorAll: () => [navigation], addEventListener() {} },
        window: {
            addEventListener: (name, callback) => { events[name] = callback; },
            getComputedStyle(element) {
                if (element === toggle) return { display: state.viewport < 1200 ? 'inline-flex' : 'none' };
                if (element === panel) return { paddingLeft: '0', paddingRight: '0', columnGap: state.viewport < 1360 ? '12px' : '24px' };
                return { columnGap: state.viewport < 1360 ? '13.6px' : '17.6px' };
            },
        },
        requestAnimationFrame(callback) { frames.set(++frameId, callback); return frameId; },
        cancelAnimationFrame(id) { frames.delete(id); },
        ResizeObserver: class {
            constructor(callback) { this.callback = callback; this.targets = []; observers.push(this); }
            observe(target) { this.targets.push(target); }
        },
    };
    vm.runInNewContext(`${initialization}\ninitDesktopNavigationOverflow();`, sandbox);
    return {
        state, more, moreItems, styles, events, observers, fonts, fontEvents, panel, utilities,
        visible: () => navigation.children.filter((item) => item !== more).map((item) => item.id),
        hidden: () => moreItems.children.map((item) => item.id),
        readyFonts() { fonts.status = 'loaded'; fontsReady(); },
        async flush() {
            await Promise.resolve();
            const callbacks = [...frames.values()]; frames.clear();
            callbacks.forEach((callback) => callback());
        },
    };
}

const menuWidths = [112, 110, 64, 58, 45, 70, 50, 55];

test('1920, 1440 and 1280 keep every fitting link despite a collapsed list; 1024 restores mobile items', async () => {
    const view = fixture(menuWidths);
    for (const width of [1920, 1440, 1280, 1024, 1440]) {
        view.state.viewport = width;
        view.events.resize();
        await view.flush();
        assert.deepEqual(view.visible(), menuWidths.map((_, index) => index), `viewport ${width}`);
        assert.equal(view.hidden().length, 0);
        assert.equal(view.more.hidden, true);
        if (width === 1024) assert.equal(view.styles.size, 0);
    }
});

test('only genuinely overflowing links move, preserving order and maximal visible prefix across resizes', async () => {
    const widths = [...menuWidths, 110, 110, 110];
    const view = fixture(widths);
    for (const [viewport, expectedVisible] of [[1920, 8], [1440, 8], [1280, 7], [1024, 11], [1920, 8]]) {
        view.state.viewport = viewport;
        view.events.resize();
        await view.flush();
        assert.deepEqual(view.visible(), widths.slice(0, expectedVisible).map((_, index) => index), `viewport ${viewport}`);
        assert.deepEqual(view.hidden(), widths.slice(expectedVisible).map((_, index) => expectedVisible + index));
        assert.equal(view.more.hidden, viewport === 1024);
    }
});

test('waits for fonts, ignores submenu scrollWidth and observes changes to real available space', async () => {
    const view = fixture(menuWidths, 1440, true);
    await view.flush();
    assert.equal(view.styles.size, 0, 'no measurement with pending fonts');
    view.readyFonts();
    await view.flush();
    assert.equal(view.visible().length, 8);
    assert.equal(view.more.hidden, true);
    assert.deepEqual(view.observers[0].targets, [view.panel, view.utilities]);
    view.state.utilityExtra = 300;
    view.observers[0].callback();
    await view.flush();
    assert.ok(view.hidden().length > 0, 'larger utilities reduce the real budget');
    view.state.utilityExtra = 0;
    view.fontEvents.loadingdone();
    view.events.load();
    await view.flush();
    assert.equal(view.visible().length, 8);
    assert.equal(view.more.hidden, true);
});
