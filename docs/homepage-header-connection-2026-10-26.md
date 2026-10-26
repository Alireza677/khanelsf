# بررسی و اصلاح اتصال Homepage و Header Menu

تاریخ: ۲۰۲۶-۱۰-۲۶

## محدوده و قطعیت شواهد

این بررسی روی سورس همین checkout انجام شده است. هیچ داده‌ای از برگه‌ها، منوها یا تنظیمات وب‌سایت گزارش‌شده در دسترس نبوده و هیچ محتوای واقعی مشاهده یا تغییر نکرده است. نتایج تست‌ها مربوط به Factory و Fixture و SQLite `:memory:` هستند.

یک اختلاف مهم با شرح حادثه وجود دارد: در نسخه اولیه این checkout، انتخاب Homepage در تنظیمات سایت پیاده نشده بود. جست‌وجوی سورس، فرم تنظیمات، metadata ذخیره تنظیمات، مدل Page و README هیچ کلید موجودی برای انتخاب شناسه Homepage نشان نداد. بنابراین `homepage_page_id` **کلید جدید این اصلاح** است؛ نام کلید ناشناخته‌ای در دیتابیس Production فرض نشده است. تعیین اینکه پنل سایت گزارش‌شده چه نسخه یا سفارشی‌سازی‌ای دارد، نیازمند بررسی پس از استقرار است.

## ۱. علت ریشه‌ای Homepage

مسیر پیش از اصلاح:

```text
GET /
  → routes/web.php: route home → HomeController::__invoke
  → Page::published()->where('slug', 'home')->first()
  → view('home')
  → layouts.app + partials.page-blocks در صورت وجود blocks
```

در مقابل، آدرس مستقیم برگه از `PageController::show`، scope همان `published()`، و `view('page')` استفاده می‌کرد. پس `/` هیچ انتخابی از SettingsService دریافت نمی‌کرد و مستقل از نامک برگه موردنظر، فقط به نامک رزروشده `home` متکی بود. تعیین `Page.template = home` نیز جایگزین انتخاب Homepage نبود؛ HomeController اصلاً این فیلد را برای انتخاب برگه مصرف نمی‌کرد.

در `ManageSiteSettings` اولیه نه فیلد انتخاب Homepage و نه ورودی metadata برای ذخیره آن وجود داشت. متد `save()` فقط کلیدهای metadata را از فرم به `SettingsService::set()` می‌دهد. تست بازتولید نشان داد افزودن مقدار `data.homepage_page_id` به state فرم قدیمی، رکوردی در جدول settings ایجاد نمی‌کند.

مسیر پس از اصلاح:

```text
تنظیمات سایت → عمومی → برگه صفحه اصلی
  → homepage_page_id، group=general، type=select
  → SettingsService::set → settings.value
  → HomeController → SettingsService::homepagePageId
  → Page::published()->whereKey(...)->firstOrFail()
  → view('page') → همان Layout و partials.page-blocks
```

انتخاب به نامک، Page ID ثابت یا `Page.template` خاص وابسته نیست. canonical پاسخ ریشه همچنان `route('home')` است. HTML، CSS و JS مورد اعتماد در بلوک `custom_html` از همان `partials.blocks.custom_html` به‌صورت قبلی عبور می‌کنند؛ sanitizer یا مجوز جدیدی اضافه نشده است.

قاعده انتشار فعلی Page عبارت است از `status = published` و `published_at` خالی یا گذشته. فیلد مستقل `is_active` در مدل Page وجود ندارد. برگه انتخاب‌شده حذف‌شده، draft، دارای status غیرمنتشر، یا زمان انتشار آینده در `/` مانند مسیر مستقیم 404 می‌دهد و به محتوای برگه دیگری برنمی‌گردد. تست وضعیت `inactive` نیز نشان می‌دهد تنها وضعیت `published` پذیرفته می‌شود، نه اینکه وضعیت مدیریتی جدیدی اضافه شده باشد.

