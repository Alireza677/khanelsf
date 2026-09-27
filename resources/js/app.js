import './bootstrap';
import { initIndustrialStickyHeader } from './components/industrial-sticky-header';
import { initHeaderOverlays } from './components/header-overlays';
import { initFormDatePickers } from './components/form-date-pickers';
import { initFormNumberInputs } from './components/form-number-inputs';
import { initFormPages } from './components/form-page';
import { initProjectGalleryFilters } from './components/project-gallery-filters';

const initJalaliMonthFilters = () => {
    document.querySelectorAll('[data-jalali-month-filter]').forEach((form) => {
        if (form.dataset.jalaliMonthReady === 'true') return;

        const value = form.querySelector('[data-jalali-month-value]');
        const year = form.querySelector('[data-jalali-year]');
        const month = form.querySelector('[data-jalali-month]');
        if (! value || ! year || ! month) return;

        form.dataset.jalaliMonthReady = 'true';
        const submit = () => {
            value.value = `${year.value}-${month.value}`;
            form.submit();
        };
        year.addEventListener('change', submit);
        month.addEventListener('change', submit);
    });
};

if (document.querySelector('[data-hero-dotted-surface]')) {
    import('./components/hero-dotted-surface');
}

const initMobileHeader = () => {
    document.querySelectorAll('[data-site-header]').forEach((header) => {
        if (header.dataset.mobileHeaderInitialized === 'true') {
            return;
        }

        header.dataset.mobileHeaderInitialized = 'true';

        const toggle = header.querySelector('[data-menu-toggle]');
        const nav = header.querySelector('[data-site-nav]');

        if (! toggle || ! nav) {
            return;
        }

        const close = (restoreFocus = false) => {
            const wasOpen = header.classList.contains('is-nav-open');

            header.classList.remove('is-nav-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'باز کردن منوی اصلی');

            nav.querySelectorAll('[data-industrial-submenu-toggle]').forEach((button) => {
                button.setAttribute('aria-expanded', 'false');
                button.parentElement.classList.remove('is-mobile-submenu-open');
            });

            if (wasOpen && header.classList.contains('industrial-header')) {
                document.body.classList.remove('industrial-mobile-menu-open');
            }

            if (restoreFocus) {
                toggle.focus();
            }
        };

        const open = () => {
            header.classList.add('is-nav-open');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'بستن منوی اصلی');

            if (header.classList.contains('industrial-header')) {
                document.body.classList.add('industrial-mobile-menu-open');
            }
        };

        toggle.addEventListener('click', () => {
            if (header.classList.contains('is-nav-open')) {
                close();
            } else {
                open();
            }
        });

        nav.addEventListener('click', (event) => {
            const submenuToggle = event.target.closest('[data-industrial-submenu-toggle]');

            if (submenuToggle && header.classList.contains('industrial-header') && window.innerWidth <= 900) {
                const expanded = submenuToggle.getAttribute('aria-expanded') !== 'true';
                submenuToggle.setAttribute('aria-expanded', String(expanded));
                submenuToggle.parentElement.classList.toggle('is-mobile-submenu-open', expanded);
                return;
            }

            if (event.target.closest('a')) {
                close();
            }
        });

        document.addEventListener('click', (event) => {
            if (! header.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && header.classList.contains('is-nav-open')) {
                close(true);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                close();
            }
        });
    });
};

