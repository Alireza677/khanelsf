# Starter CMS

A minimal Laravel 11 starter CMS prepared for public pages, blog posts, categories, contact messages, menus, site settings, Filament admin, and Spatie Media Library.

Composer package downloads were not reachable from this environment, so `vendor/` is not installed. Run the commands below from this directory when Packagist is reachable.

## 1. Create Or Install The Project

If starting from a clean folder, the normal Laravel command is:

```bash
composer create-project laravel/laravel starter-cms "11.*"
cd starter-cms
```

This repository already contains a minimal Laravel 11 skeleton, so install dependencies here with:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

What this does:

- `composer install` downloads Laravel 11, Filament, and Media Library from `composer.json`.
- `.env` stores local secrets and database credentials.
- `key:generate` sets `APP_KEY`, which Laravel needs for encrypted cookies and sessions.

## 2. MySQL Configuration

Create a MySQL database:

```sql
CREATE DATABASE starter_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set these values in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=starter_cms
DB_USERNAME=root
DB_PASSWORD=
```

What this changes:

- `config/database.php` uses MySQL by default.
- Session, cache, and queue settings are database-backed for a simple local/dev setup.

## 3. Install Filament Admin Panel

For a fresh Laravel app, run:

```bash
composer require filament/filament:"^3.2" -W
php artisan filament:install --panels
```

This skeleton already includes `app/Providers/Filament/AdminPanelProvider.php` and `bootstrap/providers.php`, which registers an `/admin` panel with login enabled.

Create CMS resources after dependencies are installed:

```bash
php artisan make:filament-resource Page --generate
php artisan make:filament-resource Category --generate
php artisan make:filament-resource Post --generate
php artisan make:filament-resource ContactMessage --generate
php artisan make:filament-resource Menu --generate
php artisan make:filament-resource MenuItem --generate
php artisan make:filament-resource Setting --generate
```

What this does:

- Filament provides the admin UI.
- Generated resources create practical CRUD screens for the CMS models.
- `User::canAccessPanel()` only allows users with `is_admin = true` into the admin panel.

## 4. Install And Publish Media Library

For a fresh Laravel app, run:

```bash
composer require spatie/laravel-medialibrary:"^11.0"
composer require filament/spatie-laravel-media-library-plugin:"^3.2"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
```

Then make public uploads accessible:

```bash
php artisan storage:link
```

What this does:

- Media Library adds a `media` table for uploaded files.
- The published config lets you adjust disk, conversions, queueing, and path behavior later.
- `Page` and `Post` implement `HasMedia` so each can hold one featured image.
- Featured images use the `featured_image` collection on the `public` disk.
- A small non-queued `thumb` conversion is available for admin listings and compact previews.
- Filament resources use `SpatieMediaLibraryFileUpload` and `SpatieMediaLibraryImageColumn` from the official Filament plugin.

Attach a featured image:

```php
$post
    ->addMedia($request->file('featured_image'))
    ->toMediaCollection('featured_image');
```

Retrieve media:

```php
$media = $post->featuredImage();
$url = $post->featuredImageUrl();
$thumbUrl = $post->featuredImageUrl('thumb');
```

Display media in Blade:

```blade
@if ($post->featuredImageUrl())
    <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->title }}">
@endif
```

If you later install Filament's Media Library upload plugin, its form field can target the same collection:

```php
SpatieMediaLibraryFileUpload::make('featured_image')
    ->collection('featured_image')
    ->image()
    ->imagePreviewHeight('160')
```

## 5. Run Migrations And Seed Data

```bash
php artisan migrate
php artisan db:seed
```

What this creates:

- Laravel tables: `users`, `sessions`, `cache`, `jobs`, failed jobs, and password reset tokens.
- CMS tables: `pages`, `categories`, `posts`, `contact_messages`, `menus`, `menu_items`, and `settings`.
- Media Library table: `media`, after publishing its migration.
- A default admin user: `admin@example.com` / `password`.
- A published `home` page.

Change the admin password immediately in any real environment.

## 6. Run The App

```bash
php artisan serve
```

Open:

- Public site: `http://127.0.0.1:8000`
- Blog index: `http://127.0.0.1:8000/blog`
- Admin panel: `http://127.0.0.1:8000/admin`

