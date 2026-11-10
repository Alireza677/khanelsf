export const initProductStandard = () => {
    document.querySelectorAll('[data-product-summary]').forEach((root) => {
        if (root.dataset.productReady) return;
        root.dataset.productReady = 'true';
        const gallery = root.querySelector('[data-product-gallery]');
        const main = gallery?.querySelector('[data-product-main-image]');
        const image = main?.querySelector('img');
        const error = gallery?.querySelector('[data-product-image-error]');
        const thumbnails = [...(gallery?.querySelectorAll('[data-product-thumbnail]') || [])];
        if (image) {
            const updateImageState = (failed) => {
                image.hidden = failed;
                if (error) error.hidden = !failed;
                main.setAttribute('aria-busy', 'false');
            };
            image.addEventListener('load', () => updateImageState(false));
            image.addEventListener('error', () => updateImageState(true));
            if (image.complete) updateImageState(image.naturalWidth === 0);
            thumbnails.forEach((thumbnail) => thumbnail.addEventListener('click', (event) => {
                event.preventDefault();
                const src = thumbnail.href;
                image.hidden = false;
                if (error) error.hidden = true;
                main.setAttribute('aria-busy', 'true');
                image.src = src;
                image.alt = thumbnail.dataset.imageAlt || '';
                main.href = src;
                main.dataset.galleryLightboxSrc = src;
                main.dataset.galleryLightboxAlt = image.alt;
                thumbnails.forEach((item) => {
                    if (item === thumbnail) item.setAttribute('aria-current', 'true');
                    else item.removeAttribute('aria-current');
                });
            }));
        }
        root.querySelectorAll('[data-product-quantity]').forEach((control) => {
            const input = control.querySelector('input');
            const decrease = control.querySelector('[data-quantity-decrease]');
            const increase = control.querySelector('[data-quantity-increase]');
            const sync = () => {
                const value = input.valueAsNumber;
                decrease.disabled = !Number.isInteger(value) || value <= 1;
                increase.disabled = !Number.isInteger(value) || value >= 99;
                input.setCustomValidity(input.value && (!Number.isInteger(value) || value < 1 || value > 99) ? 'تعداد باید عدد صحیح از ۱ تا ۹۹ باشد.' : '');
            };
            [decrease, increase].forEach((button) => { button.hidden = false; });
            decrease.addEventListener('click', () => { input.stepDown(); sync(); });
            increase.addEventListener('click', () => { input.stepUp(); sync(); });
            input.addEventListener('input', sync);
            sync();
        });
        root.querySelectorAll('[data-product-share]').forEach((button) => {
            button.hidden = false;
            const status = root.querySelector('[data-product-share-status]');
            const fallback = root.querySelector('[data-product-share-fallback]');
            button.addEventListener('click', async () => {
                button.disabled = true;
                status.textContent = '';
                try {
                    if (navigator.share) {
                        await navigator.share({ title: button.dataset.shareTitle, url: button.dataset.shareUrl });
                        status.textContent = 'اشتراک‌گذاری انجام شد.';
                    } else {
                        if (!navigator.clipboard?.writeText) throw new Error('clipboard-unavailable');
                        await navigator.clipboard.writeText(button.dataset.shareUrl);
                        status.textContent = 'آدرس محصول کپی شد.';
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        fallback.hidden = false;
                        const input = fallback.querySelector('input');
                        input.focus();
                        input.select();
                        status.textContent = 'اشتراک‌گذاری خودکار ممکن نشد؛ آدرس محصول را کپی کنید.';
                    }
                } finally {
                    button.disabled = false;
                }
            });
        });
    });
    document.querySelectorAll('[data-product-tabs]').forEach((root) => {
        if (root.dataset.tabsReady) return;
        const tabs = [...root.querySelectorAll('[data-product-tab]')];
        const panels = [...root.querySelectorAll('[data-product-panel]')];
        if (!tabs.length || tabs.length !== panels.length) return;
        root.dataset.tabsReady = 'true';
        const list = root.querySelector('.product-details__tabs');
        list.setAttribute('role', 'tablist');
        tabs.forEach((tab, index) => {
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-controls', panels[index].id);
            panels[index].setAttribute('role', 'tabpanel');
            panels[index].tabIndex = 0;
        });
        const select = (index, focus = false) => {
            tabs.forEach((tab, i) => {
                tab.setAttribute('aria-selected', String(i === index));
                tab.tabIndex = i === index ? 0 : -1;
                panels[i].hidden = i !== index;
            });
            if (focus) {
                tabs[index].focus({ preventScroll: true });
                const tab = tabs[index];
                // Scroll only the tab strip; do not move the whole document or header.
                const tabRect = tab.getBoundingClientRect();
                const listRect = list.getBoundingClientRect();
                if (tabRect.left < listRect.left) list.scrollLeft += tabRect.left - listRect.left;
                if (tabRect.right > listRect.right) list.scrollLeft += tabRect.right - listRect.right;
            }
        };
        const rtl = getComputedStyle(root).direction === 'rtl';
        tabs.forEach((tab, index) => {
            tab.addEventListener('click', (event) => { event.preventDefault(); select(index); });
            tab.addEventListener('keydown', (event) => {
                let next;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (event.key === 'ArrowRight') next = (index + (rtl ? -1 : 1) + tabs.length) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index + (rtl ? 1 : -1) + tabs.length) % tabs.length;
                if (next !== undefined) { event.preventDefault(); select(next, true); }
            });
        });
        const hashIndex = panels.findIndex((panel) => `#${panel.id}` === window.location.hash);
        const preferred = tabs.findIndex((tab) => tab.dataset.productTab === root.dataset.defaultTab);
        select(hashIndex >= 0 ? hashIndex : Math.max(0, preferred));
    });
};
