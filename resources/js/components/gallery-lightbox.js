// Shared by Product, Project and Service; existing trigger attributes remain valid.
export const initGalleryLightbox = () => {
    if (window.__galleryLightboxInitialized) return;
    window.__galleryLightboxInitialized = true;
    let overlay = null;
    let opener = null;
    let items = [];
    let index = 0;
    let inertElements = [];
    const close = (restoreFocus = true) => {
        if (!overlay) return;
        overlay.remove();
        overlay = null;
        document.body.classList.remove('gallery-lightbox-open');
        inertElements.forEach(({ element, inert }) => { element.inert = inert; });
        inertElements = [];
        if (restoreFocus && opener?.isConnected) opener.focus();
    };
    const render = () => {
        const image = overlay.querySelector('img');
        image.hidden = false;
        overlay.querySelector('[data-lightbox-error]').hidden = true;
        image.src = items[index].src;
        image.alt = items[index].alt;
        overlay.querySelector('[data-lightbox-status]').textContent = `${index + 1} / ${items.length}`;
    };
    const move = (step) => { index = (index + step + items.length) % items.length; render(); };
    const open = (trigger) => {
        close(false);
        opener = trigger;
        const gallery = trigger.closest('[data-product-gallery]');
        const thumbnails = gallery ? [...gallery.querySelectorAll('[data-product-thumbnail]')] : [];
        items = thumbnails.length ? thumbnails.map((item) => ({ src: item.href, alt: item.dataset.imageAlt || '' }))
            : [{ src: trigger.getAttribute('data-gallery-lightbox-src'), alt: trigger.getAttribute('data-gallery-lightbox-alt') || '' }];
        index = Math.max(0, items.findIndex((item) => item.src === trigger.href || item.src === trigger.getAttribute('data-gallery-lightbox-src')));
        overlay = document.createElement('div');
        overlay.className = 'gallery-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'پیش‌نمایش تصاویر');
        overlay.dir = 'rtl';
        overlay.innerHTML = '<button type="button" class="gallery-lightbox__close" aria-label="بستن پیش‌نمایش تصویر">×</button><button type="button" class="gallery-lightbox__previous" aria-label="تصویر قبلی">›</button><img alt=""><button type="button" class="gallery-lightbox__next" aria-label="تصویر بعدی">‹</button><span class="gallery-lightbox__status" data-lightbox-status role="status"></span><p class="gallery-lightbox__error" data-lightbox-error role="status" hidden>تصویر بارگیری نشد.</p>';
        const image = overlay.querySelector('img');
        image.addEventListener('error', () => {
            if (overlay?.querySelector('img') !== image) return;
            image.hidden = true;
            overlay.querySelector('[data-lightbox-error]').hidden = false;
        });
        document.body.appendChild(overlay);
        inertElements = [...document.body.children].filter((element) => element !== overlay && !['SCRIPT', 'STYLE'].includes(element.tagName))
            .map((element) => { const inert = element.inert; element.inert = true; return { element, inert }; });
        document.body.classList.add('gallery-lightbox-open');
        overlay.querySelector('.gallery-lightbox__close').addEventListener('click', () => close());
        const previous = overlay.querySelector('.gallery-lightbox__previous');
        const next = overlay.querySelector('.gallery-lightbox__next');
        previous.hidden = next.hidden = items.length < 2;
        previous.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
        render();
        overlay.querySelector('.gallery-lightbox__close').focus();
    };
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-gallery-lightbox-src]');
        if (trigger && !event.defaultPrevented) { event.preventDefault(); open(trigger); }
    });
    document.addEventListener('keydown', (event) => {
        if (!overlay) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (['ArrowLeft', 'ArrowRight'].includes(event.key) && items.length > 1) { event.preventDefault(); move(event.key === 'ArrowLeft' ? 1 : -1); }
        if (event.key === 'Tab') {
            const focusable = [...overlay.querySelectorAll('button')].filter((element) => !element.hidden && !element.disabled);
            const first = focusable[0];
            const last = focusable.at(-1);
            if (!overlay.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
};