## 7. Run Tests

The starter includes PHPUnit feature tests for public routes, SEO tags, sitemap, robots.txt, admin draft preview, settings fallbacks, theme variables, and the contact form.

Run:

```bash
php artisan test
```

The test suite uses SQLite in memory via `phpunit.xml`, so it does not touch your local MySQL database.

Useful checks before handing a client starter to production:

```bash
php artisan migrate
php artisan db:seed
php artisan route:list --except-vendor
npm run build
php artisan test
```

## Reserved Public Slugs And Paths

Avoid creating normal pages with these slugs or paths unless you intentionally want to replace the matching feature:

- `home`: editable homepage record loaded at `/`
- `blog`: blog index at `/blog`
- `blog/search`: blog search
- `blog/category/{slug}`: blog category archive
- `contact`: contact page at `/contact`
- `projects`: projects index at `/projects`
- `projects/category/{slug}`: project category archive
- `galleries`: galleries index at `/galleries`
- `galleries/category/{slug}`: gallery category archive
- `shop`: shop index at `/shop`
- `shop/category/{slug}`: product category archive
- `cart`: session cart at `/cart`
- `checkout`: checkout at `/checkout`
- `health`: safe JSON health check at `/health`
- `sitemap.xml`: XML sitemap
- `robots.txt`: robots response

Admin-only draft preview routes live under `/admin/preview/...` and require authentication.

## Homepage Strategy

The homepage is managed as a normal `Page` record with the reserved slug `home`.

This keeps the CMS simple:

- Editors update homepage title, content, SEO fields, status, and featured image from the Pages admin resource.
- `HomeController` loads the published page where `slug = home`.
- No separate homepage settings table, page builder, API, or custom content layer is needed.

Seed data creates the initial homepage:

```php
Page::query()->firstOrCreate(
    ['slug' => 'home'],
    [
        'title' => 'Home',
        'content' => '<p>Welcome to the starter CMS.</p>',
        'template' => 'home',
        'status' => 'published',
    ],
);
```

For future client projects, keep this convention unless the homepage genuinely needs structured blocks. Add fields or a simple template-specific view only when the design requires it.

## Simple SEO

SEO support is intentionally lightweight and uses existing CMS fields.

For pages and posts:

- `seo_title` is used as the browser title when present.
- `seo_description` is used as the meta description when present.
- Pages fall back to their regular `title` and a short plain-text summary of `content`.
- Posts fall back to their regular `title`, then `excerpt`, then a short plain-text summary of `content`.

Site-wide fallbacks come from the `settings` table:

- `site_title`
- `default_meta_description`

If those settings are missing, the layout falls back to `config('app.name')` and omits the meta description when no description is available.

## Redirect Manager

The Filament admin includes an `SEO > Redirects` manager for simple SEO-safe redirects.

Redirect fields:

- `source_path`: path to redirect, for example `/old-page`
- `target_url`: internal path or full URL
- `status_code`: `301` or `302`
- `is_active`
- `hits_count`
- `last_hit_at`
- `note`

Use:

- `301` for permanent URL changes.
- `302` for temporary redirects.

The redirect resolver:

- runs before the catch-all page route
- records hits for active redirects
- skips direct self-redirect loops
- does not redirect `/admin`, `/sitemap.xml`, `/robots.txt`, `/build`, `/storage`, and common asset paths

Redirect source URLs are not added to the sitemap.

## Module Disable SEO Checklist

When disabling Projects or Shop, the public URLs become unavailable and may return 404 unless you create redirects.

Projects URLs to consider:

- `/projects`
- `/projects/category/{slug}`
- `/projects/{slug}`

Gallery URLs to consider:

- `/galleries`
- `/galleries/category/{slug}`
- `/galleries/{slug}`

Shop URLs to consider:

- `/shop`
- `/shop/category/{slug}`
- `/shop/{slug}`
- `/cart`
- `/checkout`

In `Site Settings`, use the redirect suggestion actions:

- `Create Projects Redirects`
- `Create Gallery Redirects`
- `Create Shop Redirects`

These actions create active redirects but do not disable modules and do not delete data. Cleanup actions are separate and destructive.

## Admin Media Library

The Filament admin includes a WordPress-like Media Library backed by Spatie's existing `media` table.

