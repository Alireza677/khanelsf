@php
    $settings = app(\App\Services\SettingsService::class);
    $adminDashboardBackgroundLight = $settings->adminDashboardBackgroundLight();
@endphp

@include('partials.site-font-styles')

<style>
    :root {
        --admin-dashboard-background-light: {{ $adminDashboardBackgroundLight }};
        --cms-modal-layer-z: 60;
    }

    .cms-modal-layer {
        position: fixed;
        inset: 0;
        z-index: var(--cms-modal-layer-z);
        isolation: isolate;
    }

    .cms-modal-backdrop {
        position: absolute;
        inset: 0;
        z-index: 0;
    }

    .cms-modal-panel {
        position: relative;
        z-index: 1;
    }

    html:not(.dark) .fi-body {
        background-color: var(--admin-dashboard-background-light) !important;
    }

    .fi-main {
        max-width: min(100%, calc(70vw + 24rem)) !important;
    }

    html,
    body,
    .fi,
    .fi-body,
    .fi-layout,
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
        font-family: var(--site-font-family) !important;
    }

    .fi .font-sans,
    .fi input,
    .fi textarea,
    .fi select,
    .fi button {
        font-family: inherit !important;
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

    .media-view-switcher {
        display: inline-flex;
        overflow: hidden;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: rgb(255 255 255);
    }

    .dark .media-view-switcher {
        border-color: rgb(75 85 99);
        background: rgb(17 24 39);
    }

    .media-view-switcher__button {
        display: inline-flex;
        width: 2.25rem;
        height: 2.25rem;
        align-items: center;
        justify-content: center;
        color: rgb(107 114 128);
        transition: background-color 150ms ease, color 150ms ease;
    }

    .media-view-switcher__button + .media-view-switcher__button {
        border-inline-start: 1px solid rgb(209 213 219);
    }

    .dark .media-view-switcher__button + .media-view-switcher__button {
        border-inline-start-color: rgb(75 85 99);
    }

    .media-view-switcher__button:hover {
        background: rgb(249 250 251);
        color: rgb(17 24 39);
    }

    .dark .media-view-switcher__button:hover {
        background: rgb(31 41 55);
        color: rgb(255 255 255);
    }

    .media-view-switcher__button.is-active {
        background: rgb(239 246 255);
        color: rgb(37 99 235);
    }

    .dark .media-view-switcher__button.is-active {
        background: rgb(30 58 138 / 0.35);
        color: rgb(96 165 250);
    }

    .media-grid-card {
        min-width: 0;
        width: 100%;
    }

    .media-grid-card__preview {
        position: relative;
        display: flex;
        aspect-ratio: 1 / 1;
        width: 100%;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 0.375rem;
        background: rgb(243 244 246);
        color: rgb(107 114 128);
    }

    .dark .media-grid-card__preview {
        background: rgb(31 41 55);
        color: rgb(156 163 175);
    }

    .media-grid-card__preview img,
    .media-grid-card__preview video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .media-grid-card__type-icon {
        position: absolute;
        inset: 50% auto auto 50%;
        display: inline-flex;
        width: 2.75rem;
        height: 2.75rem;
        align-items: center;
        justify-content: center;
        transform: translate(-50%, -50%);
        border-radius: 9999px;
        background: rgb(0 0 0 / 0.58);
        color: rgb(255 255 255);
    }

    .media-grid-card__file-icon {
        width: 3rem;
        height: 3rem;
    }

    .media-grid-card__extension {
        position: absolute;
        inset-inline-end: 0.5rem;
        bottom: 0.5rem;
        max-width: calc(100% - 1rem);
        overflow: hidden;
        border-radius: 0.25rem;
        background: rgb(255 255 255 / 0.9);
        padding: 0.125rem 0.375rem;
        color: rgb(55 65 81);
        font-size: 0.6875rem;
        font-weight: 700;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dark .media-grid-card__extension {
        background: rgb(17 24 39 / 0.9);
        color: rgb(229 231 235);
    }

    .media-grid-card__title {
        overflow: hidden;
        margin-top: 0.625rem;
        color: rgb(17 24 39);
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1.35rem;
        text-align: start;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dark .media-grid-card__title {
        color: rgb(243 244 246);
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

    [x-cloak] {
        display: none !important;
    }

    .form-builder-editor {
        direction: ltr;
        display: grid;
        gap: 1.25rem;
        grid-template-areas: "inspector canvas";
        grid-template-columns: minmax(0, 3fr) minmax(18rem, 2fr);
        align-items: start;
    }

    .form-builder-inspector,
    .form-builder-canvas {
        direction: rtl;
        border: 1px solid rgb(229 231 235);
        border-radius: .75rem;
        background: rgb(255 255 255);
        box-shadow: 0 1px 2px rgb(0 0 0 / .04);
    }

    .dark .form-builder-inspector,
    .dark .form-builder-canvas {
        border-color: rgb(255 255 255 / .1);
        background: rgb(255 255 255 / .05);
    }

    .form-builder-inspector {
        grid-area: inspector;
        position: sticky;
        top: 5.5rem;
        max-height: calc(100vh - 7rem);
        overflow: hidden;
    }

    .form-builder-canvas {
        grid-area: canvas;
        min-height: 32rem;
        padding: .75rem;
    }

    .form-builder-tabs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        border-bottom: 1px solid rgb(229 231 235);
        padding: .5rem .5rem 0;
    }

    .dark .form-builder-tabs {
        border-bottom-color: rgb(255 255 255 / .1);
    }

    .form-builder-tabs button {
        border-bottom: 2px solid transparent;
        color: rgb(107 114 128);
        padding: .75rem .5rem;
        font-size: .875rem;
        font-weight: 600;
    }

    .form-builder-tabs button.is-active {
        border-bottom-color: rgb(var(--primary-600));
        color: rgb(var(--primary-600));
    }

    .form-builder-inspector__body {
        max-height: calc(100vh - 11rem);
        overflow-y: auto;
        padding: 1rem;
    }

    .form-builder-search {
        display: flex;
        align-items: center;
        gap: .5rem;
        border: 1px solid rgb(209 213 219);
        border-radius: .5rem;
        padding: .55rem .75rem;
    }

    .form-builder-search svg {
        width: 1.1rem;
        color: rgb(107 114 128);
    }

    .form-builder-search input {
        min-width: 0;
        flex: 1;
        border: 0;
        background: transparent;
        outline: 0;
        font-size: .875rem;
    }

    .form-builder-palette {
        display: grid;
        gap: .75rem;
        margin-top: 1rem;
    }

    .form-builder-palette details {
        border-bottom: 1px solid rgb(229 231 235);
        padding-bottom: .75rem;
    }

    .dark .form-builder-palette details {
        border-bottom-color: rgb(255 255 255 / .1);
    }

    .form-builder-palette summary {
        cursor: pointer;
        font-size: .8rem;
        font-weight: 700;
        color: rgb(75 85 99);
    }

    .form-builder-palette__items {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
        margin-top: .65rem;
    }

    .form-builder-palette__items button {
        display: grid;
        grid-template-columns: 1.1rem 1fr .9rem;
        align-items: center;
        gap: .4rem;
        min-height: 3rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .5rem;
        padding: .55rem;
        text-align: right;
        font-size: .78rem;
        font-weight: 600;
    }

    .form-builder-palette__items button:hover {
        border-color: rgb(var(--primary-500));
        background: rgb(var(--primary-50));
    }

    .form-builder-palette__items svg,
    .form-builder-inspector__empty svg,
    .form-builder-canvas__empty svg {
        width: 100%;
    }

    .form-builder-inspector__empty,
    .form-builder-canvas__empty {
        display: grid;
        place-items: center;
        gap: .5rem;
        color: rgb(107 114 128);
        padding: 2rem;
        text-align: center;
    }

    .form-builder-inspector__empty svg,
    .form-builder-canvas__empty svg {
        width: 2rem;
    }

    .form-builder-canvas__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .form-builder-canvas__header h3 {
        color: rgb(17 24 39);
        font-weight: 700;
    }

    .dark .form-builder-canvas__header h3 {
        color: rgb(255 255 255);
    }

    .form-builder-canvas__header p,
    .form-builder-canvas__header > span {
        color: rgb(107 114 128);
        font-size: .75rem;
    }

    .form-builder-canvas__items {
        display: grid;
        gap: .55rem;
        min-height: 24rem;
        align-content: start;
    }

    .form-builder-card {
        position: relative;
        display: grid;
        gap: .55rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .65rem;
        background: rgb(249 250 251);
        padding: .65rem;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }

    .dark .form-builder-card {
        border-color: rgb(255 255 255 / .1);
        background: rgb(17 24 39 / .45);
    }

    .form-builder-card:hover,
    .form-builder-card.is-selected {
        border-color: rgb(var(--primary-500));
        box-shadow: 0 0 0 2px rgb(var(--primary-500) / .12);
        background: rgb(var(--primary-50));
    }

    .dark .form-builder-card.is-selected {
        background: rgb(var(--primary-950) / .3);
    }

    .form-builder-card__topline {
        display: flex;
        align-items: center;
        gap: .55rem;
        padding-inline-end: 4.5rem;
    }

    .form-builder-card__handle {
        display: grid;
        width: 1.25rem;
        place-items: center;
        color: rgb(156 163 175);
        cursor: grab;
    }

    .form-builder-card__handle svg {
        width: 1rem;
    }

    .form-builder-card__title {
        display: grid;
        min-width: 0;
    }

    .form-builder-card__title strong,
    .form-builder-step-divider strong {
        color: rgb(17 24 39);
        font-size: .875rem;
    }

    .dark .form-builder-card__title strong,
    .dark .form-builder-step-divider strong {
        color: rgb(255 255 255);
    }

    .form-builder-card__title span,
    .form-builder-card__meta,
    .form-builder-step-divider span {
        color: rgb(107 114 128);
        font-size: .7rem;
    }

    .form-builder-card__meta {
        display: flex;
        gap: .4rem;
        margin-inline-start: auto;
    }

    .form-builder-card__meta span {
        border-radius: 999px;
        background: rgb(229 231 235);
        padding: .2rem .45rem;
    }

    .form-builder-card__meta .is-required {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .form-builder-card__preview {
        display: flex;
        align-items: center;
        gap: .5rem;
        min-height: 2rem;
        border: 1px solid rgb(209 213 219);
        border-radius: .45rem;
        background: rgb(255 255 255);
        color: rgb(156 163 175);
        padding: .4rem .6rem;
        font-size: .75rem;
    }

    .form-builder-card__preview svg {
        width: .9rem;
        margin-inline-start: auto;
    }

    .form-builder-card__preview .is-textarea {
        min-height: 2.5rem;
    }

    .form-builder-card__preview .is-choice {
        border: 1px solid rgb(209 213 219);
        border-radius: 999px;
        padding: .2rem .45rem;
        color: rgb(107 114 128);
    }

    .form-builder-card__actions {
        position: absolute;
        inset-block-start: .5rem;
        inset-inline-end: .5rem;
        display: flex;
        gap: .15rem;
    }

    .form-builder-card.is-structural {
        grid-template-columns: 1.25rem 1fr auto;
        align-items: center;
        border-style: dashed;
        background: rgb(var(--primary-50));
    }

    .form-builder-step-divider {
        display: flex;
        align-items: baseline;
        gap: .5rem;
        padding-inline-end: 4.5rem;
    }

    .form-builder-field-settings > .fi-fo-component-ctn {
        gap: 1rem;
    }

    .form-builder-choices-control {
        display: grid;
        gap: .75rem;
    }

    .form-builder-manage-choices {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        width: 100%;
        border: 1px solid rgb(var(--primary-500));
        border-radius: .55rem;
        background: rgb(var(--primary-50));
        color: rgb(var(--primary-700));
        padding: .7rem .8rem;
        font-size: .8rem;
        font-weight: 700;
    }

    .form-builder-manage-choices > span {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .form-builder-manage-choices > span:last-child {
        color: rgb(107 114 128);
        font-size: .7rem;
        font-weight: 500;
    }

    .form-builder-manage-choices svg {
        width: 1rem;
    }

    .dark .form-builder-manage-choices {
        background: rgb(var(--primary-950) / .35);
        color: rgb(var(--primary-300));
    }

    .form-builder-choice-metadata {
        border: 1px solid rgb(229 231 235);
        border-radius: .55rem;
        padding: .7rem;
    }

    .dark .form-builder-choice-metadata {
        border-color: rgb(255 255 255 / .1);
    }

    .form-builder-choice-metadata summary {
        cursor: pointer;
        color: rgb(75 85 99);
        font-size: .75rem;
        font-weight: 600;
    }

    .form-builder-choice-metadata > p {
        margin-top: .45rem;
        color: rgb(107 114 128);
        font-size: .7rem;
        line-height: 1.6;
    }

    .form-builder-choice-metadata__items {
        display: grid;
        gap: 1rem;
        margin-top: .75rem;
    }

    .form-builder-choice-metadata__item {
        display: grid;
        gap: .75rem;
        border-top: 1px solid rgb(229 231 235);
        padding-top: .75rem;
    }

    .dark .form-builder-choice-metadata__item {
        border-top-color: rgb(255 255 255 / .1);
    }

    .form-builder-choice-metadata__item > strong {
        font-size: .75rem;
    }

    .form-builder-choices-layer {
        position: fixed;
        inset: 0;
        z-index: 45;
        pointer-events: none;
    }

    .form-builder-choices-backdrop {
        position: fixed;
        inset: 0;
        background: rgb(15 23 42 / .12);
        pointer-events: auto;
    }

    .form-builder-choices-drawer {
        position: fixed;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid rgb(229 231 235);
        border-radius: .75rem;
        background: rgb(255 255 255);
        box-shadow: 0 24px 60px rgb(15 23 42 / .22);
        direction: rtl;
        pointer-events: auto;
    }

    .dark .form-builder-choices-drawer {
        border-color: rgb(255 255 255 / .1);
        background: rgb(17 24 39);
    }

    .form-builder-choices-drawer__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        border-bottom: 1px solid rgb(229 231 235);
        padding: 1rem;
    }

    .dark .form-builder-choices-drawer__header {
        border-bottom-color: rgb(255 255 255 / .1);
    }

    .form-builder-choices-drawer__header h3 {
        color: rgb(17 24 39);
        font-size: .95rem;
        font-weight: 700;
    }

    .dark .form-builder-choices-drawer__header h3 {
        color: rgb(255 255 255);
    }

    .form-builder-choices-drawer__header p {
        margin-top: .2rem;
        color: rgb(107 114 128);
        font-size: .72rem;
    }

    .form-builder-choices-drawer__header button {
        display: grid;
        width: 2rem;
        height: 2rem;
        flex: 0 0 auto;
        place-items: center;
        border-radius: .45rem;
        color: rgb(107 114 128);
    }

    .form-builder-choices-drawer__header button:hover {
        background: rgb(243 244 246);
    }

    .form-builder-choices-drawer__header svg {
        width: 1.1rem;
    }

    .form-builder-choices-drawer__body {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 1rem;
    }

    .form-builder-choice-list {
        display: grid;
        gap: .6rem;
    }

    .form-builder-choice-row {
        display: grid;
        grid-template-columns: 2rem minmax(0, 1fr) 2rem;
        align-items: center;
        gap: .5rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .55rem;
        background: rgb(249 250 251);
        padding: .6rem;
    }

    .dark .form-builder-choice-row {
        border-color: rgb(255 255 255 / .1);
        background: rgb(255 255 255 / .04);
    }

    .form-builder-choice-row__handle,
    .form-builder-choice-row__delete {
        display: grid;
        place-items: center;
    }

    .form-builder-choice-row__handle {
        cursor: grab;
    }

    .form-builder-choice-row__label [data-field-wrapper] {
        margin: 0;
    }

    .form-builder-choice-row__label label {
        display: none;
    }

    .form-builder-choices-drawer__empty {
        border: 1px dashed rgb(209 213 219);
        border-radius: .55rem;
        color: rgb(107 114 128);
        padding: 2rem 1rem;
        text-align: center;
        font-size: .8rem;
    }

    .form-builder-choices-drawer__footer {
        display: flex;
        justify-content: flex-start;
        border-top: 1px solid rgb(229 231 235);
        padding: .85rem 1rem;
    }

    .dark .form-builder-choices-drawer__footer {
        border-top-color: rgb(255 255 255 / .1);
    }

    .cms-sidebar-parent-item {
        position: relative;
        z-index: 1;
    }

    .cms-sidebar-parent-item:focus-within,
    .cms-sidebar-parent-item:hover {
        z-index: 2;
    }

    .cms-sidebar-submenu {
        margin-top: .25rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .75rem;
        background: rgb(255 255 255 / .98);
        padding: .35rem;
        box-shadow: 0 12px 28px rgb(15 23 42 / .12);
        direction: rtl;
    }

    .dark .cms-sidebar-submenu {
        border-color: rgb(255 255 255 / .1);
        background: rgb(17 24 39 / .98);
        box-shadow: 0 12px 28px rgb(0 0 0 / .3);
    }

    .cms-sidebar-submenu > .fi-sidebar-item {
        gap: 0;
    }

    .cms-sidebar-submenu .fi-sidebar-item-button {
        justify-content: flex-start;
    }

    .block-builder-editor {
        direction: ltr;
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(18rem, 2fr);
        gap: 1.25rem;
        align-items: start;
    }

    .block-builder-inspector,
    .block-builder-canvas {
        direction: rtl;
        overflow: hidden;
        border: 1px solid rgb(229 231 235);
        border-radius: .8rem;
        background: rgb(255 255 255);
        box-shadow: 0 1px 3px rgb(15 23 42 / .06);
    }

    .dark .block-builder-inspector,
    .dark .block-builder-canvas {
        border-color: rgb(255 255 255 / .1);
        background: rgb(255 255 255 / .04);
    }

    .block-builder-inspector {
        position: sticky;
        top: 5.5rem;
        max-height: calc(100vh - 7rem);
    }

    .block-builder-inspector__selection {
        display: flex;
        min-height: 24rem;
        max-height: calc(100vh - 7rem);
        flex-direction: column;
    }

    .block-builder-inspector__header {
        display: grid;
        gap: .15rem;
        border-bottom: 1px solid rgb(229 231 235);
        padding: 1rem;
    }

    .dark .block-builder-inspector__header {
        border-bottom-color: rgb(255 255 255 / .1);
    }

    .block-builder-inspector__header > span,
    .block-builder-inspector__header > small {
        color: rgb(107 114 128);
        font-size: .72rem;
    }

    .block-builder-inspector__header > strong {
        color: rgb(17 24 39);
        font-size: 1rem;
    }

    .dark .block-builder-inspector__header > strong {
        color: rgb(255 255 255);
    }

    .block-builder-inspector__tabs {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border-bottom: 1px solid rgb(229 231 235);
        padding: .45rem .45rem 0;
        direction: rtl;
    }

    .dark .block-builder-inspector__tabs {
        border-bottom-color: rgb(255 255 255 / .1);
    }

    .block-builder-inspector__tabs button {
        border-bottom: 2px solid transparent;
        color: rgb(107 114 128);
        padding: .7rem .35rem;
        font-size: .78rem;
        font-weight: 600;
    }

    .block-builder-inspector__tabs button.is-active {
        border-bottom-color: rgb(var(--primary-600));
        color: rgb(var(--primary-600));
    }

    .block-builder-inspector__body {
        min-height: 0;
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .block-builder-inspector__group {
        display: grid;
        gap: 1rem;
    }

    /* Keep nested repeaters visually subordinate to the selected block while
       making the currently expanded item unmistakable. This scope deliberately
       excludes repeaters elsewhere in Filament. */
    .block-builder-inspector .fi-fo-repeater {
        border-top: 1px solid rgb(229 231 235);
        padding-top: 1rem;
    }

    .dark .block-builder-inspector .fi-fo-repeater {
        border-top-color: rgb(255 255 255 / .1);
    }

    .block-builder-inspector .fi-fo-repeater > ul > .fi-grid {
        gap: .85rem;
    }

    .block-builder-inspector .fi-fo-repeater-item {
        overflow: hidden;
        border-radius: .75rem;
        background: rgb(255 255 255);
        box-shadow: 0 0 0 1px rgb(209 213 219);
        transition: background-color .15s ease, box-shadow .15s ease;
    }

    .dark .block-builder-inspector .fi-fo-repeater-item {
        background: rgb(17 24 39);
        box-shadow: 0 0 0 1px rgb(255 255 255 / .14);
    }

    .block-builder-inspector .fi-fo-repeater-item-header {
        min-width: 0;
        background: rgb(255 255 255);
        transition: background-color .15s ease, color .15s ease;
    }

    .dark .block-builder-inspector .fi-fo-repeater-item-header {
        background: rgb(17 24 39);
    }

    .block-builder-inspector .fi-fo-repeater-item.fi-collapsed:hover {
        background: rgb(var(--primary-50));
        box-shadow: 0 0 0 1px rgb(var(--primary-300));
    }

    .block-builder-inspector .fi-fo-repeater-item.fi-collapsed:hover > .fi-fo-repeater-item-header,
    .block-builder-inspector .fi-fo-repeater-item.fi-collapsed:focus-within > .fi-fo-repeater-item-header {
        background: rgb(var(--primary-50));
    }

    .dark .block-builder-inspector .fi-fo-repeater-item.fi-collapsed:hover,
    .dark .block-builder-inspector .fi-fo-repeater-item.fi-collapsed:focus-within > .fi-fo-repeater-item-header {
        background: rgb(var(--primary-950) / .28);
    }

    .block-builder-inspector .fi-fo-repeater-item:focus-within {
        box-shadow: 0 0 0 2px rgb(var(--primary-500));
    }

    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) {
        box-shadow: 0 0 0 2px rgb(var(--primary-600));
    }

    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) > .fi-fo-repeater-item-header {
        background: rgb(var(--primary-600));
        color: rgb(255 255 255);
    }

    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) > .fi-fo-repeater-item-header h4,
    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) > .fi-fo-repeater-item-header button,
    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) > .fi-fo-repeater-item-header svg {
        color: rgb(255 255 255);
    }

    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) > .fi-fo-repeater-item-header button:hover,
    .block-builder-inspector .fi-fo-repeater-item:not(.fi-collapsed) > .fi-fo-repeater-item-header button:focus-visible {
        background: rgb(255 255 255 / .16);
    }

    .block-builder-inspector .fi-fo-repeater-item-content {
        background: rgb(255 255 255);
        padding: 1.25rem;
    }

    .dark .block-builder-inspector .fi-fo-repeater-item-content {
        background: rgb(17 24 39);
    }

    .block-builder-inspector .fi-fo-repeater > .fi-ac {
        margin-top: .25rem;
    }

    .page-history {
        display: grid;
        gap: 1rem;
        min-height: 24rem;
    }

    .page-history__tabs {
        position: sticky;
        top: 0;
        z-index: 1;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border-bottom: 1px solid rgb(229 231 235);
        background: rgb(255 255 255);
    }

    .dark .page-history__tabs {
        border-bottom-color: rgb(255 255 255 / .1);
        background: rgb(17 24 39);
    }

    .page-history__tabs button {
        border-bottom: 2px solid transparent;
        padding: .75rem;
        color: rgb(107 114 128);
        font-weight: 600;
    }

    .page-history__tabs button.is-active {
        border-bottom-color: rgb(var(--primary-600));
        color: rgb(var(--primary-600));
    }

    .page-history__panel,
    .page-history__list {
        display: grid;
        gap: .65rem;
    }

    .page-history__toolbar {
        display: flex;
        gap: .5rem;
    }

    .page-history__toolbar button,
    .page-history__apply,
    .page-history__load-more {
        border-radius: .5rem;
        padding: .55rem .8rem;
        background: rgb(var(--primary-600));
        color: white;
        font-weight: 600;
    }

    .page-history__toolbar button:disabled,
    .page-history__apply:disabled {
        cursor: not-allowed;
        opacity: .45;
    }

    .page-history__load-more {
        justify-self: center;
        background: rgb(var(--primary-50));
        color: rgb(var(--primary-700));
    }

    .page-history__entry {
        display: grid;
        gap: .2rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .65rem;
        padding: .75rem;
        text-align: start;
    }

    .page-history__entry:hover,
    .page-history__entry:focus-visible {
        border-color: rgb(var(--primary-400));
        background: rgb(var(--primary-50));
    }

    .page-history__entry.is-selected {
        border-color: rgb(var(--primary-600));
        box-shadow: 0 0 0 1px rgb(var(--primary-600));
    }

    .page-history__entry small,
    .page-history__entry time,
    .page-history__empty {
        color: rgb(107 114 128);
        font-size: .75rem;
    }

    .page-history__entry strong {
        color: rgb(var(--primary-600));
        font-size: .78rem;
    }

    .block-builder-inspector__empty,
    .block-builder-inspector__empty-tab,
    .block-builder-canvas__empty {
        color: rgb(107 114 128);
        text-align: center;
    }

    .block-builder-inspector__empty {
        display: grid;
        min-height: 24rem;
        place-content: center;
        place-items: center;
        gap: .5rem;
        padding: 2rem;
    }

    .block-builder-inspector__empty svg,
    .block-builder-canvas__empty svg {
        width: 2.25rem;
    }

    .block-builder-inspector__empty-tab {
        border: 1px dashed rgb(209 213 219);
        border-radius: .6rem;
        padding: 1.25rem;
        font-size: .78rem;
    }

    .block-builder-canvas {
        min-height: 32rem;
        padding: .75rem;
    }

    .block-builder-canvas__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .block-builder-canvas__header h3 {
        color: rgb(17 24 39);
        font-weight: 700;
    }

    .dark .block-builder-canvas__header h3 {
        color: rgb(255 255 255);
    }

    .block-builder-canvas__header p,
    .block-builder-canvas__header > span {
        color: rgb(107 114 128);
        font-size: .75rem;
    }

    .block-builder-canvas__items {
        display: grid;
        gap: .5rem;
        align-content: start;
        min-height: 22rem;
    }

    .block-builder-card {
        display: grid;
        grid-template-columns: 1.75rem 2rem minmax(0, 1fr) auto;
        align-items: center;
        gap: .5rem;
        min-height: 3.5rem;
        border: 1px solid rgb(229 231 235);
        border-radius: .7rem;
        background: rgb(249 250 251);
        padding: .55rem;
        cursor: pointer;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }

    .dark .block-builder-card {
        border-color: rgb(255 255 255 / .1);
        background: rgb(17 24 39 / .45);
    }

    .block-builder-card:hover,
    .block-builder-card.is-selected {
        border-color: rgb(var(--primary-500));
        background: rgb(var(--primary-50));
        box-shadow: 0 0 0 2px rgb(var(--primary-500) / .12);
    }

    .dark .block-builder-card:hover,
    .dark .block-builder-card.is-selected {
        background: rgb(var(--primary-950) / .28);
    }

    .block-builder-card__handle,
    .block-builder-card__icon {
        display: grid;
        place-items: center;
        color: rgb(107 114 128);
    }

    .block-builder-card__handle {
        cursor: grab;
    }

    .block-builder-card__icon {
        width: 2rem;
        height: 2rem;
        border-radius: .55rem;
        background: rgb(229 231 235);
    }

    .dark .block-builder-card__icon {
        background: rgb(255 255 255 / .08);
    }

    .block-builder-card__handle svg,
    .block-builder-card__icon svg {
        width: 1.15rem;
    }

    .block-builder-card__identity {
        display: grid;
        min-width: 0;
    }

    .block-builder-card__identity strong {
        overflow: hidden;
        color: rgb(17 24 39);
        font-size: .86rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dark .block-builder-card__identity strong {
        color: rgb(255 255 255);
    }

    .block-builder-card__identity small {
        color: rgb(107 114 128);
        font-size: .7rem;
    }

    .block-builder-card__actions {
        display: flex;
        align-items: center;
        gap: .15rem;
    }

    .block-builder-canvas__empty {
        display: grid;
        min-height: 22rem;
        place-content: center;
        place-items: center;
        gap: .6rem;
    }

    .block-builder-canvas__footer {
        display: flex;
        justify-content: center;
        border-top: 1px solid rgb(229 231 235);
        margin-top: 1rem;
        padding-top: 1rem;
    }

    .dark .block-builder-canvas__footer {
        border-top-color: rgb(255 255 255 / .1);
    }

    @media (max-width: 1023px) {
        .form-builder-editor,
        .block-builder-editor {
            grid-template-areas: "inspector" "canvas";
            grid-template-columns: minmax(0, 1fr);
        }

        .form-builder-inspector {
            position: static;
            max-height: 34rem;
        }

        .form-builder-inspector__body {
            max-height: 29rem;
        }

        .block-builder-inspector {
            position: static;
            max-height: 36rem;
        }

        .block-builder-inspector__selection {
            max-height: 36rem;
        }
    }

    @media (max-width: 639px) {
        .block-builder-inspector .fi-fo-repeater-item-header {
            gap: .5rem;
            padding: .75rem;
        }

        .block-builder-inspector .fi-fo-repeater-item-header h4 {
            min-width: 0;
            flex: 1;
        }

        .block-builder-inspector .fi-fo-repeater-item-header ul {
            flex-shrink: 0;
            gap: .35rem;
        }

        .block-builder-inspector .fi-fo-repeater-item-content {
            padding: 1rem;
        }
    }

    @media (max-width: 639px) {
        .form-builder-palette__items {
            grid-template-columns: 1fr;
        }

        .form-builder-card__topline {
            align-items: flex-start;
            flex-wrap: wrap;
        }
    }

    @media (min-width: 768px) {
        .fi-page-editor-locked-scroll .fi-resource-pages form#form .fi-form-actions {
            margin-inline: 0;
            border-radius: 0.75rem 0.75rem 0 0;
        }
    }
    .activity-creation-wizard-modal {
        direction: rtl;
        overflow: hidden;
        box-shadow: 0 24px 70px rgb(var(--primary-950) / .16) !important;
    }

    .activity-creation-wizard-modal .fi-fo-wizard-header {
        background: rgb(var(--primary-50) / .55);
    }

    .dark .activity-creation-wizard-modal .fi-fo-wizard-header {
        background: rgb(var(--primary-950) / .2);
    }

    .activity-creation-wizard-modal .fi-fo-wizard-step {
        min-height: 18rem;
    }

    .activity-wizard-summary {
        display: grid;
        gap: .45rem;
        border: 1px solid rgb(var(--primary-200));
        border-radius: .75rem;
        background: rgb(var(--primary-50) / .7);
        color: rgb(var(--primary-950));
        padding: 1rem;
    }

    .activity-wizard-summary strong {
        font-size: 1rem;
    }

    .activity-wizard-summary span,
    .activity-wizard-hint {
        font-size: .8rem;
    }

    .dark .activity-wizard-summary {
        border-color: rgb(var(--primary-800));
        background: rgb(var(--primary-950) / .3);
        color: rgb(var(--primary-100));
    }

    @media (max-width: 639px) {
        .activity-creation-wizard-modal {
            width: 100vw !important;
            max-width: 100vw !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            border-radius: 0 !important;
        }

        .activity-creation-wizard-modal .fi-fo-wizard-step {
            min-height: calc(100dvh - 18rem);
        }
    }
</style>

<script>
    (() => {
        if (window.__internalLinkSearchBound) {
            return;
        }

        window.__internalLinkSearchBound = true;

        const endpoint = @json(\Illuminate\Support\Facades\Route::has('admin.internal-links.search') ? route('admin.internal-links.search') : url('/admin/internal-links/search'));
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
