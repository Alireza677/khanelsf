import './bootstrap';
import { initIndustrialStickyHeader } from './components/industrial-sticky-header';
import { initHeaderOverlays } from './components/header-overlays';

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
        const button = root.querySelector('[data-hero-template-2-button]');

        if (! select || ! button) {
            return;
        }

        const sync = () => {
            const url = select.value;

            if (url) {
                button.setAttribute('href', url);
                button.setAttribute('aria-disabled', 'false');
            } else {
                button.setAttribute('href', '#');
                button.setAttribute('aria-disabled', 'true');
            }
        };

        select.addEventListener('change', sync);
        button.addEventListener('click', (event) => {
            if (button.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
            }
        });

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

        if (steps.length < 2 || ! currentLabel || ! back || ! next || ! submit) {
            return;
        }

        form.dataset.stepsReady = 'true';
        const invalidStep = steps.findIndex((step) => step.querySelector('.form-error'));
        let current = invalidStep >= 0
            ? invalidStep
            : form.dataset.initialStep === 'last' ? steps.length - 1 : 0;

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
        const returnFocus = modal.previousElementSibling?.querySelector('[data-step-submit], button[type="submit"]');

        if (! closeButton) {
            return;
        }

        const close = () => {
            modal.hidden = true;

            if (! document.querySelector('[data-calculator-result-modal]:not([hidden])')) {
                document.body.classList.remove('calculator-result-modal-open');
            }

            returnFocus?.focus();
        };

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

        modal.hidden = false;
        document.body.classList.add('calculator-result-modal-open');
        closeButton.focus();
    });
};

const initPublicInteractions = () => {
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
    initMultiStepForms();
    initFormSelects();
    initCalculatorResultModals();
};

document.addEventListener('forms:rendered', () => {
    initMultiStepForms();
    initFormSelects();
    initCalculatorResultModals();
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicInteractions);
} else {
    initPublicInteractions();
}