const initDesktopNavigationOverflow = () => {
    document.querySelectorAll('[data-desktop-navigation]').forEach((navigation) => {
        if (navigation.dataset.desktopOverflowInitialized === 'true') {
            return;
        }

        const more = navigation.querySelector(':scope > [data-navigation-more]');
        const moreTrigger = more?.querySelector('[data-navigation-more-trigger]');
        const moreItems = more?.querySelector('[data-navigation-more-items]');

        if (! more || ! moreTrigger || ! moreItems) {
            return;
        }

        navigation.dataset.desktopOverflowInitialized = 'true';

        const closeMore = (restoreFocus = false) => {
            more.classList.remove('is-open');
            moreTrigger.setAttribute('aria-expanded', 'false');

            moreItems.querySelectorAll('.is-submenu-inline-end').forEach((item) => {
                item.classList.remove('is-submenu-inline-end');
            });

            if (restoreFocus) {
                moreTrigger.focus();
            }
        };

        const restoreItems = () => {
            Array.from(moreItems.children).forEach((item) => {
                navigation.insertBefore(item, more);
            });

            more.hidden = true;
            closeMore();
        };

        const fitItems = () => {
            restoreItems();

            if (window.innerWidth <= 900) {
                return;
            }

            const candidates = () => Array.from(navigation.children)
                .filter((item) => item !== more);

            while (navigation.scrollWidth > navigation.clientWidth && candidates().length > 0) {
                more.hidden = false;
                moreItems.prepend(candidates().at(-1));
            }

            if (moreItems.children.length === 0) {
                more.hidden = true;
            }
        };

        let fitFrame;
        const scheduleFit = () => {
            cancelAnimationFrame(fitFrame);
            fitFrame = requestAnimationFrame(fitItems);
        };

        moreTrigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = ! more.classList.contains('is-open');

            closeMore();

            if (willOpen) {
                more.classList.add('is-open');
                moreTrigger.setAttribute('aria-expanded', 'true');
            }
        });

        const positionNestedSubmenu = (item) => {
            const submenu = item.querySelector(':scope > ul');

            if (! submenu) {
                return;
            }

            item.classList.remove('is-submenu-inline-end');

            const itemRect = item.getBoundingClientRect();
            const submenuWidth = Math.min(submenu.scrollWidth, window.innerWidth - 32);
            const roomBefore = itemRect.left - 16;
            const roomAfter = window.innerWidth - itemRect.right - 16;

            if (roomBefore < submenuWidth && roomAfter > roomBefore) {
                item.classList.add('is-submenu-inline-end');
            }
        };

        moreItems.addEventListener('pointerover', (event) => {
            const item = event.target.closest('li.has-children');

            if (item?.parentElement === moreItems) {
                positionNestedSubmenu(item);
            }
        });

        moreItems.addEventListener('focusin', (event) => {
            const item = event.target.closest('li.has-children');

            if (item?.parentElement === moreItems) {
                positionNestedSubmenu(item);
            }
        });

        document.addEventListener('click', (event) => {
            if (! more.contains(event.target)) {
                closeMore();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && more.classList.contains('is-open')) {
                closeMore(true);
            }
        });

        window.addEventListener('resize', scheduleFit);
        document.fonts?.ready.then(scheduleFit);
        scheduleFit();
    });
};

const initActionPlaceholders = () => {
    if (window.__actionPlaceholdersInitialized) {
        return;
    }

    window.__actionPlaceholdersInitialized = true;

    document.addEventListener('click', (event) => {
        const placeholder = event.target.closest('a[data-action-placeholder][href="#"]');

        if (placeholder) {
            event.preventDefault();
        }
    });
};

const initGalleryLightbox = () => {
    if (window.__galleryLightboxInitialized) {
        return;
    }

    window.__galleryLightboxInitialized = true;

    let overlay = null;
    let image = null;

    const close = () => {
        overlay?.remove();
        overlay = null;
        image = null;
        document.body.classList.remove('gallery-lightbox-open');
    };

    const open = (src, alt = '') => {
        close();

        overlay = document.createElement('div');
        overlay.className = 'gallery-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'gallery-lightbox__close';
        closeButton.setAttribute('aria-label', 'Close image preview');
        closeButton.textContent = '×';

        image = document.createElement('img');
        image.src = src;
        image.alt = alt;

        overlay.append(closeButton, image);
        document.body.appendChild(overlay);
        document.body.classList.add('gallery-lightbox-open');
        closeButton.focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-gallery-lightbox-src]');

        if (trigger) {
            event.preventDefault();
            open(trigger.getAttribute('data-gallery-lightbox-src'), trigger.getAttribute('data-gallery-lightbox-alt') || '');

            return;
        }

        if (overlay && (event.target === overlay || event.target.closest('.gallery-lightbox__close'))) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && overlay) {
            close();
        }
    });
};

