<style>
    .king-media-management-modal { max-width: 56rem; max-height: calc(100dvh - 2rem); overflow: hidden; }
    .king-media-management-modal .fi-modal-header { flex: none; padding: 1rem 1.25rem 0; }
    .king-media-management-modal .fi-modal-heading { font-size: 1.125rem; line-height: 1.6; }
    .king-media-management-modal .fi-modal-content { min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 1rem 1.25rem; }
    .king-media-management-modal .fi-modal-content > .fi-fo-component-ctn,
    .king-media-replacement-section > .fi-fo-component-ctn { gap: 1rem; }
    .king-media-management-modal .fi-modal-footer { flex: none; padding: .75rem 1.25rem; z-index: 1; }
    .king-media-management-modal .fi-modal-footer-actions { display: flex; flex-direction: row; justify-content: space-between; gap: .75rem; }
    .king-media-management-modal .fi-modal-footer-actions > * { flex: none; width: auto; min-height: 2.75rem; }
    .king-media-management-modal .fi-modal-close-btn { min-width: 2.75rem; min-height: 2.75rem; }
    .king-media-dependencies { display: grid; gap: .75rem; min-width: 0; }
    .king-media-source { display: flex; align-items: center; gap: .75rem; min-width: 0; border-radius: .75rem; background: rgba(var(--gray-500), .06); padding: .75rem; }
    .king-media-source-thumbnail { width: 4rem; height: 4rem; flex: none; object-fit: cover; border-radius: .5rem; }
    .king-media-source-copy { display: grid; gap: .375rem; min-width: 0; overflow-wrap: anywhere; }
    .king-media-source-copy strong { font-size: .9375rem; line-height: 1.7; }
    .king-media-source-copy p { display: flex; align-items: center; flex-wrap: wrap; gap: .25rem; }
    .king-media-section-heading { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; font-size: 1rem; line-height: 1.5; font-weight: 600; }
    .king-media-section-heading > svg { width: 1.25rem; height: 1.25rem; flex: none; color: rgb(var(--primary-600)); }
    .king-media-usage-list { min-width: 0; max-height: 13rem; overflow-y: auto; overscroll-behavior: contain; border: 1px solid rgba(var(--gray-500), .25); border-radius: .75rem; }
    .king-media-usage-list:focus-visible { outline: 2px solid rgb(var(--primary-500)); outline-offset: 2px; }
    .king-media-usage-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(5rem, auto) auto; gap: .5rem .75rem; align-items: center; padding: .5rem .75rem; }
    .king-media-usage-row + .king-media-usage-row { border-top: 1px solid rgba(var(--gray-500), .18); }
    .king-media-usage-title { display: flex; align-items: start; gap: .5rem; min-width: 0; overflow-wrap: anywhere; font-size: .875rem; font-weight: 500; line-height: 1.7; }
    .king-media-usage-title > svg { width: 1.25rem; height: 1.25rem; flex: none; color: rgb(var(--gray-500)); }
    .king-media-usage-kind { min-width: 0; }
    .king-media-usage-kind .fi-badge { white-space: normal; overflow-wrap: anywhere; }
    .king-media-usage-kind .truncate { white-space: normal; overflow: visible; text-overflow: clip; }
    .king-media-usage-links { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
    .king-media-usage-links a { min-height: 2.25rem; }
    .king-media-usage-note { grid-column: 1 / -1; overflow-wrap: anywhere; }
    .king-media-replacement-hint { margin-top: .375rem; line-height: 1.7; }
    .king-media-replacement-controls { border: 1px solid rgba(var(--gray-500), .25); border-radius: .75rem; padding: .75rem; }
    .king-media-replacement-controls > .fi-fo-component-ctn { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: center; gap: 1rem; }
    .king-media-replacement-controls > .fi-fo-component-ctn > * { min-width: 0; grid-column: auto !important; }
    .king-media-usage-options { gap: .5rem .75rem; max-height: 13rem; overflow-y: auto; overscroll-behavior: contain; padding-inline-end: .25rem; }
    .king-media-usage-options label { align-items: center; min-height: 2.5rem; overflow-wrap: anywhere; }
    .king-media-picker-compact { display: flex; align-items: center; justify-content: flex-end; gap: .5rem; min-width: 0; }
    .king-media-picker-compact > .fi-btn { flex: none; min-height: 2.75rem; max-width: 100%; }
    .king-media-picker-selection { min-width: 0; }
    .king-media-picker-selection:empty { display: none; }
    .king-media-picker-thumbnail { display: flex; align-items: center; gap: .5rem; min-width: 0; }
    .king-media-picker-thumbnail img { width: 2.5rem; height: 2.5rem; border-radius: .375rem; object-fit: cover; flex: none; }
    .king-media-picker-thumbnail span { min-width: 0; max-width: 5.5rem; overflow-wrap: anywhere; font-size: .75rem; line-height: 1.6; }
    .king-media-picker-thumbnail button { flex: none; min-width: 2rem; min-height: 2.5rem; }
    .king-media-management-modal .king-media-operation { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem 1rem; border: 1px solid rgba(var(--gray-500), .25); border-radius: .75rem; padding: .5rem .75rem; }
    .king-media-operation label { align-items: center; min-height: 2.5rem; }
    .king-media-operation input { flex: none; margin-top: 0; }
    .king-media-management-modal .fi-fo-field-wrp-label { font-weight: 600; }
    @media (max-width: 639px) {
        .king-media-management-modal .fi-modal-header { padding: .75rem 1rem 0; }
        .king-media-management-modal .fi-modal-content { padding: .75rem 1rem; }
        .king-media-management-modal .fi-modal-footer { padding: .75rem 1rem; }
        .king-media-source-thumbnail { width: 3.5rem; height: 3.5rem; }
        .king-media-usage-row { grid-template-columns: minmax(0, 1fr) auto; gap: .5rem; }
        .king-media-usage-title { grid-column: 1 / -1; }
        .king-media-usage-list { max-height: min(20rem, 44dvh); }
        .king-media-usage-links a { min-height: 2.75rem; }
        .king-media-replacement-controls > .fi-fo-component-ctn { grid-template-columns: minmax(0, 1fr); gap: .75rem; }
        .king-media-picker-compact { flex-wrap: wrap; justify-content: flex-start; }
        .king-media-picker-thumbnail span { max-width: none; }
        .king-media-management-modal .king-media-operation { grid-template-columns: minmax(0, 1fr); }
    }
</style>
