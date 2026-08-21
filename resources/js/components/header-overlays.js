const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

export const searchScopeSummary = (selectedLabels, total) => {
    if (selectedLabels.length === total) {
        return 'جستجو در همه';
    }

    if (selectedLabels.length === 1) {
        return 'فقط ' + selectedLabels[0];
    }

    return 'جستجو در ' + selectedLabels.length.toLocaleString('fa-IR') + ' بخش';
};

const initSearchScopes = () => {
    document.querySelectorAll('[data-search-scope]').forEach((form) => {
        if (form.dataset.searchScopeInitialized === 'true') {
            return;
        }

        form.dataset.searchScopeInitialized = 'true';
        const selector = form.querySelector('[data-search-scope-selector]');
        const toggle = form.querySelector('[data-search-scope-toggle]');
        const master = form.querySelector('[data-search-scope-all]');
        const summary = form.querySelector('[data-search-scope-summary]');
        const types = Array.from(form.querySelectorAll('[data-search-scope-type]'));

        if (! selector || ! toggle || ! master || ! summary || types.length === 0) {
            return;
        }

        const sync = () => {
            const selected = types.filter((input) => input.checked);
            master.checked = selected.length === types.length;
            master.indeterminate = selected.length > 0 && selected.length < types.length;
            summary.textContent = searchScopeSummary(
                selected.map((input) => input.nextElementSibling.textContent.trim()),
                types.length,
            );
        };

        toggle.addEventListener('click', () => {
            const expanded = ! selector.classList.contains('is-expanded');
            selector.classList.toggle('is-expanded', expanded);
            toggle.setAttribute('aria-expanded', String(expanded));
            toggle.setAttribute('aria-label', expanded
                ? 'بستن محدوده‌های جستجو'
                : 'نمایش محدوده‌های جستجو');
        });

        master.addEventListener('change', () => {
            types.forEach((input) => {
                input.checked = true;
            });
            sync();
        });

        types.forEach((input) => {
            input.addEventListener('change', () => {
                if (! types.some((item) => item.checked)) {
                    input.checked = true;
                }
                sync();
            });
        });

        form.addEventListener('submit', () => {
            const selected = types.filter((input) => input.checked);
            types.forEach((input) => input.removeAttribute('name'));

            if (selected.length < types.length) {
                selected.forEach((input) => input.setAttribute('name', 'types[]'));
            }
        });

        sync();
    });
};

export const syncCartPresentation = (drawer, state, root = document) => {
    const items = drawer.querySelector('[data-cart-items]');
    const emptyState = drawer.querySelector('[data-cart-empty-state]');
    const footer = drawer.querySelector('[data-cart-footer]');
    const subtotalBlock = drawer.querySelector('[data-cart-subtotal-block]');
    const actions = drawer.querySelector('[data-cart-actions]');

    if (state.is_empty) {
        items?.querySelectorAll('[data-cart-item]').forEach((item) => item.remove());
    }

    root.querySelectorAll('[data-cart-trigger]').forEach((trigger) => {
        trigger.setAttribute('aria-label', state.count > 0
            ? 'سبد خرید، ' + state.count + ' کالا'
            : 'سبد خرید');
    });

    root.querySelectorAll('[data-cart-badge]').forEach((badge) => {
        badge.textContent = state.display_count;
        badge.hidden = state.count === 0;
    });

    const subtotal = drawer.querySelector('[data-cart-subtotal]');
    if (subtotal) {
        subtotal.textContent = state.subtotal_formatted;
    }

    items?.toggleAttribute('hidden', state.is_empty);
    emptyState?.toggleAttribute('hidden', ! state.is_empty);
    footer?.toggleAttribute('hidden', state.is_empty);
    subtotalBlock?.toggleAttribute('hidden', state.is_empty);
    actions?.toggleAttribute('hidden', state.is_empty);
};

