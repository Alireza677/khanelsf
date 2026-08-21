import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import {
    searchScopeSummary,
    syncCartPresentation,
} from '../../resources/js/components/header-overlays.js';

const source = readFileSync(
    new URL('../../resources/js/components/header-overlays.js', import.meta.url),
    'utf8',
);
const styles = readFileSync(
    new URL('../../resources/css/app.css', import.meta.url),
    'utf8',
);

test('shared overlay controller owns close, escape, focus, inert background and scroll lock', () => {
    assert.match(source, /data-header-overlay-close/);
    assert.match(source, /event\.key === 'Escape'/);
    assert.match(source, /trigger\?\.focus\(\)/);
    assert.match(source, /element\.inert = true/);
    assert.match(source, /header-overlay-open/);
    assert.match(source, /event\.key !== 'Tab'/);
});

test('opening an overlay closes the active overlay and initialization is idempotent', () => {
    assert.match(source, /if \(activeOverlay\) \{\s*close\(\{ restoreFocus: false \}\)/);
    assert.match(source, /headerOverlaysInitialized === 'true'/);
    assert.match(source, /aria-expanded', 'true'/);
    assert.match(source, /aria-expanded', 'false'/);
});

test('search scope summary covers all, one and multiple selections', () => {
    assert.equal(searchScopeSummary(['محصولات', 'پروژه‌ها'], 2), 'جستجو در همه');
    assert.equal(searchScopeSummary(['محصولات'], 4), 'فقط محصولات');
    assert.equal(searchScopeSummary(['محصولات', 'پروژه‌ها'], 4), 'جستجو در ۲ بخش');
});

test('search scope controller enforces selection and clean all-sources submission', () => {
    assert.match(source, /if \(! types\.some\(\(item\) => item\.checked\)\)/);
    assert.match(source, /input\.checked = true/);
    assert.match(source, /input\.removeAttribute\('name'\)/);
    assert.match(source, /selected\.length < types\.length/);
    assert.match(source, /master\.indeterminate/);
});

test('search scope toggle keeps expanded state semantic for CSS icon switching', () => {
    assert.match(source, /selector\.classList\.toggle\('is-expanded', expanded\)/);
    assert.match(source, /toggle\.setAttribute\('aria-expanded', String\(expanded\)\)/);
});

test('drawer remove is progressively enhanced without navigation', () => {
    assert.match(source, /data-cart-drawer-remove/);
    assert.match(source, /event\.preventDefault\(\)/);
    assert.match(source, /'Accept': 'application\/json'/);
    assert.match(source, /credentials: 'same-origin'/);
    assert.doesNotMatch(source, /location\.reload|window\.location\.href/);
});

test('successful drawer removal syncs row, badge, subtotal and empty state', () => {
    assert.match(source, /if \(! state\.is_empty\) \{\s*await removeRow\(row\)/);
    assert.match(source, /badge\.textContent = state\.display_count/);
    assert.match(source, /subtotal\.textContent = state\.subtotal_formatted/);
    assert.match(source, /data-cart-empty-state/);
    assert.match(source, /data-cart-footer/);
});

test('authoritative empty response atomically removes all rows and hides non-empty UI', () => {
    assert.match(source, /if \(state\.is_empty\) \{\s*items\?\.querySelectorAll\('\[data-cart-item\]'\)\.forEach\(\(item\) => item\.remove\(\)\)/);
    assert.match(source, /items\?\.toggleAttribute\('hidden', state\.is_empty\)/);
    assert.match(source, /footer\?\.toggleAttribute\('hidden', state\.is_empty\)/);
    assert.match(source, /subtotalBlock\?\.toggleAttribute\('hidden', state\.is_empty\)/);
    assert.match(source, /actions\?\.toggleAttribute\('hidden', state\.is_empty\)/);
    assert.match(source, /emptyState\?\.toggleAttribute\('hidden', ! state\.is_empty\)/);
    assert.match(styles, /\.header-cart-drawer__items\[hidden\][\s\S]*display: none !important/);
    assert.match(styles, /\.header-cart-drawer__footer\[hidden\]/);
    assert.match(styles, /\.industrial-header__cart-badge\[hidden\]/);
});

const stateElement = () => ({
    hidden: false,
    textContent: '',
    attributes: {},
    toggleAttribute(name, force) { this.attributes[name] = force; this.hidden = force; },
    setAttribute(name, value) { this.attributes[name] = value; },
});

test('empty response removes every item while drawer stays open', () => {
    const rows = [{ removed: false, remove() { this.removed = true; } }];
    const elements = Object.fromEntries([
        '[data-cart-items]', '[data-cart-empty-state]', '[data-cart-footer]',
        '[data-cart-subtotal-block]', '[data-cart-actions]', '[data-cart-subtotal]',
    ].map((selector) => [selector, stateElement()]));
    elements['[data-cart-items]'].querySelectorAll = () => rows;
    const drawer = { hidden: false, querySelector: (selector) => elements[selector] };
    const badge = stateElement();
    const trigger = stateElement();
    const root = { querySelectorAll: (selector) => selector.includes('trigger') ? [trigger] : [badge] };

    syncCartPresentation(drawer, {
        count: 0, display_count: '0', subtotal_formatted: '0 تومان', is_empty: true,
    }, root);

    assert.equal(rows.every((row) => row.removed), true);
    assert.equal(elements['[data-cart-items]'].hidden, true);
    assert.equal(elements['[data-cart-footer]'].hidden, true);
    assert.equal(elements['[data-cart-subtotal-block]'].hidden, true);
    assert.equal(elements['[data-cart-actions]'].hidden, true);
    assert.equal(elements['[data-cart-empty-state]'].hidden, false);
    assert.equal(badge.hidden, true);
    assert.equal(trigger.attributes['aria-label'], 'سبد خرید');
    assert.equal(drawer.hidden, false);
});

test('non-empty response preserves remaining rows and refreshes subtotal UI', () => {
    const remainingRow = { removed: false, remove() { this.removed = true; } };
    const elements = Object.fromEntries([
        '[data-cart-items]', '[data-cart-empty-state]', '[data-cart-footer]',
        '[data-cart-subtotal-block]', '[data-cart-actions]', '[data-cart-subtotal]',
    ].map((selector) => [selector, stateElement()]));
    elements['[data-cart-items]'].querySelectorAll = () => [remainingRow];
    const drawer = { querySelector: (selector) => elements[selector] };
    const badge = stateElement();
    const root = { querySelectorAll: (selector) => selector.includes('trigger') ? [] : [badge] };

    syncCartPresentation(drawer, {
        count: 1, display_count: '1', subtotal_formatted: '250 تومان', is_empty: false,
    }, root);

    assert.equal(remainingRow.removed, false);
    assert.equal(elements['[data-cart-empty-state]'].hidden, true);
    assert.equal(elements['[data-cart-footer]'].hidden, false);
    assert.equal(elements['[data-cart-subtotal]'].textContent, '250 تومان');
    assert.equal(badge.hidden, false);
});

test('failed and concurrent drawer removals remain deterministic', () => {
    assert.match(source, /mutationQueue = mutationQueue\.then/);
    assert.match(source, /button\.disabled = false/);
    assert.match(source, /حذف محصول انجام نشد/);
});
