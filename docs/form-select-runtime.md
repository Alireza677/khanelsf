# Shared Select layout and overlay correction

## Source-confirmed causes

- The later `.form-card button { width: fit-content; }` rule beat the single-class `.form-select__trigger { width: 100%; }` rule on specificity. As a result, the visible control shrank to the selected text/placeholder even when its field occupied all 12 grid columns. No change to stored column spans was needed.
- The dropdown remained absolutely positioned inside `.form-action-modal__panel`, whose `overflow-y: auto` and bounded height create a scrolling/clipping container. Out-of-flow positioning does not prevent descendants from enlarging scrollable overflow; increasing z-index does not escape that clipping container.
- The previous positioning used only viewport height, ignored visual viewport offsets/keyboard changes, imposed a 120px minimum even when less room existed, and did not reposition on ancestor scrolling. `scrollIntoView()` on a result could also scroll ancestor containers.
- The location columns used `auto-fit` with a minimum width rather than explicit equal columns and a mobile breakpoint. Its Builder preview used a separate native/search implementation.

## Current behavior

`resources/js/components/form-selects.js` and `resources/css/components/form-selects.css` are shared between the public app and Builder previews. The public entry imports them; the existing Filament theme loads their Vite entries. Deploy with the normal `npm run build` so the new shared entries are present in the manifest.

Native selects, names, values, required flags, disabled state and native change/input events remain the submission contract. Only presentation is enhanced. Both standard Select and Iran province/city Builder previews use the shared runtime; preview controls have no submitted name. Existing Filament settings/Choices controls retain their own existing overlay integration.

- Controls fill their allocated column; specific trigger rules beat the general form-button width without changing other buttons or inputs. Province/city columns are equal on desktop and stack at 640px and below.
- Opening moves the list into a fixed portal. The Popover API, when available, puts that portal in the browser top layer; the fallback portal uses a high stacking level. Native dialog owners retain their portal as a descendant to preserve modal interaction.
- Positioning follows the visual viewport, including offsets, resize, keyboard and scrolling. Desktop lists open below or above; mobile and short viewports use a centered selection dialog constrained to the visible part of the owning modal or page.
- Search stays above the results. Only the results scroll; keyboard navigation adjusts that list's `scrollTop` without scrolling the form. Centered mode blocks scrolling through its backdrop and keeps Tab inside the selection dialog.
- Click outside/Escape close the list; selection updates the native select and closes, restoring focus. Ordinary dropdown Tab navigation returns to the form's control order. Only one shared dropdown stays open, and removing/navigating away from a form removes its portal and observers.
- Native option changes/disabled states are observed, so dependent cities and Alpine/Livewire previews keep the displayed value/options synchronized.

## Changed files

- New shared JS/CSS: `resources/js/components/form-selects.js`, `resources/css/components/form-selects.css`.
- Public entry points: `resources/js/app.js`, `resources/css/app.css`, `resources/js/components/form-iran-locations.js` (re-exports the existing Persian search normalization from the shared module).
- Builder: `resources/views/filament/theme.blade.php`, `resources/views/filament/forms/components/form-builder-editor.blade.php`, `iran-location-preview.blade.php`, and new `form-select-preview.blade.php` alongside it.
- Build entries: `vite.config.js`.
- Focused checks: new `tests/js/form-selects.test.js`, updated rendering checks in `tests/Unit/IranProvinceCityFieldTest.php`, and updated source-contract paths in `tests/Feature/FormSelectPresentationTest.php`.

No schema, backend validation, storage, Submission, Lead, scoring, eligibility, routes, tables or migrations are changed. Browser/visual verification is left for the user's manual review; source checks and successful builds are not a browser test.
