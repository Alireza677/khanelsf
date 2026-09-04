import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

import { calculateSelectOverlayPosition } from '../../resources/js/filament/form-builder-select-overlays.js';

const viewport = { top: 0, left: 0, right: 1200, bottom: 800 };
const source = await readFile(
    new URL('../../resources/js/filament/form-builder-select-overlays.js', import.meta.url),
    'utf8',
);

test('select overlay opens below when the dropdown fits', () => {
    const position = calculateSelectOverlayPosition({
        triggerRect: { top: 100, right: 700, bottom: 140, left: 400, width: 300 },
        dropdownHeight: 240,
        viewport,
    });

    assert.equal(position.placement, 'bottom');
    assert.equal(position.top, 148);
    assert.equal(position.left, 400);
    assert.equal(position.width, 300);
});

test('select overlay flips above when there is not enough room below', () => {
    const position = calculateSelectOverlayPosition({
        triggerRect: { top: 650, right: 700, bottom: 690, left: 400, width: 300 },
        dropdownHeight: 240,
        viewport,
    });

    assert.equal(position.placement, 'top');
    assert.equal(position.top, 402);
    assert.equal(position.availableHeight, 634);
});

test('select overlay stays inside a narrow mobile viewport', () => {
    const position = calculateSelectOverlayPosition({
        triggerRect: { top: 200, right: 390, bottom: 244, left: 20, width: 370 },
        dropdownHeight: 300,
        viewport: { top: 0, left: 0, right: 360, bottom: 640 },
    });

    assert.equal(position.placement, 'bottom');
    assert.equal(position.left, 8);
    assert.equal(position.width, 344);
});

test('shared builder overlay uses the top layer and tracks scrolling viewports', () => {
    assert.match(source, /popover', 'manual'/);
    assert.match(source, /dropdown\.showPopover\(\)/);
    assert.match(source, /dropdown\.hidePopover\(\)/);
    assert.match(source, /document\.addEventListener\('scroll', schedulePosition, \{ capture: true/);
    assert.match(source, /window\.visualViewport\?\.addEventListener\('resize'/);
    assert.match(source, /setProperty\('top',[\s\S]*'important'\)/);
    assert.match(source, /\[data-form-builder-select-overlays\]/);
});