What it does:

- Adds a `Media Library` navigation item in the admin sidebar.
- Uploads images and videos to the `public` disk.
- Stores general uploads in the `media_library` collection.
- Uses the authenticated admin user as the Spatie media owner, so no separate media table or custom media system is needed.
- Lets admins browse, search by file name, filter images/videos, preview, open, copy URLs, and delete media.

Run this before using public media URLs:

```bash
php artisan storage:link
```

Pages and posts keep their existing `featured_image` media collection. On edit screens, admins can either upload a new featured image or use the `Use media library image` action to copy an existing Media Library image into that page/post featured image collection.

## Simple Shop Setup

The starter includes a lightweight shop intended for service packages, simple catalog products, digital products, and manual-order workflows.

Public shop paths:

- `/shop`: product index
- `/shop/category/{slug}`: product category archive
- `/shop/{slug}`: product detail
- `/cart`: session cart
- `/checkout`: manual checkout form

Admin shop sections:

- Products
- Product Categories
- Orders

Shop settings live in `Site Settings > Shop`:

- `shop_enabled`
- `shop_label`
- `shop_index_title`
- `shop_index_description`
- `shop_per_page`
- `shop_seo_title`
- `shop_seo_description`
- `shop_order_admin_email`
- `shop_manual_payment_message`

### Mail Settings

When a new order is created, the app sends a plain admin notification email.

Set normal Laravel mail values in `.env`, for example:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

The admin recipient is `shop_order_admin_email`. If that is empty, the app falls back to `contact_email`.

If the customer enters an email address during checkout, a simple order confirmation email is also attempted. Mail sending is fail-safe: checkout should still create the order if mail configuration is missing or unavailable.

### Manual Payment Behavior

The shop currently uses manual payment only:

- Orders are created with `payment_method = manual`.
- Orders start with `payment_status = unpaid`.
- The thank-you page shows the manual payment message from settings.
- Admins can mark orders paid, completed, or cancelled from Filament.

This is intentional for a reusable starter. It avoids pretending that a real payment flow exists before a client-specific gateway has been selected and tested.

### Order Lifecycle

Use this simple lifecycle for client projects:

- `pending` + `unpaid`: created immediately after checkout.
- `paid`: an admin has manually confirmed payment.
- `completed`: the product, service, or manual fulfillment has been delivered.
- `cancelled`: the order was rejected, abandoned, duplicated, or otherwise should not be fulfilled.

Order operations available in Filament:

- copy order number
- quick phone/email links for customer contact
- mark paid
- complete
- cancel
- print a simple HTML invoice/order view
- export orders as CSV

Printable invoices are plain HTML and browser-print friendly. No PDF package is included.

### Payment Gateway Extension Point

Future payment work should start from:

- `app/Contracts/PaymentGateway.php`
- `app/Services/ManualPaymentGateway.php`
- `app/Services/ZarinpalPaymentGateway.php`
- `app/Services/ZarinpalGraphqlClient.php`
- `app/Providers/ShopServiceProvider.php`

Replace the binding in `ShopServiceProvider` with a real gateway implementation when a client project needs online payment. The checkout flow already asks the gateway for the payment method, initial payment status, and instructions.

### Zarinpal Readiness

The starter includes a safe Zarinpal structure, but it does not process real payments yet.

Settings in `Site Settings > Shop`:

- `payment_gateway`: `manual` or `zarinpal`
- `zarinpal_access_token`
- `zarinpal_graphql_endpoint`
- `zarinpal_callback_url`

Default GraphQL endpoint:

```text
https://next.zarinpal.com/api/v4/graphql/
```

The GraphQL client sends:

- `POST`
- `Accept: application/json`
- `Authorization: Bearer {ACCESS_TOKEN}`

The access token is stored as a setting and must not be exposed in public views, logs, frontend JavaScript, or committed files.

Current Zarinpal behavior:

- Manual payment remains the default.
- If `payment_gateway = zarinpal` but no token exists, checkout returns a safe validation error and keeps the cart.
- The callback route exists at `/payments/zarinpal/callback` and does not require auth.
- Callback verification is a placeholder until real mutation details are completed.
- Orders are not marked paid automatically.

