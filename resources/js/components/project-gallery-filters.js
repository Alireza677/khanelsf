const groups = ['type', 'location', 'service', 'year'];
const emptyFilters = () => Object.fromEntries(groups.map((key) => [key, []]));
const number = (value) => Number(value).toLocaleString('fa-IR');

export const initProjectGalleryFilters = () => {
    const root = document.querySelector('[data-project-gallery-filters]');
    if (!root || root.dataset.ready || !document.querySelector('[data-project-gallery-results]')) return;
    root.dataset.ready = 'true';
    document.documentElement.classList.add('project-gallery-filters-ready');
    root.hidden = false;

    const panel = root.querySelector('dialog');
    const form = panel.querySelector('form');
    const triggers = [...root.querySelectorAll('[data-project-filter-open]')];
    const inputs = [...form.querySelectorAll('input[type="checkbox"]')];
    const apply = form.querySelector('[data-project-filter-apply]');
    const count = form.querySelector('[data-project-filter-count]');
    const panelMessage = root.querySelector('[data-project-filter-panel-message]');
    const notice = root.querySelector('[data-project-filter-notice]');
    let draft = JSON.parse(root.dataset.active);
    let lastTrigger = triggers[0];
    let countRequest;
    let resultsRequest;
    let countTimer;
    let countVersion = 0;
    let loadingResults = false;
    let panelMotion;
    let closingPanel = false;
    const animateDrawer = () => window.matchMedia('(min-width: 641px) and (prefers-reduced-motion: no-preference)').matches;

    const showMessage = (message = '') => {
        panelMessage.textContent = message;
        notice.textContent = panel.open ? '' : message;
        notice.hidden = !notice.textContent;
    };
    const syncSelections = () => {
        inputs.forEach((input) => { input.checked = draft[input.dataset.filterGroup].includes(input.value); });
        const selected = Object.values(draft).reduce((sum, values) => sum + values.length, 0);
        root.querySelectorAll('[data-project-filter-badge]').forEach((badge) => {
            badge.textContent = number(selected);
            badge.hidden = selected === 0;
        });
    };
    const setCount = (total) => {
        count.textContent = number(total);
        root.dataset.count = String(total);
    };
    const cancelCount = () => {
        countVersion += 1;
        clearTimeout(countTimer);
        countRequest?.abort();
    };
    const selectedUrl = () => {
        const url = new URL(root.dataset.url, location.href);
        groups.forEach((key) => draft[key].forEach((value) => url.searchParams.append(`${key}[]`, value)));
        return url;
    };
    const refreshCount = () => {
        cancelCount();
        const version = countVersion;
        apply.disabled = true;
        apply.setAttribute('aria-busy', 'true');
        showMessage('در حال شمارش پروژه‌ها…');
        countTimer = setTimeout(async () => {
            countRequest = new AbortController();
            try {
                const response = await fetch(selectedUrl(), {
                    headers: { Accept: 'application/json', 'X-Project-Gallery': 'count' },
                    signal: countRequest.signal,
                    cache: 'no-store',
                });
                if (!response.ok) throw new Error('Count failed');
                const result = await response.json();
                if (version !== countVersion) return;
                setCount(result.count);
                showMessage();
            } catch (error) {
                if (error.name !== 'AbortError' && version === countVersion) {
                    showMessage('شمارش انجام نشد؛ برای تلاش دوباره «مشاهده پروژه» را بزنید.');
                }
            } finally {
                if (version === countVersion) {
                    apply.disabled = loadingResults;
                    apply.setAttribute('aria-busy', String(loadingResults));
                }
            }
        }, 220);
    };
    const closePanel = () => {
        if (!panel.open || closingPanel) return;
        closingPanel = true;
        const transform = getComputedStyle(panel).transform;
        panelMotion?.cancel();
        if (!animateDrawer()) {
            panel.close();
            return;
        }
        panelMotion = panel.animate([{ transform }, { transform: 'translateX(100%)' }], { duration: 200, easing: 'ease-in', fill: 'forwards' });
        // Keep the modal backdrop and scroll lock until the exit animation ends.
        panelMotion.finished.then(() => panel.close()).catch(() => {});
    };
    triggers.forEach((trigger) => trigger.addEventListener('click', () => {
        if (panel.open) return;
        lastTrigger = trigger;
        notice.hidden = true;
        panelMotion?.cancel();
        panel.showModal();
        if (animateDrawer()) {
            panelMotion = panel.animate([{ transform: 'translateX(100%)' }, { transform: 'translateX(0)' }], { duration: 240, easing: 'ease-out' });
        }
        document.body.classList.add('project-filter-panel-open');
        triggers.forEach((button) => button.setAttribute('aria-expanded', 'true'));
        panel.querySelector('[data-project-filter-close]').focus();
    }));
    panel.addEventListener('close', () => {
        panelMotion?.cancel();
        closingPanel = false;
        document.body.classList.remove('project-filter-panel-open');
        triggers.forEach((button) => button.setAttribute('aria-expanded', 'false'));
        const trigger = lastTrigger.getClientRects().length ? lastTrigger : triggers.find((button) => button.getClientRects().length);
        trigger?.focus({ preventScroll: true });
    });
    panel.querySelector('[data-project-filter-close]').addEventListener('click', closePanel);
    panel.addEventListener('cancel', (event) => { event.preventDefault(); closePanel(); });
    panel.addEventListener('click', (event) => {
        if (event.target !== panel) return;
        const rect = panel.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closePanel();
    });
    form.querySelectorAll('.project-filters__accordion').forEach((button) => {
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(expanded));
            const icon = button.querySelector('.project-filters__chevron');
            icon.classList.toggle('icon-arrow-left-2', !expanded);
            icon.classList.toggle('icon-arrow-down-1', expanded);
            document.getElementById(button.getAttribute('aria-controls')).hidden = !expanded;
        });
    });
    form.addEventListener('change', (event) => {
        if (!event.target.matches('input[type="checkbox"]')) return;
        draft = emptyFilters();
        inputs.filter((input) => input.checked).forEach((input) => draft[input.dataset.filterGroup].push(input.value));
        syncSelections();
        refreshCount();
    });

    const loadResults = async (url, { history = 'push', close = false, scroll = false } = {}) => {
        cancelCount();
        resultsRequest?.abort();
        const request = new AbortController();
        resultsRequest = request;
        loadingResults = true;
        apply.disabled = true;
        apply.setAttribute('aria-busy', 'true');
        inputs.forEach((input) => { input.disabled = true; });
        document.querySelectorAll('[data-project-gallery-results]').forEach((el) => el.setAttribute('aria-busy', 'true'));
        showMessage('در حال دریافت پروژه‌ها…');
        try {
            const response = await fetch(url, { signal: request.signal, cache: 'no-store', headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error('Gallery failed');
            // Reuse the actual template renderer, including all saved card presentation settings.
            const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (request !== resultsRequest) return;
            const updatedRoot = documentFragment.querySelector('[data-project-gallery-filters]');
            const results = [...documentFragment.querySelectorAll('[data-project-gallery-results]')];
            const existing = [...document.querySelectorAll('[data-project-gallery-results]')];
            if (!updatedRoot || !results.length || results.length !== existing.length) throw new Error('Missing gallery');
            existing.forEach((element, index) => element.replaceWith(results[index]));
            draft = JSON.parse(updatedRoot.dataset.active);
            syncSelections();
            setCount(updatedRoot.dataset.count);
            if (history === 'push' && url.toString() !== location.href) window.history.pushState({}, '', url);
            showMessage();
            if (close) closePanel();
            if (scroll) {
                results[0].scrollIntoView({ block: 'start' });
                results[0].focus({ preventScroll: true });
            }
        } catch (error) {
            if (error.name !== 'AbortError' && request === resultsRequest) {
                showMessage('دریافت پروژه‌ها انجام نشد. دوباره تلاش کنید.');
            }
        } finally {
            if (request === resultsRequest) {
                loadingResults = false;
                apply.disabled = false;
                apply.setAttribute('aria-busy', 'false');
                inputs.forEach((input) => { input.disabled = false; });
                document.querySelectorAll('[data-project-gallery-results]').forEach((el) => el.removeAttribute('aria-busy'));
            }
        }
    };
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!loadingResults) loadResults(selectedUrl(), { close: true });
    });
    document.addEventListener('click', (event) => {
        const reset = event.target.closest('[data-project-filter-reset]');
        if (reset && (root.contains(reset) || reset.closest('[data-project-gallery-results]'))) {
            draft = emptyFilters();
            syncSelections();
            loadResults(selectedUrl(), { scroll: !panel.open });
            return;
        }
        const link = event.target.closest('[data-project-gallery-results] .shared-collection__pagination a');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (link.hasAttribute('aria-current')) return;
        loadResults(new URL(link.href), { scroll: true });
    });
    window.addEventListener('popstate', () => { closePanel(); loadResults(new URL(location.href), { history: 'none' }); });
    syncSelections();
    setCount(root.dataset.count);
};
