import test from 'node:test';
import assert from 'node:assert/strict';

import {
    INDUSTRIAL_HEADER_HIDE_THRESHOLD,
    shouldHideIndustrialTopActions,
} from '../../resources/js/components/industrial-sticky-header.js';

test('top actions use stable hysteresis instead of scroll direction', () => {
    let hidden = shouldHideIndustrialTopActions(0, false);
    assert.equal(hidden, false);

    hidden = shouldHideIndustrialTopActions(INDUSTRIAL_HEADER_HIDE_THRESHOLD, hidden);
    assert.equal(hidden, true);
    assert.equal(shouldHideIndustrialTopActions(500, hidden), true);
    assert.equal(shouldHideIndustrialTopActions(200, hidden), true);
    assert.equal(shouldHideIndustrialTopActions(20, hidden), true);
    assert.equal(shouldHideIndustrialTopActions(5, hidden), false);
});

test('movement around the hide threshold cannot show hidden actions', () => {
    let hidden = false;

    for (const scrollY of [105, 106, 104, 107, 100, 105, 99, 106]) {
        hidden = shouldHideIndustrialTopActions(scrollY, hidden);
    }

    assert.equal(hidden, true);
});

test('initial state at a restored deep scroll position is hidden', () => {
    assert.equal(shouldHideIndustrialTopActions(400, false), true);
});
