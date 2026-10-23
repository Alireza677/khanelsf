export const normalizePersianSearch = (value) => String(value).normalize('NFKC')
    .replace(/ي|ى/g, 'ی').replace(/ك/g, 'ک').replace(/[\u064B-\u065F\u0670\u0640]/g, '')
    .replace(/[\s\u200c\u200d]+/g, '').toLocaleLowerCase('fa');

const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));

export const calculateFormSelectPosition = ({ triggerRect, viewport, modalRect, desiredHeight = 360 }) => {
    const gap = 8;
    const below = Math.max(0, viewport.bottom - triggerRect.bottom - gap * 2);
    const above = Math.max(0, triggerRect.top - viewport.top - gap * 2);
    const centered = viewport.right - viewport.left <= 640 || Math.max(below, above) < Math.min(180, desiredHeight);
    const bounds = centered && modalRect ? {
        left: Math.max(viewport.left, modalRect.left), right: Math.min(viewport.right, modalRect.right),
        top: Math.max(viewport.top, modalRect.top), bottom: Math.min(viewport.bottom, modalRect.bottom),
    } : viewport;
    const availableWidth = Math.max(1, bounds.right - bounds.left - 24);
    const width = Math.min(centered ? Math.max(triggerRect.width, 360) : triggerRect.width, availableWidth);
    const placement = centered ? 'center' : below >= Math.min(desiredHeight, 360) || below >= above ? 'bottom' : 'top';
    const maxHeight = Math.max(1, Math.min(centered ? 420 : 360,
        centered ? bounds.bottom - bounds.top - 24 : placement === 'bottom' ? below : above));
    const height = Math.min(desiredHeight, maxHeight);
    return {
        placement, width, maxHeight,
        left: centered ? bounds.left + (bounds.right - bounds.left - width) / 2
            : clamp(triggerRect.left, viewport.left + 12, viewport.right - width - 12),
        top: centered ? bounds.top + (bounds.bottom - bounds.top - height) / 2
            : placement === 'bottom' ? triggerRect.bottom + gap : triggerRect.top - gap - height,
    };
};

const instances = new Map();
let active = null;
let frame = null;
let installed = false;
let sequence = 0;

const viewport = () => {
    const visual = window.visualViewport;
    return { left: visual?.offsetLeft || 0, top: visual?.offsetTop || 0,
        right: (visual?.offsetLeft || 0) + (visual?.width || window.innerWidth),
        bottom: (visual?.offsetTop || 0) + (visual?.height || window.innerHeight) };
};

const closeActive = (restoreFocus = false) => {
    if (! active) return;
    const state = active;
    active = null;
    if (frame !== null) { cancelAnimationFrame(frame); frame = null; }
    if (state.host.hasAttribute('popover') && typeof state.host.hidePopover === 'function') {
        try { state.host.hidePopover(); } catch { /* A removed modal may already have dismissed its top layer. */ }
    }
    state.panel.hidden = true;
    state.root.append(state.panel);
    state.host.remove();
    state.root.classList.remove('is-open');
    state.trigger.setAttribute('aria-expanded', 'false');
    state.search.setAttribute('aria-expanded', 'false');
    state.trigger.removeAttribute('aria-activedescendant');
    state.search.removeAttribute('aria-activedescendant');
    if (restoreFocus && state.trigger.isConnected) state.trigger.focus({ preventScroll: true });
};

const positionActive = () => {
    frame = null;
    if (! active) return;
    const state = active;
    if (! state.root.isConnected || ! state.trigger.getClientRects().length || state.native.disabled) {
        closeActive();
        return;
    }
    const rect = state.trigger.getBoundingClientRect();
    const modal = state.root.closest('.form-action-modal__panel, .fi-modal-window, dialog, [role="dialog"]');
    const view = viewport();
    const desiredHeight = Math.min(360, state.results.scrollHeight + state.search.offsetHeight + 70);
    const position = calculateFormSelectPosition({ triggerRect: rect, viewport: view,
        modalRect: modal?.getBoundingClientRect(), desiredHeight });
    state.centered = position.placement === 'center';
    state.host.classList.toggle('is-centered', state.centered);
    state.panel.setAttribute('aria-modal', state.centered ? 'true' : 'false');
    state.panel.dataset.placement = position.placement;
    Object.assign(state.panel.style, { width: `${position.width}px`, maxHeight: `${position.maxHeight}px`,
        left: `${position.left}px`, top: `${position.top}px` });
    // Use the rendered height for short lists and upward/centered placement.
    const height = state.panel.getBoundingClientRect().height;
    if (position.placement === 'top') state.panel.style.top = `${rect.top - 8 - height}px`;
    if (state.centered) state.panel.style.top = `${position.top + (Math.min(desiredHeight, position.maxHeight) - height) / 2}px`;
};