Before enabling live Zarinpal payments, complete and verify the official GraphQL request and verification mutations, required variables, callback payload shape, authority/reference fields, amount currency rules, sandbox/live behavior, and failure-code handling.

### What The Shop Intentionally Does Not Include

To keep this starter maintainable, the shop does not include:

- real online payment gateway integration
- PDF generation
- stock quantity or warehouse management
- shipping methods
- tax calculation
- coupons
- product variations
- customer accounts or order history
- multilingual catalog behavior

## Gallery Module

The starter includes a lightweight Gallery module for image and video gallery pages.

Public gallery paths:

- `/galleries`: gallery index
- `/galleries/category/{slug}`: gallery category archive
- `/galleries/{slug}`: gallery detail

Admin gallery sections:

- Galleries
- Gallery Categories

Gallery settings live in `Site Settings > Galleries`:

- `galleries_enabled`
- `galleries_label`
- `galleries_index_title`
- `galleries_index_description`
- `galleries_per_page`
- `galleries_seo_title`
- `galleries_seo_description`

Media behavior:

- Featured image uses the existing `featured_image` Media Library collection.
- Gallery images use an `images` Media Library collection.
- Video support is intentionally lightweight through `video_url`.
- No video upload, transcoding, streaming, or hosting workflow is included.

Project integration:

- A gallery can optionally belong to a Project.
- Published related galleries are shown on the Project detail page when Galleries are enabled.
- A `Featured Galleries` page block can show featured, latest, category-based, project-based, or type-filtered galleries.

Enable/disable behavior:

- When `galleries_enabled = false`, gallery links are hidden from frontend menus.
- Gallery resources are hidden from the Filament sidebar.
- Public gallery routes return 404 unless an active redirect exists.
- Existing gallery data is kept.
- Cleanup is separate, destructive, and does not delete redirects.

When disabling Galleries on a live client site, create redirects for old gallery URLs from `Site Settings > Galleries` or `SEO > Redirects`.

What the Gallery module intentionally does not include:

- complex video hosting
- video transcoding
- heavy lightbox package
- advanced media permissions
- multilingual gallery behavior

## Template Builder

The Filament admin includes `Design > Templates`, a lightweight Elementor-style Theme Builder for dynamic layouts.

Templates can fully replace the default Blade content for these areas:

- blog index
- post detail
- post category archive
- projects index
- project detail
- project category archive
- shop index
- product detail
- product category archive
- galleries index
- gallery detail
- gallery category archive
- site header
- site footer

If no published matching template exists, the original Blade view remains the fallback. Draft templates are ignored.

### Template Conditions

Templates use the existing `conditions` JSON field. No extra condition tables are required.

Current condition structure:

```json
{ "type": "all" }
```

```json
{ "type": "specific_item", "item_id": 123 }
```

```json
{ "type": "category", "category_id": 5 }
```

Supported condition types:

- `all`: applies to every item/archive of the selected template type
- `specific_item`: applies to one specific record
- `category`: applies to items inside one category

Specificity order:

1. `specific_item`
2. `category`
3. `all` / default

Priority only resolves conflicts inside the same specificity level. For example, a specific product template beats a product category template even if the category template has a higher priority. Between two category templates for the same category, higher priority wins. If priority is the same, default templates and then the newest updated template win consistently.

Examples:

- all products: type `product_single`, condition `all`
- products in one category: type `product_single`, condition `category`
- one product only: type `product_single`, condition `specific_item`
- one project category archive: type `project_category`, condition `specific_item`
- all post category archives: type `post_category`, condition `all`

Use dynamic template blocks when replacing a default layout:

- `Dynamic: Archive Header`
- `Dynamic: Content Grid`
- `Dynamic: Single Header`
- `Dynamic: Single Content`
- `Dynamic: Single Meta`
- `Dynamic: Single Gallery`
- `Dynamic: Add To Cart`

Static blocks are useful for fixed hero/CTA/FAQ sections, but dynamic blocks are what render the current post, product, project, gallery, category, or archive collection.

Limitations:

- no nested AND/OR condition groups
- no date-based conditions
- no user-role conditions
- no visual drag-and-drop grid system
- conditions currently target single items or one category only

### Template Preview And Debug

On a Template edit screen, use the `Preview` action to render the template with real context.

Preview behavior:

