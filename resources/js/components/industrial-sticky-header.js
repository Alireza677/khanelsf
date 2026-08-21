export const INDUSTRIAL_HEADER_HIDE_THRESHOLD = 106;
export const INDUSTRIAL_HEADER_SHOW_THRESHOLD = 5;

export const shouldHideIndustrialTopActions = (scrollY, currentlyHidden) => {
    const normalizedScrollY = Math.max(Number(scrollY) || 0, 0);

    if (normalizedScrollY <= INDUSTRIAL_HEADER_SHOW_THRESHOLD) {
        return false;
    }

    if (normalizedScrollY >= INDUSTRIAL_HEADER_HIDE_THRESHOLD) {
        return true;
    }

    return currentlyHidden;
};

export const initIndustrialStickyHeader = () => {
    document.querySelectorAll('.industrial-header:not(.industrial-header--static)').forEach((header) => {
        if (
            header.dataset.stickyActionsInitialized === 'true'
            || ! header.querySelector('.industrial-header__top-actions')
        ) {
            return;
        }

        header.dataset.stickyActionsInitialized = 'true';

        let hidden = header.classList.contains('is-top-actions-hidden');
        let scrollFrame;

        const update = () => {
            const nextHidden = shouldHideIndustrialTopActions(window.scrollY, hidden);

            if (nextHidden !== hidden) {
                hidden = nextHidden;
                header.classList.toggle('is-top-actions-hidden', hidden);
            }

            scrollFrame = undefined;
        };

        const scheduleUpdate = () => {
            if (scrollFrame !== undefined) {
                return;
            }

            scrollFrame = requestAnimationFrame(update);
        };

        // Apply restored scroll state as soon as the header exists, then resync when
        // the browser restores history/session scroll after DOMContentLoaded.
        update();
        window.addEventListener('scroll', scheduleUpdate, { passive: true });
        window.addEventListener('pageshow', scheduleUpdate);
        window.addEventListener('load', scheduleUpdate, { once: true });
    });
};