برای سازگاری، اگر انتخاب جدید خالی باشد، رفتار قبلی نامک `home` و View قدیمی `home` حفظ می‌شود؛ سایت خالی نیز پاسخ قبلی خود را حفظ می‌کند.

## ۲. علت ریشه‌ای Header Menu

ذخیره و خواندن تنظیم سایت در ابتدا هم به یک کلید متصل بودند:

```text
ManageSiteSettings: header_menu_id → SettingsService::set → settings
  → SettingsService::headerMenuId → MenuService::header
```

هدر پیش‌فرض از `partials.header` و `<x-navigation placement="header">` همین مسیر را استفاده می‌کند. قطع اتصال اثبات‌شده در هدر صنعتی بود: `SiteHeaderRuntime::prepare` ابتدا `settings.menu_id` ذخیره‌شده داخل بلوک را می‌خواند و فقط در صورت خالی‌بودن آن، `MenuService::header()` را فراخوانی می‌کرد. بنابراین یک menu_id قدیمی، متفاوت، غیرفعال یا حذف‌شده در Template می‌توانست انتخاب `header_menu_id` را نادیده بگیرد.

تست بازتولید با دو منوی مستقل نشان داد با وجود ذخیره موفق `header_menu_id`، HTML هدر صنعتی آیتم منوی Template را نمایش می‌دهد.

اصلاح یک خط در Runtime، ترتیب را مشخص می‌کند:

1. انتخاب صریح `header_menu_id` در تنظیمات سایت؛
2. در نبود انتخاب سایت، `settings.menu_id` قبلی بلوک؛
3. در نبود هر دو، رفتار قبلی `MenuService::header()` و location=`main`.

اگر انتخاب صریح سایت به منوی حذف‌شده یا غیرفعال اشاره کند، منوی نامرتبط جایگزین نمی‌شود. این همان رفتار `MenuService` موجود برای انتخاب صریح است. ترتیب اولویت در helper فرم بلوک نیز توضیح داده شد. این تغییر برای سایت‌هایی که هم‌زمان هر دو مقدار متفاوت را تنظیم کرده‌اند، عمداً اولویت را به تنظیم سراسری سایت می‌دهد.

مسیر داده Navigation حفظ شده است:

```text
MenuService → Menu(status=active) → rootItems / children
  → مرتب‌سازی و NavigationSourceVisibility
  → MenuItem::resolvedUrl
      → source_key: NavigationSourceRegistry → Source resolver
      → reference: ResolvesNavigationUrl
      → custom_url: URL ذخیره‌شده
  → Navigation component در هدر پیش‌فرض
    یا SiteHeaderRuntime → site-header-industrial در هدر صنعتی
```

فیلتر وضعیت منو و آیتم، Module availability، حذف Source نامعتبر، URL مرجع جاری، parent_id و ترتیب آیتم‌ها تغییر نکردند. عمق هدر پیش‌فرض همچنان حداکثر دو سطح و Runtime صنعتی همچنان حداکثر ۲۰ سطح است. هیچ قرارداد Source یا Navigation Core تغییر نکرده است.

## ۳. Route، Template و Cache

