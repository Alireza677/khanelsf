# Production Save Debugging

TEMP DEBUG - remove after production save issue is fixed.

This project now has temporary diagnostics for Filament/Livewire save failures on production. The added logging avoids request values and records only safe keys, text lengths, request metadata, exception metadata, and timings.

## Server Commands

Run these after deploying the code:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan filament:clear-cached-components
php artisan route:clear
php artisan config:clear
```

If storage logs are not written, fix permissions:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

On shared hosting, replace `www-data:www-data` with the PHP-FPM/web server user and group for the site.

## Tables To Verify

The existing migrations include the database-backed tables required by the current `.env` settings:

- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `failed_jobs`

Verify on production:

```bash
php artisan migrate:status
php artisan tinker
Schema::hasTable('sessions');
Schema::hasTable('cache');
Schema::hasTable('cache_locks');
Schema::hasTable('jobs');
Schema::hasTable('failed_jobs');
```

## Log File

Watch the Laravel log while testing:

```bash
tail -f storage/logs/laravel.log
```

If your production logging is daily, watch the current daily file instead:

```bash
tail -f storage/logs/laravel-$(date +%F).log
```

## Browser Tests

1. Log in as an admin.
2. Open `/admin/debug/log-test`.
3. Confirm the JSON response says `ok: true`.
4. Confirm `TEMP DEBUG - Server log test` appears in the Laravel log.
5. Open browser DevTools Network tab.
6. Edit and save a short Page/Post.
7. Edit and save the long content that fails on production.
8. Inspect the `/livewire/update` request status, response body, request payload size, and timing.
9. Compare log entries:
   - `TEMP DEBUG - Livewire update request started`
   - `TEMP DEBUG - Filament save started`
   - `TEMP DEBUG - Filament save completed`
   - `TEMP DEBUG - Filament save failed`
   - `TEMP DEBUG - global throwable reported`

## Production `.env` Recommendation

Use this after diagnostics are finished:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alireza-ameri.ir

LOG_CHANNEL=stack
LOG_LEVEL=error

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database
```

During debugging, keep `LOG_LEVEL=debug`. After the issue is fixed, change it back to `error` or your normal production level.

Do not use `SESSION_DOMAIN=null` in production. Leave it empty as `SESSION_DOMAIN=` or omit it, unless you explicitly need a shared cookie domain such as `.alireza-ameri.ir`.

## Remove Temporary Logging

After finding the production save issue:

1. Remove `LogLivewireRequests::class` and the exception report callback from `bootstrap/app.php`.
2. Remove `/admin/debug/log-test` from `routes/web.php`.
3. Remove `LogsFilamentCreateDebug` / `LogsFilamentEditDebug` use statements and trait usages from Filament create/edit pages.
4. Remove the settings save debug wrapper from `app/Filament/Pages/ManageSiteSettings.php`.
5. Delete:
   - `app/Support/TemporaryDebugLogger.php`
   - `app/Http/Middleware/LogLivewireRequests.php`
   - `app/Filament/Resources/Concerns/LogsFilamentCreateDebug.php`
   - `app/Filament/Resources/Concerns/LogsFilamentEditDebug.php`
6. Keep `database/migrations/2026_06_25_000000_expand_content_text_columns.php` applied. Rolling it back is intentionally disabled to avoid truncating production content.
7. Set `APP_ENV=production`, `APP_DEBUG=false`, and `LOG_LEVEL=error`.