const schedulePosition = () => {
    if (active && frame === null) frame = requestAnimationFrame(positionActive);
};

const tabFromTrigger = (state, backwards) => {
    const scope = state.root.closest('[role="dialog"], dialog, .fi-modal-window') || document;
    const controls = Array.from(scope.querySelectorAll('a[href], button, input, select, textarea, [tabindex]'))
        .filter((node) => node.tabIndex >= 0 && ! node.disabled && node.getClientRects().length && ! state.host.contains(node));
    const index = controls.indexOf(state.trigger);
    const next = controls[index + (backwards ? -1 : 1)];
    closeActive();
    (next || (scope !== document ? controls[backwards ? controls.length - 1 : 0] : state.trigger))?.focus();
};

const initialize = (root) => {
    if (instances.has(root)) return;
    const native = root.querySelector('[data-form-select-native]');
    if (! native) return;
    native.id ||= `form-select-${++sequence}`;
    const label = native.getAttribute('aria-label') || document.getElementById(`${native.id}-label`)?.textContent
        || root.previousElementSibling?.textContent || 'انتخاب گزینه';
    let trigger = root.querySelector('[data-form-select-trigger]');
    if (! trigger) {
        trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'form-select__trigger';
        trigger.setAttribute('data-form-select-trigger', '');
        trigger.innerHTML = '<span data-form-select-value></span><span class="form-select__chevron" aria-hidden="true"></span>';
        trigger.setAttribute('aria-label', label.trim());
        root.append(trigger);
    }
    let panel = root.querySelector('[data-form-select-listbox]');
    if (! panel) {
        panel = document.createElement('div');
        panel.className = 'form-select__panel';
        panel.setAttribute('data-form-select-listbox', '');
        root.append(panel);
    }
    let results = panel.querySelector('[data-form-select-options]');
    if (! results) {
        results = document.createElement('div');
        results.id = panel.id || `${native.id}-listbox`;
        results.append(...panel.querySelectorAll('[data-form-select-option]'));
        panel.removeAttribute('id');
        panel.append(results);
    }
    results.classList.add('form-select__options');
    results.setAttribute('data-form-select-options', '');
    results.setAttribute('role', 'listbox');
    results.setAttribute('aria-label', label.trim());
    results.id ||= `${native.id}-listbox`;
    panel.removeAttribute('aria-labelledby');
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', label.trim());
    panel.hidden = true;
    let search = panel.querySelector('[data-form-select-search]');
    if (! search) {
        search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-select__search';
        search.setAttribute('data-form-select-search', '');
        search.placeholder = 'جست‌وجو…';
        search.setAttribute('aria-label', `جست‌وجو در ${label.trim()}`);
        panel.prepend(search);
    }
    search.autocomplete = 'off';
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', results.id);
    search.setAttribute('aria-expanded', 'false');
    const heading = document.createElement('div');
    heading.className = 'form-select__heading';
    const title = document.createElement('span');
    title.textContent = label.trim();
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'form-select__dismiss';
    dismiss.textContent = '×';
    dismiss.setAttribute('aria-label', 'بستن فهرست');
    dismiss.addEventListener('click', () => closeActive(true));
    heading.append(title, dismiss);
    panel.prepend(heading);
    let empty = panel.querySelector('[data-form-select-empty]');
    if (! empty) {
        empty = document.createElement('p');
        empty.className = 'form-select__empty';
        empty.textContent = 'موردی پیدا نشد.';
        empty.setAttribute('role', 'status');
        panel.append(empty);
    }
    const state = { root, native, trigger, panel, results, search, dismiss, empty, options: [], visible: [], index: 0 };
    const setActive = (index, reveal = false) => {
        state.index = state.visible.length ? (index + state.visible.length) % state.visible.length : -1;
        const chosen = state.visible[state.index];
        state.options.forEach((option) => option.classList.toggle('is-active', option === chosen));
        for (const control of [trigger, search]) {
            if (chosen) control.setAttribute('aria-activedescendant', chosen.id);
            else control.removeAttribute('aria-activedescendant');
        }
        if (reveal && chosen) {
            const item = chosen.getBoundingClientRect();
            const box = results.getBoundingClientRect();
            if (item.top < box.top) results.scrollTop -= box.top - item.top;
            else if (item.bottom > box.bottom) results.scrollTop += item.bottom - box.bottom;
        }
    };
    const filter = () => {
        const query = normalizePersianSearch(search.value);
        state.options.forEach((option) => { option.hidden = query !== '' && ! normalizePersianSearch(option.textContent).includes(query); });
        state.visible = state.options.filter((option) => ! option.hidden && ! option.disabled);
        empty.hidden = state.visible.length > 0;
        setActive(0);
        if (active === state) schedulePosition();
    };
    const sync = () => {
        trigger.disabled = native.disabled;
        const selectedText = native.selectedOptions[0]?.textContent || 'انتخاب کنید';
        root.querySelector('[data-form-select-value]').textContent = selectedText;
        if (! trigger.hasAttribute('aria-labelledby')) trigger.setAttribute('aria-label', `${label.trim()}: ${selectedText}`);
        trigger.classList.toggle('is-placeholder', native.value === '');
        trigger.setAttribute('aria-invalid', native.getAttribute('aria-invalid') || 'false');
        if (native.getAttribute('aria-describedby')) trigger.setAttribute('aria-describedby', native.getAttribute('aria-describedby'));
        state.options.forEach((option) => option.setAttribute('aria-selected', option.dataset.value === native.value ? 'true' : 'false'));
        setActive(Math.max(0, state.visible.findIndex((option) => option.dataset.value === native.value)));
        if (native.disabled && active === state) closeActive();
    };
    const rebuild = () => {
        results.replaceChildren(...Array.from(native.options, (item, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'form-select__option';
            option.id = `${results.id}-option-${index}`;
            option.tabIndex = -1;
            option.disabled = item.disabled || item.parentElement?.disabled === true;
            option.setAttribute('role', 'option');
            option.setAttribute('data-form-select-option', '');
            option.dataset.value = item.value;
            option.textContent = item.textContent;
            return option;
        }));
        state.options = Array.from(results.children);
        filter();
        sync();
    };
    const choose = (option) => {
        if (! option || option.disabled || native.disabled) return;
        native.value = option.dataset.value;
        native.dispatchEvent(new Event('input', { bubbles: true }));
        native.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
        closeActive(true);
    };
    const open = () => {
        if (native.disabled) return;
        closeActive();
        window.dispatchEvent(new CustomEvent('form-select:opening', { detail: { root } }));
        const host = document.createElement('div');
        host.className = 'form-select__overlay';
        const computed = getComputedStyle(root);
        host.dir = computed.direction;
        host.style.fontFamily = computed.fontFamily;
        host.style.fontSize = computed.fontSize;
        host.style.lineHeight = computed.lineHeight;
        for (const variable of ['--theme-primary', '--theme-text', '--theme-button-radius', '--theme-button-radius-mobile']) {
            host.style.setProperty(variable, computed.getPropertyValue(variable));
        }
        const scrim = document.createElement('div');
        scrim.className = 'form-select__backdrop';
        scrim.setAttribute('aria-hidden', 'true');
        host.append(scrim, panel);
        (root.closest('dialog[open]') || document.body).append(host);
        state.host = host;
        active = state;
        host.addEventListener('wheel', (event) => {
            if (! results.contains(event.target)) event.preventDefault();
        }, { passive: false });
        // Top layer escapes clipping/stacking contexts; body portal is the fallback.
        if (typeof host.showPopover === 'function') {
            host.setAttribute('popover', 'manual');
            try { host.showPopover(); } catch { host.removeAttribute('popover'); }
        }
        panel.hidden = false;
        root.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        search.setAttribute('aria-expanded', 'true');
        search.value = '';
        filter();
        sync();
        positionActive();
        search.focus({ preventScroll: true });
        setActive(state.index, true);
    };
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-controls', results.id);
    trigger.setAttribute('aria-expanded', 'false');
    trigger.addEventListener('click', () => active === state ? closeActive(true) : open());
    trigger.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open();
        }
    });
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter'].includes(event.key)) {
            event.preventDefault();
            event.stopPropagation();
            if (event.key === 'Enter') choose(state.visible[state.index]);
            else setActive(event.key === 'Home' ? 0 : event.key === 'End' ? state.visible.length - 1
                : state.index + (event.key === 'ArrowDown' ? 1 : -1), true);
        }
    });
    results.addEventListener('click', (event) => choose(event.target.closest('[data-form-select-option]')));
    results.addEventListener('pointerover', (event) => {
        const index = state.visible.indexOf(event.target.closest('[data-form-select-option]'));
        if (index >= 0) setActive(index);
    });
    native.addEventListener('change', sync);
    native.addEventListener('form-select:refresh', () => { search.value = ''; rebuild(); });
    native.addEventListener('invalid', (event) => {
        event.preventDefault();
        trigger.setAttribute('aria-invalid', 'true');
        trigger.focus({ preventScroll: true });
    });
    state.form = native.form;
    state.onReset = () => queueMicrotask(sync);
    state.form?.addEventListener('reset', state.onReset);
    state.labels = Array.from(native.labels || []);
    state.onLabelClick = (event) => { event.preventDefault(); open(); };
    state.labels.forEach((label) => label.addEventListener('click', state.onLabelClick));
    const observer = new MutationObserver(rebuild);
    observer.observe(native, { childList: true, subtree: true, characterData: true, attributes: true,
        attributeFilter: ['disabled', 'selected', 'value', 'aria-invalid', 'aria-describedby'] });
    state.observer = observer;
    state.resizeObserver = typeof ResizeObserver === 'function' ? new ResizeObserver(schedulePosition) : null;
    state.resizeObserver?.observe(trigger);
    state.resizeObserver?.observe(panel);
    native.tabIndex = -1;
    native.setAttribute('aria-hidden', 'true');
    root.dataset.formSelectReady = 'true';
    root.classList.add('is-enhanced');
    instances.set(root, state);
    rebuild();
};