- Route صریح `/` در ابتدای `routes/web.php` قرار دارد و catch-all برگه در انتهاست؛ تداخل ترتیب Route علت این دو نقص نبود و Routeها تغییر نکردند.
- `ResolveRedirects` پیش از Controller می‌تواند برای `/` ریدایرکت ثبت‌شده را اجرا کند. وجود چنین داده‌ای در سایت واقعی نامعلوم است؛ رفتار ریدایرکت تغییر نکرده است.
- `BlockServiceProvider` برای Layout، `SiteHeaderTemplateResolver` را مصرف می‌کند. فقط Template منتشرشده با type=`site_header` انتخاب می‌شود. Layout با بلوک‌های Template انتخابی رندر می‌شود؛ در نبود Template قابل استفاده، هدر پیش‌فرض موجود را نشان می‌دهد.
- Template سفارشی‌ای که اصلاً بلوک `site_header` یا مصرف‌کننده Navigation نداشته باشد، خودبه‌خود منو ایجاد نمی‌کند. افزودن هدر یا تزریق منوی موازی به Templateهای دلخواه انجام نشده است؛ ساختار Template واقعی باید بررسی شود.
- `SettingsService` در اجرای عادی کش static داخل پردازش دارد. `set()` ورودی همان کلید را حذف می‌کند. در MenuService و Resolver انتخاب Header، کش پایدارِ مدل یا HTML وجود ندارد؛ Registry نیز تعریف Source را نگه می‌دارد و URL و availability را از Resolver می‌گیرد.
- تست جدید با subclass آزمایشی، `requestCacheEnabled()` را فعال و cache را بین تست‌ها پاک می‌کند؛ پس بررسی ذخیره مدیریتی و تعویض انتخاب، مسیر کش فعال را پوشش می‌دهد. الگوریتم کش پروژه تغییر نکرده است.
- کش CDN، reverse proxy، مرورگر، Route/config cache محیط استقرار و چرخه عمر workerهای Production در این آزمایش محلی تأیید نشده‌اند. نوشتن مستقیم SQL یا همگامی چند worker نیز معادل مسیر `SettingsService::set()` آزمایش‌شده نیست.

## ۴. فایل‌های بررسی‌شده

| بخش | فایل‌ها و محل بررسی |
| --- | --- |
| Route و ورودی عمومی | `routes/web.php`، `bootstrap/app.php`، `app/Http/Controllers/HomeController.php`، `app/Http/Controllers/PageController.php`، `app/Http/Middleware/ResolveRedirects.php` |
| مدیریت و تنظیمات | `app/Filament/Pages/ManageSiteSettings.php`، `app/Filament/Resources/PageResource.php`، `app/Models/Setting.php`، `app/Services/SettingsService.php` |
| Page و رندر | `app/Models/Page.php`، `resources/views/home.blade.php`، `resources/views/page.blade.php`، `resources/views/partials/page-blocks.blade.php`، `resources/views/partials/blocks/custom_html.blade.php`، `app/Services/SeoService.php` |
| Layout و Template هدر | `app/Providers/BlockServiceProvider.php`، `app/CMS/Templates/SiteHeaderTemplateResolver.php`، `resources/views/layouts/app.blade.php`، `resources/views/partials/header.blade.php`، `app/CMS/Blocks/SiteHeader/SiteHeaderRuntime.php`، `SiteHeaderDataNormalizer.php` و `SiteHeaderBlock.php` در همان پوشه |
| Navigation | `app/Models/Menu.php`، `app/Models/MenuItem.php`، `app/Services/MenuService.php`، `app/Services/MenuTreeService.php`، `app/Services/NavigationSourceVisibility.php`، `app/CMS/Navigation/NavigationSourceRegistry.php`، `app/CMS/Navigation/Sources/ServiceNavigationSource.php`، `app/View/Components/Navigation.php`، `resources/views/components/navigation.blade.php` و `navigation-items.blade.php`، `app/Providers/NavigationServiceProvider.php` و `ShopServiceProvider.php` |
| قرارداد قدیمی و داده پایه | `README.md`، بخش برگه home در `database/seeders/DatabaseSeeder.php`، بخش جدول‌های منو در `database/migrations/2026_05_30_000000_create_cms_tables.php`، `app/Services/SitemapService.php` |
| محیط آزمایش | `tests/TestCase.php`، `phpunit.xml`، `.env.testing`، `database/factories/PageFactory.php`، `app/Providers/TestingDatabaseSafetyServiceProvider.php` و فایل‌های تست فهرست‌شده در بخش بعد |

## ۵. فایل‌های تغییرکرده

