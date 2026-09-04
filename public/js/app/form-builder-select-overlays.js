const SCOPE_SELECTOR = '[data-form-builder-select-overlays]';
const DROPDOWN_SELECTOR = ':scope > .choices__list--dropdown, :scope > .choices__list[aria-expanded]';
const EDGE_GAP = 8;

const clamp = (value, minimum, maximum) => Math.min(Math.max(value, minimum), maximum);

export const calculateSelectOverlayPosition = ({
    triggerRect,
    dropdownHeight,
    viewport = {
        top: 0,
        left: 0,
        right: window.innerWidth,
        bottom: window.innerHeight,
    },
    gap = EDGE_GAP,
}) => {
    const availableBelow = Math.max(0, viewport.bottom - triggerRect.bottom - gap - EDGE_GAP);
    const availableAbove = Math.max(0, triggerRect.top - viewport.top - gap - EDGE_GAP);
    const placement = dropdownHeight <= availableBelow || availableBelow >= availableAbove
        ? 'bottom'
        : 'top';
    const availableHeight = placement === 'bottom' ? availableBelow : availableAbove;
    const renderedHeight = Math.min(dropdownHeight, availableHeight);
    const maximumWidth = Math.max(0, viewport.right - viewport.left - (EDGE_GAP * 2));
    const width = Math.min(triggerRect.width, maximumWidth);
    const left = clamp(
        triggerRect.left,
        viewport.left + EDGE_GAP,
        Math.max(viewport.left + EDGE_GAP, viewport.right - EDGE_GAP - width),
    );
    const top = placement === 'bottom'
        ? triggerRect.bottom + gap
        : triggerRect.top - gap - renderedHeight;

    return {
        availableHeight,
        left,
        placement,
        top,
        width,
    };
};

const currentViewport = () => {
    const visualViewport = window.visualViewport;

    if (! visualViewport) {
        return {
            top: 0,
            left: 0,
            right: window.innerWidth,
            bottom: window.innerHeight,
        };
    }

    return {
        top: visualViewport.offsetTop,
        left: visualViewport.offsetLeft,
        right: visualViewport.offsetLeft + visualViewport.width,
        bottom: visualViewport.offsetTop + visualViewport.height,
    };
};