const initHeroTemplateSelectors = () => {
    document.querySelectorAll('[data-hero-template-2]').forEach((root) => {
        if (root.dataset.heroTemplate2Initialized === 'true') {
            return;
        }

        root.dataset.heroTemplate2Initialized = 'true';

        const select = root.querySelector('[data-hero-template-2-select]');
        const actionSlot = root.querySelector('[data-hero-template-2-action-slot]');
        const actions = new Map(Array.from(root.querySelectorAll('template[data-hero-template-2-action]')).map((template) => [
            template.getAttribute('data-hero-template-2-action'),
            template,
        ]));

        if (! select || ! actionSlot) {
            return;
        }

        const sync = () => {
            const action = actions.get(select.value);
            actionSlot.replaceChildren();

            if (action) {
                actionSlot.append(action.content.cloneNode(true));

                return;
            }

            const button = document.createElement('button');
            button.className = 'button hero-template-2__button';
            button.type = 'button';
            button.disabled = true;
            button.dataset.heroTemplate2Button = '';
            button.textContent = actionSlot.dataset.buttonLabel || '';
            actionSlot.append(button);
        };

        select.addEventListener('change', sync);

        sync();
    });
};

const initHeroTemplateVideos = () => {
    const playVideos = () => {
        document.querySelectorAll('[data-hero-template-2-video]').forEach((video) => {
            if (video.dataset.heroTemplate2VideoStarted === 'true') {
                return;
            }

            video.dataset.heroTemplate2VideoStarted = 'true';
            video.play().catch(() => {
                video.dataset.heroTemplate2VideoStarted = 'false';
            });
        });
    };

    if (document.readyState === 'complete') {
        playVideos();

        return;
    }

    window.addEventListener('load', playVideos, { once: true });
};

const initStatsCounters = () => {
    const counters = Array.from(document.querySelectorAll('[data-stats-counter]')).filter((counter) => {
        if (counter.dataset.statsCounterInitialized === 'true') {
            return false;
        }

        counter.dataset.statsCounterInitialized = 'true';

        return true;
    });

    if (! counters.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finish = (counter) => {
        counter.textContent = counter.dataset.counterFormatted || counter.textContent;
    };

    const formatNumber = (value, useGrouping) => {
        return useGrouping ? Math.round(value).toLocaleString('en-US') : String(Math.round(value));
    };

    const animate = (counter) => {
        if (counter.dataset.statsCounterAnimated === 'true') {
            return;
        }

        counter.dataset.statsCounterAnimated = 'true';

        const target = Number.parseInt(counter.dataset.counterTarget || '0', 10);
        const prefix = counter.dataset.counterPrefix || '';
        const suffix = counter.dataset.counterSuffix || '';
        const formatted = counter.dataset.counterFormatted || '';
        const useGrouping = formatted.includes(',');

        if (! Number.isFinite(target) || target <= 0 || prefersReducedMotion) {
            finish(counter);

            return;
        }

        const duration = 1400;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = `${prefix}${formatNumber(target * eased, useGrouping)}${suffix}`;

            if (progress < 1) {
                window.requestAnimationFrame(tick);
            } else {
                finish(counter);
            }
        };

        counter.textContent = `${prefix}0${suffix}`;
        window.requestAnimationFrame(tick);
    };

    if (! ('IntersectionObserver' in window)) {
        counters.forEach(finish);

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (! entry.isIntersecting) {
                return;
            }

            animate(entry.target);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.35 });

    counters.forEach((counter) => observer.observe(counter));
};

