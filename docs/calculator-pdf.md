# Calculator PDF rendering

## Audit and scope

LOCAL is Windows with `php artisan serve` and locally installed Google Chrome. Docker, WSL, Linux and Apache are not required.

Installed dependencies: `chrome-php/chrome` **1.16.1**, with new transitive dependencies `chrome-php/wrench` **1.9.2**, `evenement/evenement` **3.0.2** and `symfony/filesystem` **7.4.18**. The latter was resolved within 7.4 to preserve the project's PHP 8.2 baseline. Existing packages, including `spatie/laravel-pdf` **2.13.1**, were not upgraded or removed.

The previous implementation used `spatie/laravel-pdf` 2.13.1 but hard-coded `->driver('gotenberg')`. Its error message named Gotenberg and only caught HTTP/package exceptions. `chrome-php/chrome` was absent. The dedicated Blade already embedded local Regular/Bold Vazirmatn fonts and CSS, with native RTL and print rules; it needed no redesign.

The installed Spatie `ChromeDriver.php` and package config support Chrome, background printing and readiness expressions. Chrome PHP does not need Node or Puppeteer. Its Windows auto-discovery uses `CHROME_PATH` if present, otherwise the Chrome App Paths registry entry under HKLM, with an upstream Program Files fallback. Nonstandard/per-user installations may need the official binary setting below.

## Rendering path

Calculator snapshots → dedicated Blade → `spatie/laravel-pdf` → configured driver → Chromium → PDF attachment.

`CalculatorSubmissionReport::download()` renders the existing Blade and passes its HTML to Spatie. It does not choose a driver or send HTTP requests itself. `config('laravel-pdf.driver')` selects the renderer; the local default is `chrome`. Gotenberg is an optional future deployment choice.

The unchanged `data()` reads stored `payload`/answer snapshots and `calculation_result`, using `CalculationResultRows` for presentation and `SettingsService` for branding. No recalculation or database write is involved.

Route: `GET /forms/submissions/{submission}/calculator-report`, named `forms.submissions.calculator-report`, with unchanged `signed` middleware. Use the existing Calculator result link or Filament report action. Unsigned requests return 403; missing calculation results return 404. Downloads are named `calculator-report-{id}.pdf` and are not stored permanently.

Renderer errors are reported through Laravel as `Calculator PDF renderer failed`, with configured driver, submission ID and original exception, then returned as HTTP 503 with the existing Persian message. Data preparation and Blade rendering happen outside the renderer catch, preserving ordinary application error reporting. There is no silent Dompdf fallback.

## Windows local setup

Install Chrome/Chromium and enable PHP's `sockets` extension, required by `chrome-php/wrench`. Run `php --ini` to locate the CLI configuration, enable `extension=sockets`, then verify with `php --ri sockets`. Windows needs the matching `php_sockets.dll` in PHP's extension directory. Do not bypass Composer platform requirements.

```sh
composer install
composer check-platform-reqs --no-dev
```

Set in `.env`:

```dotenv
LARAVEL_PDF_DRIVER=chrome
```

Leave the binary setting absent for auto-discovery. If discovery fails, set the actual path for your installation. This is a documentation example, not a repository default:

```dotenv
LARAVEL_PDF_CHROME_BINARY="C:/Program Files/Google/Chrome/Application/chrome.exe"
```

No machine-specific executable or profile path is committed in application config or `.env.example`. After changing settings, clear config and restart an already-running development server so it picks up PHP extension/environment changes:

```sh
php artisan config:clear
php artisan serve
```

The official Chrome settings are exposed in `config/laravel-pdf.php`:

| Environment variable | Key under `chrome` | Default / units |
| --- | --- | --- |
| `LARAVEL_PDF_CHROME_BINARY` | `chrome_binary` | Auto-discover |
| `LARAVEL_PDF_CHROME_NO_SANDBOX` | `no_sandbox` | `false` |
| `LARAVEL_PDF_CHROME_STARTUP_TIMEOUT` | `startup_timeout` | 30 seconds |
| `LARAVEL_PDF_CHROME_TIMEOUT` | `timeout` | 30000 milliseconds |
| `LARAVEL_PDF_CHROME_OPERATION_TIMEOUT` | `operation_timeout` | 5000 milliseconds |
| `LARAVEL_PDF_CHROME_USER_DATA_DIR` | `user_data_dir` | Library-managed temporary profile |