export const initFormSelects = (scope = document) => {
    if (scope.matches?.('[data-form-select]')) initialize(scope);
    scope.querySelectorAll('[data-form-select]').forEach(initialize);
    if (installed) return;
    installed = true;
    document.addEventListener('click', (event) => {
        if (! active || active.panel.contains(event.target) || active.root.contains(event.target)) return;
        const centered = active.centered;
        if (centered) { event.preventDefault(); event.stopPropagation(); }
        closeActive(centered);
    }, true);
    document.addEventListener('keydown', (event) => {
        if (! active) return;
        if (event.key === 'Escape') {
            event.preventDefault(); event.stopImmediatePropagation(); closeActive(true);
        } else if (event.key === 'Tab') {
            event.preventDefault();
            event.stopPropagation();
            if (active.centered) {
                (document.activeElement === active.search ? active.dismiss : active.search).focus();
            } else tabFromTrigger(active, event.shiftKey);
        }
    }, true);
    window.addEventListener('resize', schedulePosition, { passive: true });
    window.visualViewport?.addEventListener('resize', schedulePosition, { passive: true });
    window.visualViewport?.addEventListener('scroll', schedulePosition, { passive: true });
    document.addEventListener('scroll', (event) => {
        if (active && ! active.host.contains(event.target)) schedulePosition();
    }, { capture: true, passive: true });
    window.addEventListener('form-select:opening', (event) => {
        if (active && active.root !== event.detail.root) closeActive();
    });
    document.addEventListener('livewire:navigating', () => closeActive());
    window.addEventListener('pagehide', () => closeActive());
    new MutationObserver((records) => {
        for (const [root, state] of instances) {
            if (! root.isConnected) {
                if (active === state) closeActive();
                state.observer.disconnect(); state.resizeObserver?.disconnect();
                state.form?.removeEventListener('reset', state.onReset);
                state.labels.forEach((label) => label.removeEventListener('click', state.onLabelClick));
                instances.delete(root);
            }
        }
        if (active && ! active.trigger.getClientRects().length) closeActive();
        for (const record of records) for (const node of record.addedNodes) {
            if (node.nodeType === 1 && node.isConnected && ! node.closest('.form-select__overlay')) initFormSelects(node);
        }
        if (active) schedulePosition();
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
};

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => initFormSelects());
    else initFormSelects();
    document.addEventListener('livewire:navigated', () => initFormSelects());
}