const initShopCategorySliders = () => {
    document.querySelectorAll('[data-shop-category-slider]').forEach((slider) => {
        if (slider.dataset.shopCategorySliderInitialized === 'true') {
            return;
        }

        slider.dataset.shopCategorySliderInitialized = 'true';

        const viewport = slider.querySelector('[data-shop-category-viewport]');
        const track = slider.querySelector('[data-shop-category-track]');
        const previousButton = slider.querySelector('[data-shop-category-prev]');
        const nextButton = slider.querySelector('[data-shop-category-next]');

        if (! viewport || ! track || ! previousButton || ! nextButton) {
            return;
        }

        const originals = Array.from(track.children);

        if (originals.length <= 5) {
            slider.classList.add('is-static');

            return;
        }

        const itemStep = () => {
            const firstItem = track.children[0];
            const gap = Number.parseFloat(window.getComputedStyle(track).columnGap || window.getComputedStyle(track).gap) || 0;

            return (firstItem?.getBoundingClientRect().width || viewport.clientWidth / 5) + gap;
        };

        let isAnimating = false;

        const finishAnimation = () => {
            track.style.transition = '';
            track.style.transform = '';
            isAnimating = false;
        };

        const moveLeft = () => {
            if (isAnimating) {
                return;
            }

            const step = itemStep();

            if (step <= 0) {
                return;
            }

            isAnimating = true;
            track.style.transition = 'transform 320ms ease';
            track.style.transform = `translateX(-${step}px)`;

            window.setTimeout(() => {
                const firstItem = track.firstElementChild;

                if (firstItem) {
                    track.append(firstItem);
                }

                finishAnimation();
            }, 340);
        };

        const moveRight = () => {
            if (isAnimating) {
                return;
            }

            const step = itemStep();

            if (step <= 0) {
                return;
            }

            const lastItem = track.lastElementChild;

            if (! lastItem) {
                return;
            }

            isAnimating = true;
            track.style.transition = 'none';
            track.prepend(lastItem);
            track.style.transform = `translateX(-${step}px)`;
            track.offsetHeight;
            track.style.transition = 'transform 320ms ease';
            track.style.transform = 'translateX(0)';

            window.setTimeout(() => {
                finishAnimation();
            }, 340);
        };

        previousButton.addEventListener('click', () => {
            moveLeft();
        });

        nextButton.addEventListener('click', () => {
            moveRight();
        });
    });
};

