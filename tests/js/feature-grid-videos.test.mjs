import { test } from 'node:test';
import assert from 'node:assert/strict';
import { safeVideoEmbed, initFeatureGridVideos } from '../../resources/js/components/feature-grid-videos.js';

test('the player accepts only canonical Aparat frame URLs', () => {
    const valid = 'https://www.aparat.com/video/video/embed/videohash/Ab12_x/vt/frame';
    assert.equal(safeVideoEmbed(valid), valid);
    for (const value of [
        'javascript:alert(1)', '//www.aparat.com/v/a', 'http://www.aparat.com/video/video/embed/videohash/a/vt/frame',
        valid.replace('www.aparat.com', 'www.aparat.com.evil.test'), valid.replace('www.aparat.com', 'user@www.aparat.com'),
        `${valid}?extra=1`, `${valid}#fragment`, 'https://www.aparat.com/v/a',
    ]) assert.equal(safeVideoEmbed(value), null, value);
});

test('no iframe loads before Play and one shared dialog removes the player on close or ESC', (t) => {
    const created = [];
    const listeners = new Map();
    const classes = new Set();
    let focused;
    class Element extends EventTarget {
        constructor(tag) { super(); this.tag = tag; this.children = []; this.dataset = {}; this.isConnected = true; }
        setAttribute() {}
        append(...nodes) { this.children.push(...nodes); }
        replaceChildren(...nodes) { this.children = nodes; }
        showModal() { this.open = true; }
        close() { this.open = false; this.dispatchEvent(new Event('close')); }
        focus() { focused = this; }
        closest() { return this; }
    }
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    globalThis.document = {
        documentElement: { dataset: {}, classList: { add: (name) => classes.add(name), remove: (name) => classes.delete(name) } },
        body: new Element('body'),
        createElement: (tag) => { const element = new Element(tag); created.push(element); return element; },
        addEventListener: (type, callback) => listeners.set(type, [...(listeners.get(type) || []), callback]),
    };
    globalThis.window = { addEventListener() {} };
    t.after(() => { globalThis.document = previousDocument; globalThis.window = previousWindow; });
    const click = (element) => listeners.get('click').forEach((callback) => callback({ target: element, preventDefault() {} }));

    initFeatureGridVideos();
    initFeatureGridVideos();
    assert.equal(listeners.get('click').length, 1);
    assert.equal(created.length, 0);
    const trigger = new Element('button');
    trigger.dataset = { videoEmbed: 'https://evil.test/embed', videoTitle: 'Video' };
    click(trigger);
    assert.equal(created.length, 0);
    trigger.dataset.videoEmbed = 'https://www.aparat.com/video/video/embed/videohash/first/vt/frame';
    click(trigger);
    const dialog = created.find((element) => element.tag === 'dialog');
    const player = created.find((element) => element.className === 'feature-video-dialog__player');
    assert.equal(player.children[0].tag, 'iframe');
    assert.equal(dialog.open, true);
    assert.equal(classes.has('feature-video-dialog-open'), true);
    dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
    assert.equal(player.children.length, 0);
    assert.equal(classes.size, 0);
    assert.equal(focused, trigger);

    trigger.dataset.videoEmbed = 'https://www.aparat.com/video/video/embed/videohash/second/vt/frame';
    click(trigger);
    assert.equal(created.filter((element) => element.tag === 'dialog').length, 1);
    assert.equal(player.children.length, 1);
    created.find((element) => element.tag === 'button').dispatchEvent(new Event('click'));
    assert.equal(player.children.length, 0);
});