export const initFormBuilderSelectOverlays = () => {
    if (window.__formBuilderSelectOverlaysInitialized) {
        return;
    }

    window.__formBuilderSelectOverlaysInitialized = true;

    let activeOverlay = null;
    let positionFrame = null;
    const supportsPopover = typeof HTMLElement.prototype.showPopover === 'function';

    const cancelScheduledPosition = () => {
        if (positionFrame === null) {
            return;
        }

        window.cancelAnimationFrame(positionFrame);
        positionFrame = null;
    };

    const resetOverlay = (overlay) => {
        if (! overlay) {
            return;
        }

        const { dropdown, list } = overlay;

        if (supportsPopover && dropdown.matches(':popover-open')) {
            dropdown.hidePopover();
        }

        dropdown.classList.remove('form-builder-select-overlay');
        dropdown.removeAttribute('data-form-builder-select-overlay');
        dropdown.removeAttribute('data-placement');
        dropdown.removeAttribute('popover');
        dropdown.style.removeProperty('--form-builder-select-overlay-width');
        dropdown.style.removeProperty('--form-builder-select-overlay-max-height');
        dropdown.style.removeProperty('--form-builder-select-overlay-list-max-height');
        dropdown.style.removeProperty('--form-builder-select-overlay-direction');
        dropdown.style.removeProperty('left');
        dropdown.style.removeProperty('top');
        list?.style.removeProperty('max-height');
    };

    const closeActiveOverlay = (select = null) => {
        if (! activeOverlay || (select && activeOverlay.select !== select)) {
            return;
        }

        cancelScheduledPosition();
        resetOverlay(activeOverlay);
        activeOverlay = null;
    };

    const positionActiveOverlay = () => {
        positionFrame = null;

        if (! activeOverlay) {
            return;
        }

        const { choices, dropdown, list, select } = activeOverlay;

        if (! choices.isConnected || ! dropdown.isConnected || ! select.isConnected) {
            closeActiveOverlay();

            return;
        }

        const triggerRect = choices.getBoundingClientRect();
        const viewport = currentViewport();
        const direction = window.getComputedStyle(choices).direction || 'rtl';

        dropdown.style.setProperty('--form-builder-select-overlay-width', `${triggerRect.width}px`);
        dropdown.style.setProperty('--form-builder-select-overlay-direction', direction);
        dropdown.style.removeProperty('--form-builder-select-overlay-max-height');
        dropdown.style.removeProperty('--form-builder-select-overlay-list-max-height');

        const dropdownHeight = dropdown.getBoundingClientRect().height;
        const position = calculateSelectOverlayPosition({ triggerRect, dropdownHeight, viewport });
        const chromeHeight = list
            ? Math.max(0, dropdown.getBoundingClientRect().height - list.getBoundingClientRect().height)
            : 0;
        const listHeight = Math.max(0, Math.min(240, position.availableHeight - chromeHeight));

        dropdown.style.setProperty('--form-builder-select-overlay-max-height', `${position.availableHeight}px`);
        dropdown.style.setProperty('--form-builder-select-overlay-list-max-height', `${listHeight}px`);

        const renderedHeight = Math.min(
            dropdown.getBoundingClientRect().height,
            position.availableHeight,
        );
        const top = position.placement === 'top'
            ? triggerRect.top - EDGE_GAP - renderedHeight
            : position.top;

        dropdown.dataset.placement = position.placement;
        dropdown.style.setProperty('left', `${position.left}px`, 'important');
        dropdown.style.setProperty('top', `${Math.max(viewport.top + EDGE_GAP, top)}px`, 'important');
    };

    const schedulePosition = () => {
        if (! activeOverlay || positionFrame !== null) {
            return;
        }

        positionFrame = window.requestAnimationFrame(positionActiveOverlay);
    };

    const openOverlay = (select) => {
        const scope = select.closest(SCOPE_SELECTOR);
        const choices = select.closest('.choices');
        const dropdown = choices?.querySelector(DROPDOWN_SELECTOR);

        if (! scope || ! choices || ! dropdown) {
            return;
        }

        if (activeOverlay?.dropdown !== dropdown) {
            closeActiveOverlay();
        }

        const list = dropdown.querySelector(':scope > .choices__list');
        activeOverlay = { choices, dropdown, list, select };

        dropdown.classList.add('form-builder-select-overlay');
        dropdown.setAttribute('data-form-builder-select-overlay', '');

        if (supportsPopover) {
            dropdown.setAttribute('popover', 'manual');

            if (! dropdown.matches(':popover-open')) {
                try {
                    dropdown.showPopover();
                } catch {
                    // Fixed positioning still works as a fallback when a browser
                    // exposes an incomplete Popover API implementation.
                    dropdown.removeAttribute('popover');
                }
            }
        }

        schedulePosition();
    };

    document.addEventListener('showDropdown', (event) => {
        if (event.target instanceof HTMLSelectElement) {
            openOverlay(event.target);
        }
    });

    document.addEventListener('hideDropdown', (event) => {
        if (event.target instanceof HTMLSelectElement) {
            closeActiveOverlay(event.target);
        }
    });

    window.addEventListener('resize', schedulePosition, { passive: true });
    document.addEventListener('scroll', schedulePosition, { capture: true, passive: true });
    window.visualViewport?.addEventListener('resize', schedulePosition, { passive: true });
    window.visualViewport?.addEventListener('scroll', schedulePosition, { passive: true });
    document.addEventListener('livewire:navigating', () => closeActiveOverlay());

    new MutationObserver(() => {
        if (activeOverlay && (! activeOverlay.select.isConnected || ! activeOverlay.dropdown.isConnected)) {
            closeActiveOverlay();
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
};

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
    initFormBuilderSelectOverlays();
}