- route: `/admin/preview/templates/{template}`
- admin-auth protected
- always renders `noindex, nofollow`
- allows previewing draft templates for admins only
- does not change public route behavior

For single templates, choose a sample item:

- `post_single`: Post
- `project_single`: Project
- `product_single`: Product
- `gallery_single`: Gallery

For category templates, choose a sample category:

- `post_category`: Blog Category
- `project_category`: Project Category
- `product_category`: Product Category
- `gallery_category`: Gallery Category

Index templates can be previewed without selecting an item.

The Template edit form includes a read-only Debug section showing:

- template type
- status
- condition summary
- priority
- default flag
- whether the condition can match
- warnings for draft templates, missing condition targets, and replacement templates without dynamic blocks

Use the debug section when a public page is not using the expected template. Check in this order:

1. Template status is `published`.
2. Template type matches the public route type.
3. Condition target exists.
4. Specificity order is correct: `specific_item > category > all/default`.
5. Priority is higher than other templates at the same specificity.
6. The template includes dynamic blocks if it should show current content.

## Structure

The structure is intentionally plain:

- `app/Models` contains Eloquent models for CMS data.
- `database/migrations` contains the database schema.
- `app/Providers/Filament/AdminPanelProvider.php` configures the admin panel.
- `routes/web.php` contains simple public page and blog routes.
- `resources/views` contains minimal Blade views.
- `config/filesystems.php` uses the `public` disk for uploaded media.

## Client Project Launch Checklist

Use this checklist before cloning the starter for a real client project.

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
php artisan test
```

Change the seeded admin account immediately:

- default email: `admin@example.com`
- default password: `password`

### Reserved Slugs

Avoid creating normal Pages with these reserved paths:

- `home`
- `blog`
- `contact`
- `projects`
- `galleries`
- `shop`
- `cart`
- `checkout`
- `health`
- `sitemap.xml`
- `robots.txt`
- `payments/zarinpal/callback`

### Settings Before Launch

Configure these in `Site Settings`:

- site name and description
- logo and favicon
- contact phone, email, and address
- header CTA label and URL
- footer text
- social links
- theme colors, font, button radius, and container width
- default SEO title, description, and OG image
- robots and sitemap options

### SEO Checklist

- Add SEO title and description for key Pages.
- Add SEO title and description for important Posts, Projects, and Products.
- Set default OG image.
- Confirm `/sitemap.xml` loads.
- Confirm `/robots.txt` includes the sitemap.
- Keep drafts unpublished and verify they return 404 publicly.
- Use admin preview only for internal review.

### Shop Checklist

- Confirm `shop_enabled` is correct.
- Add Product Categories.
- Add Products with status, price, images, SEO fields, and stock availability.
- Test cart add/update/remove.
- Test checkout validation.
- Confirm order emails work with the production mail configuration.
- Review printable invoice and CSV export.

### Payment Checklist

- Keep `payment_gateway = manual` until a real gateway is completed and tested.
- For Zarinpal, add the access token only in settings or secure environment-backed deployment flow.
- Confirm the official request and verify GraphQL mutations before enabling live payment.
- Test callback behavior with sandbox credentials before production.
- Never commit payment credentials.

### Deployment Checklist

- Set `APP_ENV=production`.
- Set `APP_DEBUG=false`.
- Configure production database.
- Configure mail.
- Run migrations.
- Run `php artisan storage:link`.
- Run `npm run build`.
- Run `php artisan optimize`.
- Run smoke checks for `/`, `/blog`, `/projects`, `/galleries`, `/shop`, `/contact`, `/health`, `/sitemap.xml`, and `/robots.txt`.

## Production Readiness

Use this section when deploying a real client website from the starter.

### Environment Setup

Required `.env` checks:

- `APP_NAME` matches the client project.
- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `APP_URL` is the final HTTPS domain.
- `APP_KEY` exists. Generate it with `php artisan key:generate` if missing.
- Database credentials point to the production database.
- Mail credentials are configured and tested.
- Queue connection is intentional. Use `sync` only for very small/manual sites; use a real worker for queued mail or heavier background jobs.

Never commit production `.env`, payment tokens, SMTP passwords, database passwords, or API keys.

### First Deployment Commands

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan test
```