Official `custom_flags` and `env_variables` arrays are empty. Custom flags, a personal profile or disabling the sandbox are not required for this report.

## Assets, RTL and printing

`resources/views/reports/calculator-submission.blade.php` is unchanged in this migration. It embeds `resources/fonts/vazirmatn/Vazirmatn-Regular.ttf` (400) and `Vazirmatn-Bold.ttf` (700) as data URLs, with inline CSS. No CDN, Google Fonts, public internet or HTTP asset request back to `127.0.0.1:8000` is needed. This avoids waiting for assets on the same `artisan serve` process that is generating the PDF. Future logos/assets can likewise be embedded from local files; no new asset feature is added. Chrome's local DevTools connection controls the browser and does not fetch assets from Laravel.

Native `<html lang="fa" dir="rtl">`, bidi markup, A4, 10mm top/right/left and 12mm bottom margins, exact print colors and break avoidance are preserved. Spatie's Chrome driver sets `printBackground=true`. Oversized sections can still split across pages.

`waitUntilReady("document.fonts.status === 'loaded'", timeout: 30000)` is retained. The installed driver supports readiness evaluation and JavaScript; future charts can use an appropriate readiness expression. Grid, flex, SVG, header/footer and page numbering remain possible through Blade and the builder. No speculative features are added. This package version stops polling at the readiness deadline without throwing a readiness-specific exception; the wait is not a strict readiness guarantee.

## Optional future Gotenberg deployment

The existing Gotenberg config and optional env examples remain available. They are not requirements for LOCAL:

```dotenv
LARAVEL_PDF_DRIVER=gotenberg
GOTENBERG_URL=http://gotenberg:3000
# GOTENBERG_USERNAME=
# GOTENBERG_PASSWORD=
```

Use a reachable private endpoint. The hostname above assumes a shared container network. The previously documented image is pinned to `gotenberg/gotenberg:8.37.0`. No Docker/Gotenberg service was started for this change. Switching config requires no Calculator code changes.

`InvoicePdfGenerator`, `invoices.pdf`, Dompdf and `PersianPdfHtml` remain in use for invoices, regardless of the Spatie driver setting.

Deployment commands are `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan config:cache` and `php artisan view:cache`. Chrome needs a local executable, sockets, process execution and temporary-directory access; Gotenberg needs its reachable service. No database migration, seed, frontend build or route change is required.

## Verification

Verification is limited to Composer/platform compatibility, syntax/config, existing Calculator report tests and one real Chrome smoke generation where available. The automated download test mocks the configured Chrome driver to avoid requiring a browser in CI. It checks snapshots, embedded fonts, A4/margins/readiness, the download contract and absence of HTTP assets. Actual Chromium rendering is verified separately.

Completed on Windows: PHP syntax, Composer validation/platform checks, package discovery and `php artisan config:clear` passed. The existing report test file passed **5 tests / 33 assertions**. Chrome was auto-discovered successfully without an explicit binary setting. A real Calculator report was generated through the service with an unsaved sample snapshot and no database writes: `output/pdf/calculator-chrome-smoke.pdf` (62,663 bytes, one A4 page). Poppler confirmed both Vazirmatn font weights were embedded; a single visual check confirmed readable Persian and mixed LSF/numeric content. This was a service-level smoke check, not an HTTP request through `artisan serve`.

A separate lightweight check simulated a Chrome startup exception and confirmed HTTP 503 plus the configured driver and original exception in error reporting. Temporary check scripts were removed. The local PHP sockets extension was enabled with a backup of the active `php.ini`; an already-running `artisan serve` process must be restarted to load that extension. Docker and Gotenberg were not run. The full test suite was not run.

Calculator schema, answers, scoring, eligibility, winner, percentages, result calculation, database schema and snapshot/route contracts are unchanged. No unrelated dependencies were removed or upgraded, and no redesign was performed.

References: [official Chrome driver](https://spatie.be/docs/laravel-pdf/v2/drivers/using-the-chrome-driver), [official configuration](https://spatie.be/docs/laravel-pdf/v2/drivers/configuration), [Chrome PHP source](https://github.com/chrome-php/chrome).
