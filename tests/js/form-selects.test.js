import assert from 'node:assert/strict';
import test from 'node:test';
import { calculateFormSelectPosition, normalizePersianSearch } from '../../resources/js/components/form-selects.js';

const viewport = { top: 0, left: 0, right: 1200, bottom: 800 };
const triggerRect = { top: 100, bottom: 144, left: 400, right: 700, width: 300 };

test('a desktop select uses its full trigger width and opens below', () => {
    const result = calculateFormSelectPosition({ triggerRect, viewport, desiredHeight: 300 });
    assert.equal(result.placement, 'bottom');
    assert.equal(result.width, 300);
    assert.equal(result.left, 400);
    assert.equal(result.top, 152);
});

test('a select near the viewport bottom opens upward within the available height', () => {
    const result = calculateFormSelectPosition({ triggerRect: { ...triggerRect, top: 680, bottom: 724 }, viewport });
    assert.equal(result.placement, 'top');
    assert.equal(result.top, 312);
    assert.ok(result.top >= 0);
    assert.ok(result.top + result.maxHeight < 680);
});

test('mobile selection centers within the visible part of its owning modal', () => {
    const result = calculateFormSelectPosition({
        triggerRect: { top: 580, bottom: 624, left: 32, right: 358, width: 326 },
        viewport: { top: 0, left: 0, right: 390, bottom: 720 },
        modalRect: { top: 40, bottom: 680, left: 16, right: 374 },
    });
    assert.equal(result.placement, 'center');
    assert.equal(result.width, 334);
    assert.equal(result.left, 28);
    assert.ok(result.top >= 52);
    assert.ok(result.top + result.maxHeight <= 668);
});

test('a short desktop viewport falls back to centered selection', () => {
    const result = calculateFormSelectPosition({
        triggerRect: { ...triggerRect, top: 135, bottom: 179 },
        viewport: { ...viewport, bottom: 300 },
    });
    assert.equal(result.placement, 'center');
    assert.equal(result.maxHeight, 276);
    assert.equal(result.top, 12);
});

test('keyboard and zoom viewport offsets constrain every edge of the centered panel', () => {
    const view = { top: 230, left: 40, right: 400, bottom: 490 };
    const result = calculateFormSelectPosition({ triggerRect, viewport: view,
        modalRect: { top: 20, bottom: 750, left: 0, right: 430 } });
    assert.equal(result.placement, 'center');
    assert.ok(result.left >= view.left + 12);
    assert.ok(result.top >= view.top + 12);
    assert.ok(result.left + result.width <= view.right - 12);
    assert.ok(result.top + result.maxHeight <= view.bottom - 12);
});

test('search matches Persian and Arabic kaf/yeh and joining/combining characters', () => {
    assert.equal(normalizePersianSearch('كيش'), normalizePersianSearch('کیش'));
    assert.equal(normalizePersianSearch('آذربايجان شرقي'), normalizePersianSearch('آذربایجان شرقی'));
    assert.equal(normalizePersianSearch('نیم‌وَر'), normalizePersianSearch('نیم ور'));
});
