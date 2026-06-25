@php
    $settings = app(\App\Services\SettingsService::class);
    $customFontUrl = $settings->customFontUrl();
    $customFontName = $settings->customFontName();
    $customFontFormat = $settings->customFontFormat();
    $fontFamily = $settings->themeVariables()['--theme-font-family'] ?? 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
@endphp

<style>
    @if ($customFontUrl)
        @font-face {
            font-family: "{{ addslashes($customFontName) }}";
            src: url("{{ $customFontUrl }}") format("{{ $customFontFormat }}");
            font-display: swap;
            font-style: normal;
            font-weight: 400;
        }
    @endif

    .fi,
    .fi-body,
    .fi-sidebar,
    .fi-topbar,
    .fi-modal,
    .fi-dropdown,
    .fi-ta,
    .fi-fo,
    .fi-no,
    .fi-section,
    .fi-page,
    .fi-simple-layout {
        font-family: {!! $fontFamily !!};
    }

    .fi input,
    .fi textarea,
    .fi select,
    .fi button {
        font-family: inherit;
    }

    .fi-page-editor-locked-scroll {
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-layout,
    .fi-page-editor-locked-scroll .fi-main-ctn {
        height: 100dvh;
        min-height: 100dvh;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-main {
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page > section,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page > section {
        min-height: 0;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page {
        flex: 1;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page > section,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page > section {
        height: 100%;
        padding-block: 1rem 0;
        gap: 1rem;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page > section > div,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page > section > div,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page > section > div > div,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page > section > div > div {
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page > section > div,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page > section > div {
        flex: 1;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-create-record-page form#form,
    .fi-page-editor-locked-scroll .fi-resource-pages.fi-resource-edit-record-page form#form {
        display: flex;
        height: 100%;
        min-height: 0;
        flex-direction: column;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages form#form > .fi-fo-component-ctn {
        display: flex;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages form#form > .fi-fo-component-ctn > * {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
        overflow: hidden;
        width: 100%;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-fo-tabs {
        flex: 1;
        height: 100%;
        max-height: calc(100dvh - 15rem);
        min-height: 0;
        overflow: hidden;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-tabs {
        position: sticky;
        top: 0;
        z-index: 10;
        flex-shrink: 0;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-fo-tabs-tab.fi-active {
        flex: 1;
        height: 100%;
        min-height: 0;
        max-height: 100%;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding-bottom: 1.5rem;
        scrollbar-gutter: stable;
    }

    .post-content-scroll-editor.fi-fo-rich-editor {
        display: flex !important;
        flex-direction: column;
        height: min(62dvh, 42rem);
        min-height: 24rem;
        overflow: hidden !important;
    }

    .post-content-scroll-editor > .fi-input-wrp-input {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .post-content-scroll-editor > .fi-input-wrp-input > div {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        height: 100%;
        min-height: 0;
        overflow: hidden;
    }

    .post-content-scroll-editor input[id^="trix-value-"] {
        display: none;
    }

    .post-content-scroll-editor .fi-fo-rich-editor-toolbar,
    .post-content-scroll-editor trix-toolbar {
        position: relative !important;
        top: auto !important;
        z-index: 30;
        grid-row: 1;
        flex-shrink: 0;
        background: rgb(255 255 255 / 0.98);
        backdrop-filter: blur(8px);
    }

    .dark .post-content-scroll-editor .fi-fo-rich-editor-toolbar,
    .dark .post-content-scroll-editor trix-toolbar {
        background: rgb(17 24 39 / 0.98);
    }

    .post-content-scroll-editor .fi-fo-rich-editor-editor:not(trix-editor) {
        height: 100% !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    .post-content-scroll-editor trix-editor,
    .post-content-scroll-editor trix-editor.fi-fo-rich-editor-editor {
        display: block;
        grid-row: 2;
        height: 100% !important;
        min-height: 0 !important;
        max-height: none !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        box-sizing: border-box;
        overscroll-behavior: contain;
        padding-top: 1rem !important;
        scrollbar-gutter: stable;
    }

    .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-form-actions {
        position: sticky;
        bottom: 0;
        z-index: 20;
        flex-shrink: 0;
        margin-inline: -1rem;
        padding: 1rem;
        background: rgb(255 255 255 / 0.96);
        box-shadow: 0 -10px 24px rgb(15 23 42 / 0.08);
        backdrop-filter: blur(10px);
        border-top: 1px solid rgb(229 231 235);
    }

    .dark .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-form-actions {
        background: rgb(17 24 39 / 0.96);
        border-top-color: rgb(255 255 255 / 0.1);
        box-shadow: 0 -10px 24px rgb(0 0 0 / 0.24);
    }

    .media-upload-fixed-preview .filepond--root[data-style-panel-layout='grid'] .filepond--item {
        height: 150px !important;
        width: 150px !important;
    }

    .internal-link-search {
        margin-top: 0.75rem;
        direction: rtl;
        text-align: right;
    }

    .internal-link-search__label {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(55 65 81);
    }

    .dark .internal-link-search__label {
        color: rgb(209 213 219);
    }

    .internal-link-search__input {
        width: 100%;
        border-radius: 0.5rem;
        border: 1px solid rgb(209 213 219);
        background: rgb(255 255 255);
        padding: 0.5rem 0.75rem;
        color: rgb(17 24 39);
        font-size: 0.875rem;
    }

    .dark .internal-link-search__input {
        border-color: rgb(75 85 99);
        background: rgb(31 41 55);
        color: rgb(255 255 255);
    }

    .internal-link-search__status {
        padding: 0.5rem 0.125rem 0;
        color: rgb(107 114 128);
        font-size: 0.75rem;
    }

    .internal-link-search__results {
        margin-top: 0.5rem;
        max-height: 14rem;
        overflow-y: auto;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background: rgb(255 255 255);
    }

    .dark .internal-link-search__results {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .internal-link-search__result {
        display: block;
        width: 100%;
        border: 0;
        border-bottom: 1px solid rgb(243 244 246);
        background: transparent;
        padding: 0.625rem 0.75rem;
        text-align: right;
        cursor: pointer;
    }

    .internal-link-search__result:hover,
    .internal-link-search__result:focus {
        background: rgb(243 244 246);
        outline: none;
    }

    .dark .internal-link-search__result {
        border-bottom-color: rgb(31 41 55);
    }

    .dark .internal-link-search__result:hover,
    .dark .internal-link-search__result:focus {
        background: rgb(31 41 55);
    }

    .internal-link-search__result-title {
        display: block;
        color: rgb(17 24 39);
        font-size: 0.875rem;
        font-weight: 600;
    }

    .dark .internal-link-search__result-title {
        color: rgb(255 255 255);
    }

    .internal-link-search__result-meta {
        display: block;
        margin-top: 0.125rem;
        color: rgb(107 114 128);
        direction: ltr;
        font-size: 0.75rem;
        text-align: right;
    }

    @media (min-width: 768px) {
        .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-form-actions {
            margin-inline: 0;
            border-radius: 0.75rem 0.75rem 0 0;
        }
    }
</style>

<script>
    (() => {
        if (window.__internalLinkSearchBound) {
            return;
        }

        window.__internalLinkSearchBound = true;

        const endpoint = @json(route('admin.internal-links.search'));
        const strings = {
            label: 'جستجوی لینک داخلی',
            placeholder: 'جستجوی نوشته، برگه، محصول...',
            loading: 'در حال جستجو...',
            empty: 'نتیجه‌ای پیدا نشد',
            minLength: 'برای جستجو حداقل ۲ حرف وارد کنید.',
            error: 'خطا در جستجو. دوباره تلاش کنید.',
        };

        const debounce = (callback, delay = 300) => {
            let timeout;

            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => callback(...args), delay);
            };
        };

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[character]);

        const enhanceDialog = (dialog) => {
            if (! dialog || dialog.dataset.internalLinkSearchEnhanced === 'true') {
                return;
            }

            dialog.dataset.internalLinkSearchEnhanced = 'true';

            const wrapper = document.createElement('div');
            wrapper.className = 'internal-link-search';
            wrapper.dir = 'rtl';
            wrapper.innerHTML = `
                <label class="internal-link-search__label">${strings.label}</label>
                <input class="internal-link-search__input" type="search" autocomplete="off" placeholder="${strings.placeholder}">
                <div class="internal-link-search__status" hidden></div>
                <div class="internal-link-search__results" hidden></div>
            `;

            dialog.appendChild(wrapper);

            const searchInput = wrapper.querySelector('.internal-link-search__input');
            const status = wrapper.querySelector('.internal-link-search__status');
            const results = wrapper.querySelector('.internal-link-search__results');
            let abortController = null;

            const setStatus = (message) => {
                status.textContent = message;
                status.hidden = ! message;
            };

            const clearResults = () => {
                results.innerHTML = '';
                results.hidden = true;
            };

            const setHref = (url, apply = false) => {
                const hrefInput = dialog.querySelector('input[name="href"][data-trix-input]');

                if (! hrefInput) {
                    return;
                }

                hrefInput.value = url;
                hrefInput.dispatchEvent(new Event('input', { bubbles: true }));
                hrefInput.dispatchEvent(new Event('change', { bubbles: true }));
                hrefInput.focus();

                if (apply) {
                    dialog.querySelector('[data-trix-method="setAttribute"]')?.click();
                }
            };

            const renderResults = (items) => {
                clearResults();

                if (! items.length) {
                    setStatus(strings.empty);
                    return;
                }

                setStatus('');
                results.hidden = false;
                results.innerHTML = items.map((item) => `
                    <button class="internal-link-search__result" type="button" data-url="${escapeHtml(item.url)}">
                        <span class="internal-link-search__result-title">${escapeHtml(item.title)} <small>(${escapeHtml(item.type)})</small></span>
                        <span class="internal-link-search__result-meta">${escapeHtml(item.subtitle)}</span>
                    </button>
                `).join('');
            };

            const runSearch = debounce(async () => {
                const query = searchInput.value.trim();

                clearResults();

                if (query.length < 2) {
                    setStatus(query.length ? strings.minLength : '');
                    return;
                }

                abortController?.abort();
                abortController = new AbortController();
                setStatus(strings.loading);

                try {
                    const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: abortController.signal,
                    });

                    if (! response.ok) {
                        throw new Error(`Search failed with status ${response.status}`);
                    }

                    renderResults(await response.json());
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        clearResults();
                        setStatus(strings.error);
                    }
                }
            }, 300);

            searchInput.addEventListener('input', runSearch);

            results.addEventListener('mousedown', (event) => {
                event.preventDefault();
            });

            results.addEventListener('click', (event) => {
                const result = event.target.closest('.internal-link-search__result');

                if (! result) {
                    return;
                }

                setHref(result.dataset.url, true);
            });
        };

        const enhanceAllDialogs = () => {
            document
                .querySelectorAll('.post-content-scroll-editor trix-toolbar .trix-dialog--link')
                .forEach(enhanceDialog);
        };

        document.addEventListener('DOMContentLoaded', enhanceAllDialogs);
        document.addEventListener('trix-initialize', enhanceAllDialogs);

        new MutationObserver(enhanceAllDialogs).observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    })();
</script>