const initShopFilterDrawers = () => {
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    document.querySelectorAll('[data-shop-filter-drawer]').forEach((root) => {
        if (root.dataset.shopFilterDrawerInitialized === 'true') {
            return;
        }

        const panel = root.querySelector('[data-shop-filter-panel]');
        const toggle = root.querySelector('[data-shop-filter-toggle]');
        const backdrop = root.querySelector('.shop-template__filter-backdrop');
        const closeButtons = Array.from(root.querySelectorAll('[data-shop-filter-close]'));

        if (! panel || ! toggle || ! backdrop || closeButtons.length === 0) {
            return;
        }

        root.dataset.shopFilterDrawerInitialized = 'true';
        root.classList.add('is-filter-drawer-ready');

        const mobileQuery = window.matchMedia('(max-width: 900px)');
        let inertElements = [];

        const restoreBackground = () => {
            inertElements.forEach(({ element, wasInert }) => {
                element.inert = wasInert;
            });
            inertElements = [];
        };

        const isolatePanel = () => {
            let branch = panel;

            while (branch.parentElement) {
                const parent = branch.parentElement;

                Array.from(parent.children)
                    .filter((element) => element !== branch && element !== backdrop && element.tagName !== 'SCRIPT')
                    .forEach((element) => {
                        const wasInert = element.inert;
                        element.inert = true;
                        inertElements.push({ element, wasInert });
                    });

                if (parent === document.body) {
                    break;
                }

                branch = parent;
            }
        };

        const close = ({ restoreFocus = true } = {}) => {
            const wasOpen = root.classList.contains('is-filter-open');

            root.classList.remove('is-filter-open');
            toggle.setAttribute('aria-expanded', 'false');
            restoreBackground();

            if (! document.querySelector('[data-shop-filter-drawer].is-filter-open')) {
                document.body.classList.remove('shop-filter-drawer-open');
            }

            if (mobileQuery.matches) {
                panel.setAttribute('aria-hidden', 'true');
            }

            if (wasOpen && restoreFocus) {
                toggle.focus();
            }
        };

        const open = () => {
            if (! mobileQuery.matches || root.classList.contains('is-filter-open')) {
                return;
            }

            isolatePanel();
            root.classList.add('is-filter-open');
            toggle.setAttribute('aria-expanded', 'true');
            panel.setAttribute('aria-hidden', 'false');
            document.body.classList.add('shop-filter-drawer-open');

            requestAnimationFrame(() => {
                panel.querySelector('[data-shop-filter-close]')?.focus();
            });
        };

        const syncMode = () => {
            close({ restoreFocus: false });

            if (mobileQuery.matches) {
                panel.setAttribute('role', 'dialog');
                panel.setAttribute('aria-modal', 'true');
                panel.setAttribute('aria-hidden', 'true');
            } else {
                panel.setAttribute('role', 'complementary');
                panel.removeAttribute('aria-modal');
                panel.setAttribute('aria-hidden', 'false');
            }
        };

        toggle.addEventListener('click', open);
        closeButtons.forEach((button) => button.addEventListener('click', () => close()));

        document.addEventListener('keydown', (event) => {
            if (! root.classList.contains('is-filter-open')) {
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

            const focusable = Array.from(panel.querySelectorAll(focusableSelector))
                .filter((element) => element.getClientRects().length > 0);

            if (focusable.length === 0) {
                event.preventDefault();
                panel.focus();
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

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', syncMode);
        } else {
            mobileQuery.addListener(syncMode);
        }
        syncMode();
    });
};

const initMultiStepForms = () => {
    document.querySelectorAll('[data-multi-step-form]').forEach((form) => {
        if (form.dataset.stepsReady === 'true') {
            return;
        }

        const steps = Array.from(form.querySelectorAll('[data-form-step]'));
        const currentLabel = form.querySelector('[data-step-current]');
        const back = form.querySelector('[data-step-back]');
        const next = form.querySelector('[data-step-next]');
        const submit = form.querySelector('[data-step-submit]');
        const confirmation = form.querySelector('[data-submit-confirmation]');

        if (steps.length < 2 || ! currentLabel || ! back || ! next || ! submit) {
            return;
        }

        form.dataset.stepsReady = 'true';
        const invalidStep = steps.findIndex((step) => step.querySelector('.form-error'));
        let current = invalidStep >= 0
            ? invalidStep
            : (form.dataset.initialStep === 'last' || form.dataset.submitConfirmationError === 'true')
                ? steps.length - 1
                : 0;

        const show = (index) => {
            current = index;
            steps.forEach((step, stepIndex) => {
                const isCurrent = stepIndex === current;

                step.hidden = ! isCurrent;
                step.setAttribute('aria-hidden', isCurrent ? 'false' : 'true');
            });
            currentLabel.textContent = (current + 1).toLocaleString('fa-IR');
            back.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            submit.hidden = current !== steps.length - 1;
            if (confirmation) {
                confirmation.hidden = current !== steps.length - 1;
            }
        };

        next.addEventListener('click', () => {
            const inputs = Array.from(steps[current].querySelectorAll('input, select, textarea'));
            const invalid = inputs.find((input) => ! input.checkValidity());

            if (invalid) {
                invalid.reportValidity();

                return;
            }

            show(Math.min(current + 1, steps.length - 1));
        });
        back.addEventListener('click', () => show(Math.max(current - 1, 0)));
        show(current);
    });
};

const initFormSubmitConfirmations = () => {
    document.querySelectorAll('[data-submit-confirmation]').forEach((root) => {
        if (root.dataset.submitConfirmationReady === 'true') {
            return;
        }

        const checkbox = root.querySelector('[data-submit-confirmation-input]');
        const form = root.closest('form');
        const submitButtons = form ? Array.from(form.querySelectorAll('[data-form-submit]')) : [];

        if (! checkbox || submitButtons.length === 0) {
            return;
        }

        root.dataset.submitConfirmationReady = 'true';
        const sync = () => submitButtons.forEach((button) => {
            button.disabled = ! checkbox.checked;
        });

        checkbox.addEventListener('change', sync);
        sync();
    });
};

const initFormFileInputs = () => {
    document.querySelectorAll('[data-form-file-picker]').forEach((root) => {
        if (root.dataset.formFileReady === 'true') {
            return;
        }

        const input = root.previousElementSibling;
        const status = root.querySelector('[data-form-file-status]');

        if (! input?.matches('[data-form-file-input]') || ! status) {
            return;
        }

        root.dataset.formFileReady = 'true';
        const sync = () => {
            status.textContent = input.files?.[0]?.name || 'فایلی انتخاب نشده است';
        };

        input.addEventListener('change', sync);
        input.form?.addEventListener('reset', () => window.setTimeout(sync, 0));
        sync();
    });
};

const initFormSelects = () => {
    document.querySelectorAll('[data-form-select]').forEach((root) => {
        if (root.dataset.formSelectReady === 'true') {
            return;
        }

        const native = root.querySelector('[data-form-select-native]');
        const trigger = root.querySelector('[data-form-select-trigger]');
        const value = root.querySelector('[data-form-select-value]');
        const listbox = root.querySelector('[data-form-select-listbox]');
        const options = Array.from(root.querySelectorAll('[data-form-select-option]'));

        if (! native || ! trigger || ! value || ! listbox || options.length === 0) {
            return;
        }

        root.dataset.formSelectReady = 'true';
        root.classList.add('is-enhanced');
        let activeIndex = Math.max(0, options.findIndex((option) => option.dataset.value === native.value));

        const setActive = (index, focus = false) => {
            activeIndex = (index + options.length) % options.length;
            options.forEach((option, optionIndex) => option.classList.toggle('is-active', optionIndex === activeIndex));
            trigger.setAttribute('aria-activedescendant', options[activeIndex].id);

            if (focus) {
                options[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        };

        const sync = () => {
            const selected = options.find((option) => option.dataset.value === native.value) || options[0];
            value.textContent = selected.textContent;
            trigger.classList.toggle('is-placeholder', native.value === '');
            options.forEach((option) => option.setAttribute('aria-selected', option === selected ? 'true' : 'false'));
            setActive(Math.max(0, options.indexOf(selected)));
        };

        const close = (restoreFocus = false) => {
            listbox.hidden = true;
            root.classList.remove('is-open');
            root.classList.remove('is-open-up');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');

            if (restoreFocus) {
                trigger.focus();
            }
        };

        const positionPanel = () => {
            const triggerRect = trigger.getBoundingClientRect();
            const viewportHeight = window.visualViewport?.height || window.innerHeight;
            const availableBelow = Math.max(0, viewportHeight - triggerRect.bottom - 12);
            const availableAbove = Math.max(0, triggerRect.top - 12);
            const openAbove = availableBelow < Math.min(240, availableAbove) && availableAbove > availableBelow;
            const available = openAbove ? availableAbove : availableBelow;

            root.classList.toggle('is-open-up', openAbove);
            listbox.style.maxHeight = `${Math.max(120, Math.min(256, available))}px`;
        };

        const open = () => {
            window.dispatchEvent(new CustomEvent('form-select:opening', { detail: { root } }));
            listbox.hidden = false;
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            positionPanel();
            setActive(Math.max(0, options.findIndex((option) => option.dataset.value === native.value)), true);
        };

        const choose = (option) => {
            native.value = option.dataset.value || '';
            native.dispatchEvent(new Event('input', { bubbles: true }));
            native.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            close(true);
        };

        trigger.addEventListener('click', () => listbox.hidden ? open() : close());
        trigger.addEventListener('keydown', (event) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
            }

            if (event.key === 'ArrowDown') {
                if (listbox.hidden) open();
                else setActive(activeIndex + 1, true);
            } else if (event.key === 'ArrowUp') {
                if (listbox.hidden) open();
                else setActive(activeIndex - 1, true);
            } else if (event.key === 'Enter' || event.key === ' ') {
                if (listbox.hidden) open();
                else choose(options[activeIndex]);
            } else if (event.key === 'Escape' && ! listbox.hidden) {
                event.preventDefault();
                close(true);
            } else if (event.key === 'Tab') {
                close();
            }
        });
        options.forEach((option, index) => {
            option.addEventListener('click', () => choose(option));
            option.addEventListener('pointerenter', () => setActive(index));
        });
        native.addEventListener('change', sync);
        native.addEventListener('invalid', (event) => {
            event.preventDefault();
            trigger.setAttribute('aria-invalid', 'true');
            trigger.focus();
        });
        document.addEventListener('click', (event) => {
            if (! root.contains(event.target)) close();
        });
        window.addEventListener('form-select:opening', (event) => {
            if (event.detail.root !== root) close();
        });
        window.addEventListener('resize', () => {
            if (! listbox.hidden) positionPanel();
        });

        sync();
    });
};

const initCalculatorResultModals = () => {
    document.querySelectorAll('[data-calculator-result-modal]').forEach((modal) => {
        if (modal.dataset.resultModalReady === 'true') {
            return;
        }

        modal.dataset.resultModalReady = 'true';
        const closeButton = modal.querySelector('[data-calculator-result-close]');
        let returnFocus = modal.previousElementSibling?.querySelector('[data-step-submit], button[type="submit"]');

        if (! closeButton) {
            return;
        }

        const close = (restoreFocus = true) => {
            modal.hidden = true;

            if (! document.querySelector('[data-calculator-result-modal]:not([hidden])')) {
                document.body.classList.remove('calculator-result-modal-open');
            }

            if (restoreFocus) returnFocus?.focus({ preventScroll: true });
        };

        const open = (opener) => {
            if (modal.dataset.resultStale === 'true') return;
            if (opener instanceof HTMLElement) returnFocus = opener;
            modal.hidden = false;
            document.body.classList.add('calculator-result-modal-open');
            closeButton.focus({ preventScroll: true });
        };
        modal.addEventListener('calculator-result:open', (event) => open(event.detail?.opener));
        window.addEventListener('pageshow', (event) => {
            if (! event.persisted) return;
            close(false);
            modal.dataset.resultStale = 'true';
            modal.dispatchEvent(new CustomEvent('calculator-result:expired'));
        });

        closeButton.addEventListener('click', close);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ! modal.hidden) {
                close();
            }
        });

        open();
    });
};

const initPublicInteractions = () => {
    initProjectGalleryFilters();
    initJalaliMonthFilters();
    initMobileHeader();
    initIndustrialStickyHeader();
    initHeaderOverlays();
    initDesktopNavigationOverflow();
    initActionPlaceholders();
    initGalleryLightbox();
    initHeroTemplateSelectors();
    initHeroTemplateVideos();
    initStatsCounters();
    initShopCategorySliders();
    initShopFilterDrawers();
    initMultiStepForms();
    initFormPages();
    initFormSubmitConfirmations();
    initFormFileInputs();
    initFormSelects();
    initFormDatePickers();
    initFormNumberInputs();
    initCalculatorResultModals();
};

document.addEventListener('forms:rendered', () => {
    initMultiStepForms();
    initFormPages();
    initFormSubmitConfirmations();
    initFormFileInputs();
    initFormSelects();
    initFormDatePickers();
    initFormNumberInputs();
    initCalculatorResultModals();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicInteractions);
} else {
    initPublicInteractions();
}
