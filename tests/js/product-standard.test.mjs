import { test } from 'node:test';
import assert from 'node:assert/strict';
import { initProductStandard } from '../../resources/js/components/product-standard.js';
import { initGalleryLightbox } from '../../resources/js/components/gallery-lightbox.js';

// Small DOM doubles exercise event/state contracts; these do not replace browser layout QA.
class Element {
    constructor() { this.dataset = {}; this.attributes = new Map(); this.events = new Map(); this.queries = new Map(); this.hidden = false; this.disabled = false; this.children = []; this.isConnected = true; this.inert = false; }
    setAttribute(key, value) { this.attributes.set(key, String(value)); }
    getAttribute(key) { return this.attributes.get(key) ?? null; }
    removeAttribute(key) { this.attributes.delete(key); if (key === 'hidden') this.hidden = false; }
    querySelector(selector) { return this.queries.get(selector)?.[0] ?? null; }
    querySelectorAll(selector) { return this.queries.get(selector) ?? []; }
    addEventListener(type, callback) { this.events.set(type, [...(this.events.get(type) || []), callback]); }
    async fire(type, extra = {}) { const event = { target: this, preventDefault() { this.defaultPrevented = true; }, ...extra }; for (const callback of this.events.get(type) || []) await callback(event); return event; }
    focus() { document.activeElement = this; }
    select() { this.selected = true; }
    getBoundingClientRect() { return { left: 0, right: 100 }; }
    contains(element) { return this.children.includes(element); }
    remove() { this.isConnected = false; }
    appendChild(element) { this.children.push(element); }
    map(selector, ...elements) { this.queries.set(selector, elements); return this; }
}
const setup = (t, summaries = [], tabRoots = []) => {
    const previous = { document: globalThis.document, window: globalThis.window, getComputedStyle: globalThis.getComputedStyle, navigator: Object.getOwnPropertyDescriptor(globalThis, 'navigator') };
    const doc = new Element();
    doc.body = new Element();
    doc.body.classList = { add() {}, remove() {} };
    doc.map('[data-product-summary]', ...summaries).map('[data-product-tabs]', ...tabRoots);
    globalThis.document = doc;
    globalThis.window = { location: { hash: '' } };
    globalThis.getComputedStyle = () => ({ direction: 'rtl' });
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: {} });
    t.after(() => {
        globalThis.document = previous.document; globalThis.window = previous.window; globalThis.getComputedStyle = previous.getComputedStyle;
        if (previous.navigator) Object.defineProperty(globalThis, 'navigator', previous.navigator); else delete globalThis.navigator;
    });
    return doc;
};

test('tabs enhance visible HTML with ARIA, one active panel and RTL keyboard navigation', async (t) => {
    const root = new Element(); root.dataset.defaultTab = 'specifications';
    const list = new Element();
    const tabs = ['description', 'specifications', 'documents'].map((key) => { const el = new Element(); el.dataset.productTab = key; return el; });
    const panels = tabs.map((tab) => { const el = new Element(); el.id = `panel-${tab.dataset.productTab}`; return el; });
    root.map('[data-product-tab]', ...tabs).map('[data-product-panel]', ...panels).map('.product-details__tabs', list);
    setup(t, [], [root]);
    assert.ok(panels.every((panel) => !panel.hidden), 'all content visible before enhancement');
    initProductStandard(); initProductStandard();
    assert.equal(list.getAttribute('role'), 'tablist');
    assert.equal(tabs[1].getAttribute('aria-selected'), 'true');
    assert.deepEqual(panels.map((panel) => panel.hidden), [true, false, true]);
    await tabs[1].fire('keydown', { key: 'ArrowLeft' });
    assert.equal(document.activeElement, tabs[2]);
    await tabs[2].fire('keydown', { key: 'Home' });
    assert.equal(document.activeElement, tabs[0]);
    await tabs[0].fire('keydown', { key: 'End' });
    assert.equal(tabs[2].tabIndex, 0);
    assert.equal(tabs[0].events.get('click').length, 1, 'repeat init does not duplicate handlers');
    await tabs[0].fire('click');
    assert.equal(panels[0].hidden, false);
});

test('integer quantity controls respect both limits and reject fractional or invalid input', async (t) => {
    const root = new Element(); const control = new Element(); const input = new Element(); const decrease = new Element(); const increase = new Element();
    let value = 1;
    Object.defineProperties(input, { value: { get: () => String(value) }, valueAsNumber: { get: () => Number(value) } });
    input.stepDown = () => { value -= 1; }; input.stepUp = () => { value += 1; }; input.setCustomValidity = (message) => { input.validationMessage = message; };
    control.map('input', input).map('[data-quantity-decrease]', decrease).map('[data-quantity-increase]', increase);
    root.map('[data-product-quantity]', control);
    setup(t, [root]); initProductStandard();
    assert.equal(decrease.disabled, true);
    await increase.fire('click'); assert.equal(value, 2); assert.equal(decrease.disabled, false);
    value = 99; await input.fire('input'); assert.equal(increase.disabled, true);
    for (value of [0, 100, 1.5, 'x']) { await input.fire('input'); assert.match(input.validationMessage, /صحیح/); }
    value = 1; await input.fire('input'); assert.equal(input.validationMessage, '');
});

