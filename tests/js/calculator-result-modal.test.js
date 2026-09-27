import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const app = await readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const initializer = app.slice(app.indexOf('const initCalculatorResultModals ='), app.indexOf('const initPublicInteractions ='));

class Element extends EventTarget {
    dataset = {};
    hidden = true;
    focused = false;
    focus() { this.focused = true; }
}

const fixture = () => {
    const modal = new Element();
    const closeButton = new Element();
    const submit = new Element();
    const document = new EventTarget();
    const window = new EventTarget();
    const classes = new Set();
    modal.querySelector = () => closeButton;
    modal.previousElementSibling = { querySelector: () => submit };
    document.querySelectorAll = () => [modal];
    document.querySelector = () => modal.hidden ? null : modal;
    document.body = { classList: { add: (value) => classes.add(value), remove: (value) => classes.delete(value) } };
    const context = { document, window, HTMLElement: Element, CustomEvent };
    runInNewContext(`${initializer}\ninitCalculatorResultModals();`, context);
    return { modal, closeButton, submit, document, window, classes };
};

test('a result present in the first response auto-opens immediately and locks body scroll', () => {
    const { modal, closeButton, classes } = fixture();
    assert.equal(modal.hidden, false);
    assert.equal(closeButton.focused, true);
    assert.equal(classes.has('calculator-result-modal-open'), true);
});

test('Escape closes the dialog; the result link reopens it and backdrop closes it', () => {
    const { modal, document, submit, classes } = fixture();
    const escape = new Event('keydown');
    Object.defineProperty(escape, 'key', { value: 'Escape' });
    document.dispatchEvent(escape);
    assert.equal(modal.hidden, true);
    assert.equal(submit.focused, true);
    assert.equal(classes.has('calculator-result-modal-open'), false);
    const opener = new Element();
    modal.dispatchEvent(new CustomEvent('calculator-result:open', { detail: { opener } }));
    assert.equal(modal.hidden, false);
    modal.dispatchEvent(new Event('click'));
    assert.equal(modal.hidden, true);
    assert.equal(opener.focused, true);
});

test('a browser-history restore expires the result instead of reopening stale content', () => {
    const { modal, window, classes } = fixture();
    let expired = false;
    modal.addEventListener('calculator-result:expired', () => { expired = true; });
    const restored = new Event('pageshow');
    Object.defineProperty(restored, 'persisted', { value: true });
    window.dispatchEvent(restored);
    assert.equal(modal.hidden, true);
    assert.equal(expired, true);
    assert.equal(classes.has('calculator-result-modal-open'), false);
    modal.dispatchEvent(new CustomEvent('calculator-result:open'));
    assert.equal(modal.hidden, true);
});