| فایل | دلیل |
| --- | --- |
| `app/Filament/Pages/ManageSiteSettings.php` | افزودن انتخاب برگه منتشرشده و ثبت کلید جدید در metadata ذخیره تنظیمات فعلی |
| `app/Services/SettingsService.php` | getter شناسه مثبت برای همان کلید جدید |
| `app/Http/Controllers/HomeController.php` | خواندن انتخاب و رندر استاندارد Page، اعمال Publication Policy، حفظ حالت بدون انتخاب |
| `app/CMS/Blocks/SiteHeader/SiteHeaderRuntime.php` | اولویت انتخاب سراسری منوی هدر، با استفاده از همان MenuService |
| `app/CMS/Blocks/SiteHeader/SiteHeaderBlock.php` | توضیح اولویت انتخاب منو در فرم موجود |
| `app/Filament/Resources/PageResource.php` | اصلاح راهنمای قدیمیِ الزام نامک home |
| `tests/Feature/HomepageHeaderIntegrationTest.php` | تست‌های Factory/Fixture برای رفتار جدید، سازگاری و کش فعال |
| `README.md` | مستندسازی انتخاب Homepage، رفتار خطا و اولویت منوی هدر |
| همین گزارش | ثبت شواهد، محدوده اصلاح و محدودیت تأیید |

هیچ Migration، Schema، Seeder، Header/Footer جدید، Block Registry، Page Builder، Template Runtime عمومی یا Navigation Core تغییر نکرده است. محتوای آزمایشی فقط داخل تست‌ها ساخته می‌شود.

## ۶. تست‌ها و نتیجه

پیش از اصلاح، دو تست هدفمند جدید اجرا شدند: **۲ تست، ۸ assertion، هر دو fail**. یکی نبود ذخیره کلید Homepage را نشان داد و دیگری نمایش منوی Template به‌جای منوی انتخاب‌شده سایت را.

پس از اصلاح، `HomepageHeaderIntegrationTest`: **۱۵ تست، ۱۳۷ assertion، موفق**.

پوشش این مجموعه:

- ذخیره انتخاب Homepage از فرم Livewire، رندر بلوک Builder و canonical ریشه؛
- تعویض Homepage با کش گرم و حفظ کارکرد آدرس مستقیم برگه قبلی؛
- برابری خروجی بلوک Custom HTML، CSS و JS در `/` و آدرس مستقیم؛
- 404 برای برگه draft، وضعیت غیرمنتشر، انتشار آینده و حذف پس از یک درخواست موفق؛
- رفتار قبلی سایت بدون انتخاب، سایت خالی و پاک‌کردن انتخاب؛
- گزینه‌های انتخاب فقط از برگه‌های منتشرشده و رد شناسه ناموجود؛
- اولویت منوی تنظیمات بر منوی Template؛
- تغییر منوی Header از پنل با کش گرم در هدر پیش‌فرض و صنعتی؛
- ترتیب، parent/child، URL مرجع بعد از تغییر slug، Source معتبر/نامعتبر، Source ماژول غیرفعال و آیتم غیرفعال؛
- انتخاب منوی غیرفعال/حذف‌شده، نبود انتخاب، fallback قبلی و نبود هیچ منو.