If the server receives built assets from CI/CD, skip `npm install` and `npm run build` on the server and deploy the compiled `public/build` output instead.

### Admin Launch Checklist

The Filament admin includes `Maintenance > System Status`.

It is read-only, admin-only, and does not expose secrets, tokens, passwords, `.env` values, or server paths beyond safe operational hints.

System status shows:

- application environment
- Laravel version
- PHP version
- database connection status
- public storage link status
- mail configuration presence
- queue connection
- sitemap and robots route status

Launch checklist shows:

- site name
- logo and favicon
- contact email and phone
- default SEO title and description
- sitemap setting
- robots route
- health check setting
- mail configuration presence
- shop payment mode
- Zarinpal token requirement only when Zarinpal is selected
- active redirects count
- draft pages, posts, projects, galleries, and products count
- module enable/disable decisions
- backup plan reminder

Only authenticated admins can access this page.

Maintenance exports are intentionally lightweight CSV downloads:

- `Orders > Export CSV`
- `Inbox > Contact Messages > Export CSV`
- `SEO > Redirects > Export CSV`

Use these for handoff and reporting. They are not a substitute for full database backups.

### Health Check

The app exposes a safe JSON endpoint:

```text
GET /health
```

It returns only non-sensitive status fields:

- `status`
- `app`
- `environment`
- `database`

It does not expose secrets, configuration arrays, database credentials, tokens, debug state, or user data. You can disable it from `Site Settings > General` with `health_check_enabled`.

### Cache And Optimization

Run after changing deployment config or code:

```bash
php artisan storage:link
npm run build
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Run after changing Blade views during troubleshooting:

```bash
php artisan view:clear
```

Run after changing `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
```

### File Permissions

The web server must be able to write to:

- `storage`
- `bootstrap/cache`

Public uploaded files require:

```bash
php artisan storage:link
```

Confirm images load from `/storage/...` after deployment.

### Mail And Queue

Before launch:

- configure `MAIL_MAILER`, host, port, credentials, from address, and from name
- submit a contact form test
- place a test shop order if Shop is enabled
- confirm admin order notification email
- confirm customer confirmation email if customer email is supplied

If using a queue driver other than `sync`, configure a persistent queue worker through the hosting panel, supervisor, or systemd.

### SEO, Robots, Sitemap, And Redirects

Before launch:

- configure default SEO title and description
- configure key page/post/project/product SEO fields
- set a default OG image
- confirm `/sitemap.xml` loads
- confirm `/robots.txt` includes the sitemap URL
- confirm disabled Projects or Shop URLs are excluded from the sitemap
- review `SEO > Redirects`
- create redirects before disabling modules that previously had indexed URLs

Use `301` for permanent URL moves and `302` only for temporary redirects.

### Shop And Payment

Before launch:

- confirm `shop_enabled`
- confirm all products have correct status, price, stock availability, images, and SEO
- test cart add/update/remove
- test checkout validation
- keep `payment_gateway=manual` unless a real gateway has been completed and tested
- do not enable live Zarinpal until request and verify mutations are implemented with official credentials

### Backups

Set up backups before handing over the website:

- database backups from the hosting panel, managed database service, or trusted server-side tooling
- `storage/app/public`, including uploaded Media Library files
- the `public/storage` symlink target, which should point to `storage/app/public`
- `.env` as a secure manual backup outside the repository and outside public web directories
- restore test for at least one backup before launch

For client projects with orders, contact messages, or frequent content updates, use automated daily backups at minimum.

Restore notes:

- restore the database first
- restore `storage/app/public`
- recreate the storage symlink with `php artisan storage:link`
- deploy/build frontend assets with `npm run build` or restore the CI/CD-built `public/build`
- clear and rebuild caches with `php artisan optimize:clear`, then `php artisan optimize`
- verify `/`, `/admin`, `/sitemap.xml`, `/robots.txt`, `/health`, image uploads, contact form, and checkout if Shop is enabled

Before disabling Projects, Galleries, or Shop on a live site:

- review indexed URLs and active menu items
- create 301 redirects from the module tab in Site Settings or from `SEO > Redirects`
- confirm the disabled module is excluded from `/sitemap.xml`
- keep cleanup actions separate from disabling; cleanup deletes records and should be used only after backup