test('thumbnail selection updates image, alt, active state and the shared lightbox destination', async (t) => {
    const root = new Element(); const gallery = new Element(); const main = new Element(); const image = new Element(); const error = new Element();
    const first = new Element(); first.href = 'https://example.com/a.jpg'; first.dataset.imageAlt = 'اول'; first.setAttribute('aria-current', 'true');
    const second = new Element(); second.href = 'https://example.com/b.jpg'; second.dataset.imageAlt = 'دوم';
    root.map('[data-product-gallery]', gallery);
    gallery.map('[data-product-main-image]', main).map('[data-product-image-error]', error).map('[data-product-thumbnail]', first, second);
    main.map('img', image);
    setup(t, [root]); initProductStandard();
    await second.fire('click');
    assert.equal(image.src, second.href); assert.equal(image.alt, 'دوم');
    assert.equal(main.dataset.galleryLightboxSrc, second.href);
    assert.equal(first.getAttribute('aria-current'), null);
    assert.equal(second.getAttribute('aria-current'), 'true');
    await image.fire('error'); assert.equal(image.hidden, true); assert.equal(error.hidden, false);
    await first.fire('click'); await image.fire('load'); assert.equal(error.hidden, true); assert.equal(image.hidden, false);
});

test('share copies the canonical product URL and exposes a manual fallback on failure', async (t) => {
    const root = new Element(); const button = new Element(); const status = new Element(); const fallback = new Element(); const input = new Element();
    button.dataset.shareUrl = 'https://example.com/shop/item'; button.dataset.shareTitle = 'محصول';
    root.map('[data-product-share]', button).map('[data-product-share-status]', status).map('[data-product-share-fallback]', fallback); fallback.map('input', input);
    setup(t, [root]); let copied;
    navigator.clipboard = { writeText: async (value) => { copied = value; } };
    initProductStandard(); await button.fire('click');
    assert.equal(copied, button.dataset.shareUrl); assert.match(status.textContent, /کپی شد/);
    navigator.clipboard.writeText = async () => { throw new Error('denied'); };
    await button.fire('click'); assert.equal(fallback.hidden, false); assert.equal(input.selected, true); assert.equal(document.activeElement, input);
    assert.equal(button.disabled, false);
    navigator.share = async () => { throw Object.assign(new Error('cancel'), { name: 'AbortError' }); };
    await button.fire('click'); assert.equal(status.textContent, '');
});

test('shared lightbox supports groups, keyboard navigation, focus trapping/restoration and legacy single-image triggers', async (t) => {
    const doc = setup(t); const background = new Element(); background.tagName = 'MAIN'; doc.body.children = [background];
    doc.createElement = () => {
        const overlay = new Element();
        const close = new Element(); const previous = new Element(); const next = new Element(); const image = new Element(); const status = new Element(); const error = new Element();
        overlay.children = [close, previous, next, image, status, error];
        overlay.map('.gallery-lightbox__close', close).map('.gallery-lightbox__previous', previous).map('.gallery-lightbox__next', next)
            .map('img', image).map('[data-lightbox-status]', status).map('[data-lightbox-error]', error).map('button', close, previous, next);
        doc.overlay = overlay; return overlay;
    };
    const trigger = new Element(); trigger.href = 'https://example.com/a.jpg'; trigger.setAttribute('data-gallery-lightbox-src', trigger.href);
    const gallery = new Element(); const first = new Element(); first.href = trigger.href; first.dataset.imageAlt = 'اول'; const second = new Element(); second.href = 'https://example.com/b.jpg'; second.dataset.imageAlt = 'دوم';
    gallery.map('[data-product-thumbnail]', first, second);
    trigger.closest = (selector) => selector === '[data-product-gallery]' ? gallery : trigger;
    initGalleryLightbox(); initGalleryLightbox();
    assert.equal(doc.events.get('click').length, 1);
    await doc.fire('click', { target: trigger });
    assert.equal(background.inert, true); assert.equal(doc.overlay.getAttribute('aria-modal'), 'true');
    await doc.fire('keydown', { key: 'ArrowLeft' }); assert.equal(doc.overlay.querySelector('img').src, second.href);
    const close = doc.overlay.querySelector('.gallery-lightbox__close');
    await doc.fire('keydown', { key: 'Tab', shiftKey: true }); assert.equal(doc.activeElement, doc.overlay.querySelector('.gallery-lightbox__next'));
    await doc.fire('keydown', { key: 'Tab', shiftKey: false }); assert.equal(doc.activeElement, close);
    await doc.fire('keydown', { key: 'Escape' }); assert.equal(background.inert, false); assert.equal(doc.activeElement, trigger);
    const legacy = new Element(); legacy.setAttribute('data-gallery-lightbox-src', 'https://example.com/legacy.jpg'); legacy.closest = (selector) => selector === '[data-product-gallery]' ? null : legacy;
    await doc.fire('click', { target: legacy });
    assert.equal(doc.overlay.querySelector('.gallery-lightbox__next').hidden, true);
    await doc.overlay.querySelector('.gallery-lightbox__close').fire('click'); assert.equal(doc.activeElement, legacy);
});