دستور بازتولید مجموعه جدید:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:CACHE_STORE='array'
php vendor/bin/phpunit tests/Feature/HomepageHeaderIntegrationTest.php --no-progress
```

تست‌های سازگاری موجود در این فایل‌ها نیز اجرا شدند:

```text
tests/Feature/PublicRoutesTest.php
tests/Feature/SiteHeaderTemplateTest.php
tests/Feature/SiteFooterTemplateTest.php
tests/Feature/SettingsAdminPageTest.php
tests/Feature/SettingsServiceTest.php
tests/Feature/MenuManagementUxTest.php
tests/Feature/ServiceNavigationSourceTest.php
tests/Unit/NavigationSourceRegistryTest.php
tests/Unit/NavigationSourceVisibilityTest.php
tests/Unit/NavigationRendererTest.php
tests/Unit/MenuItemUrlResolutionTest.php
```

نتیجه این اجرا: **۹۶ تست، ۷۲۱ assertion؛ ۹۲ موفق و ۴ شکست**. کل این مجموعه سبز نیست. هر چهار شکست سپس با نسخه اولیه شش کلاس PHP تغییرکرده از `git show HEAD:<path>` بازتولید شدند. برای این مقایسه، نسخه‌های اولیه در پوشه موقت و با Composer classmap آزمایشی بارگذاری شدند؛ سورس کاری بازنویسی نشد و Viewها و محیط همان بودند. اجرای baseline چهار تست انتخابی نیز **۴ شکست** داشت:

| تست موجود | شکست و شاهد سورس |
| --- | --- |
| `PublicRoutesTest::test_frontend_layout_includes_the_canonical_custom_font_styles` | انتظار یک `@font-face` دارد؛ در محیط حاضر فونت همراه CMS نیز وجود دارد و دو تعریف رندر می‌شود. |
| `PublicRoutesTest::test_hero_three_split_stats_template_renders` | انتظار کلاس `hero-template-3--left` دارد؛ View فعلی `partials/blocks/hero/hero_3.blade.php` کلاس right را ثابت رندر می‌کند. |
| `SettingsServiceTest::test_font_css_cannot_be_injected_through_name_or_file_value` | انتظار نبود هر `@font-face` را دارد، اما فونت همراه معتبر CMS مستقل از ورودی نامعتبر رندر می‌شود. شکست به معنی اثبات تزریق CSS نیست. |
| `MenuManagementUxTest::test_menu_renderer_excludes_inactive_and_disabled_source_items` | Fixture یک آیتم type=post با URL و بدون reference می‌سازد؛ قرارداد فعلی `MenuItem::resolvedUrl()` برای این نوع، reference می‌خواهد و آیتم را حذف می‌کند. |

این چهار مورد برای حفظ محدوده اصلاح تغییر نکردند. تست‌های جدید از مرجع معتبر برای آیتم داخلی استفاده می‌کنند. تست‌های موجود SiteHeaderTemplate، SiteFooterTemplate، ServiceNavigationSource و قراردادهای Unit Navigation در اجرای فوق شکست نداشتند.

بررسی `php -l` برای هر هفت فایل PHP تغییرکرده، `pint --test` روی همان فایل‌ها و `git diff --check` موفق بودند. هیچ تغییر JS/CSS یا asset کامپوننت Filament انجام نشده؛ build یا انتشار asset جدید لازم نبود.

## ۷. بررسی لازم پس از استقرار

1. تطبیق نسخه سورس پنل واقعی با این checkout و شناسایی انتخاب Homepage قبلی در صورت وجود سفارشی‌سازی؛ هیچ نگاشت یا انتقال داده‌ای از کلید ناشناخته انجام نشده است. انتخاب برگه منتشرشده در فیلد جدید باید در پنل واقعی بررسی شود.
2. بررسی `homepage_page_id`، وضعیت و زمان انتشار برگه انتخابی؛ مقایسه محتوای `/` و آدرس مستقیم، و اجرای مجاز CSS/JS با CSP و دارایی‌های واقعی.
3. بررسی `header_menu_id`، وضعیت منو و آیتم‌ها، Sourceهای ثبت‌شده و فعال‌بودن ماژول‌های مقصد.
4. بررسی `header_template_id`، type/status آن و وجود بلوک مصرف‌کننده Navigation در Template واقعی.
5. بررسی ریدایرکت ثبت‌شده برای `/`، کش‌های محیط استقرار و پاسخ تازه پس از ذخیره تغییرات مدیریتی.
6. بررسی بصری هدر، زیرمنوها، رفتار موبایل و اسکریپت‌های صفحه در مرورگر سایت واقعی. تست‌های PHP، اجرای JS یا صحت بصری را تأیید نمی‌کنند.

موفقیت تست‌های آزمایشی به معنی تأیید عملکرد وب‌سایت واقعی یا Production نیست.
