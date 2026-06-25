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

    .post-content-scroll-editor.fi-fo-rich-editor,
    .post-content-scroll-editor.fi-fo-rich-editor > div {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .post-content-scroll-editor.fi-fo-rich-editor {
        height: min(62dvh, 42rem);
        min-height: 24rem;
        overflow: hidden !important;
    }

    .post-content-scroll-editor.fi-fo-rich-editor > div {
        flex: 1 1 auto;
        height: 100%;
        min-height: 0;
        overflow: hidden;
    }

    .post-content-scroll-editor .fi-fo-rich-editor-toolbar {
        position: sticky;
        top: 0;
        z-index: 5;
        flex: 0 0 auto;
        background: rgb(255 255 255 / 0.98);
        backdrop-filter: blur(8px);
    }

    .dark .post-content-scroll-editor .fi-fo-rich-editor-toolbar {
        background: rgb(17 24 39 / 0.98);
    }

    .post-content-scroll-editor .fi-fo-rich-editor-editor {
        display: block;
        flex: 1 1 auto;
        height: auto !important;
        max-height: none !important;
        min-height: 0;
        overflow-y: auto !important;
        overflow-x: hidden;
        overscroll-behavior: contain;
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

    @media (min-width: 768px) {
        .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-form-actions {
            margin-inline: 0;
            border-radius: 0.75rem 0.75rem 0 0;
        }
    }
</style>

<script>
    (() => {
        if (window.__postContentScrollEditorBound) {
            return;
        }

        window.__postContentScrollEditorBound = true;

        document.addEventListener('wheel', (event) => {
            const wrapper = event.target instanceof Element
                ? event.target.closest('.post-content-scroll-editor')
                : null;

            if (! wrapper) {
                return;
            }

            const editor = wrapper.querySelector('trix-editor');

            if (! editor || editor.scrollHeight <= editor.clientHeight) {
                return;
            }

            const canScrollUp = editor.scrollTop > 0;
            const canScrollDown = editor.scrollTop + editor.clientHeight < editor.scrollHeight;

            if ((event.deltaY < 0 && canScrollUp) || (event.deltaY > 0 && canScrollDown)) {
                editor.scrollTop += event.deltaY;
                event.preventDefault();
            }
        }, { passive: false });
    })();
</script>