const initCartDrawerRemovals = () => {
    if (document.documentElement.dataset.cartDrawerRemovalsInitialized === 'true') {
        return;
    }

    document.documentElement.dataset.cartDrawerRemovalsInitialized = 'true';
    let mutationQueue = Promise.resolve();

    const removeRow = async (row) => {
        if (! row) {
            return;
        }

        if (! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            row.classList.add('is-removing');
            await new Promise((resolve) => window.setTimeout(resolve, 170));
        }

        row.remove();
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-cart-drawer-remove]');

        if (! form || ! window.fetch) {
            return;
        }

        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const drawer = form.closest('[data-header-overlay]');
        const row = form.closest('[data-cart-item]');
        const error = drawer?.querySelector('[data-cart-drawer-error]');
        button.disabled = true;
        button.classList.add('is-loading');

        mutationQueue = mutationQueue.then(async () => {
            if (error) {
                error.hidden = true;
                error.textContent = '';
            }

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (! response.ok) {
                    throw new Error('Cart removal failed');
                }

                const state = await response.json();
                if (! state.is_empty) {
                    await removeRow(row);
                }
                syncCartPresentation(drawer, state);

                const nextButton = drawer.querySelector('[data-cart-drawer-remove] button:not([disabled])');
                (nextButton || drawer.querySelector('[data-cart-empty-state]:not([hidden]) a')
                    || drawer.querySelector('[data-header-overlay-panel]'))?.focus();
            } catch {
                button.disabled = false;
                button.classList.remove('is-loading');

                if (error) {
                    error.textContent = 'حذف محصول انجام نشد. لطفاً دوباره تلاش کنید.';
                    error.hidden = false;
                    error.focus?.();
                }
            }
        });
    });
};

export const initHeaderOverlays = () => {
    initSearchScopes();
    initCartDrawerRemovals();

    if (document.documentElement.dataset.headerOverlaysInitialized === 'true') {
        return;
    }

    const overlays = new Map(
        Array.from(document.querySelectorAll('[data-header-overlay]'))
            .map((overlay) => [overlay.id, overlay]),
    );

    if (overlays.size === 0) {
        return;
    }

    document.documentElement.dataset.headerOverlaysInitialized = 'true';
    let activeOverlay = null;
    let returnFocus = null;
    let inertElements = [];

    const restoreBackground = () => {
        inertElements.forEach(({ element, wasInert }) => {
            element.inert = wasInert;
        });
        inertElements = [];
    };

    const close = ({ restoreFocus = true } = {}) => {
        if (! activeOverlay) {
            return;
        }

        const trigger = returnFocus;
        activeOverlay.hidden = true;
        activeOverlay = null;
        returnFocus = null;
        restoreBackground();
        document.body.classList.remove('header-overlay-open');
        document.querySelectorAll('[data-header-overlay-trigger][aria-expanded="true"]')
            .forEach((item) => item.setAttribute('aria-expanded', 'false'));

        if (restoreFocus) {
            trigger?.focus();
        }
    };

    const open = (overlay, trigger) => {
        if (activeOverlay) {
            close({ restoreFocus: false });
        }

        document.querySelectorAll('[data-public-account-controls] details[open]')
            .forEach((details) => details.removeAttribute('open'));

        activeOverlay = overlay;
        returnFocus = trigger;
        overlay.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('header-overlay-open');
        inertElements = Array.from(document.body.children)
            .filter((element) => element !== overlay && element.tagName !== 'SCRIPT')
            .map((element) => {
                const wasInert = element.inert;
                element.inert = true;
                return { element, wasInert };
            });

        requestAnimationFrame(() => {
            const autofocus = overlay.querySelector('[data-header-overlay-autofocus]');
            (autofocus || overlay.querySelector('[data-header-overlay-panel]'))?.focus();
        });
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-header-overlay-trigger]');

        if (trigger) {
            const overlay = overlays.get(trigger.dataset.headerOverlayTrigger);

            if (overlay) {
                event.preventDefault();
                open(overlay, trigger);
            }

            return;
        }

        if (activeOverlay && event.target.closest('[data-header-overlay-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (! activeOverlay) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(activeOverlay.querySelectorAll(focusableSelector))
            .filter((element) => ! element.hidden);

        if (focusable.length === 0) {
            event.preventDefault();
            activeOverlay.querySelector('[data-header-overlay-panel]')?.focus();
            return;
        }

        const first = focusable[0];
        const last = focusable.at(-1);

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
};
