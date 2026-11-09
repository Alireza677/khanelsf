# King CMS — Universal Shop Filter Query Engine — Phase 2

تاریخ: ۲۰۲۶-۱۱-۰۹. محدوده: Query، Parser، Context فیلتر، اتصال عملکردی آرشیو و SEO آرشیو. طراحی بصری فروشگاه و جزئیات محصول در این فاز انجام نشده است.

یادداشت Stabilization بعدی: قرارداد پاسخ ترکیب معتبر با صفر نتیجه به **HTTP 200** اصلاح شد؛ ورودی/دسته نامعتبر و Pagination خارج از محدوده همچنان ۴۰۴ هستند. توضیح ۴۰۴ نتایج خالی در متن تاریخی زیر، رفتار پایان فاز دوم است و با این تصمیم جایگزین شده است. Canonical و Noindex فاز دوم حفظ شدند. در Preflight مرحله Stabilization، Migration فاز اول از قبل روی اتصال محلی اعمال شده بود؛ در آن مرحله هیچ Migrationی روی دیتابیس برنامه اجرا نشد. جزئیات: `docs/shop-stabilization-targeted-fixes.md`.

## پیش‌نیاز و وضعیت Git

در شروع این فاز، ۳۹ فایل مربوط به فاز اول تغییرکرده/جدید و Commitنشده بودند. Hash آن‌ها پیش از فاز دوم ثبت شد تا تغییرات این فاز از فاز اول جدا و حفظ آن‌ها بررسی شود.

بررسی Read-only با `migrate:status` نشان داد Migration `2026_11_09_000001_add_universal_product_catalog` در اتصال فعلی برنامه **Pending** است. هیچ Migrationی روی دیتابیس برنامه یا Production اجرا نشد. قبل از فعال‌کردن این کد، Migration فاز اول باید با روال استقرار پروژه اعمال شود. فاز دوم Migration تازه‌ای ندارد.

## معماری Query

- `ShopFilterParser` شکل، حدود و مقادیر پارامترها را اعتبارسنجی و به `ShopFilters` تبدیل می‌کند. شناسه نامعتبر یا غیرفعال در لایه کاربردی اعتبارسنجی می‌شود.
- `ShopFilters` وضعیت استاندارد، تولید URL، تغییر تک‌فیلتر و Hidden Inputهای فرم را در اختیار مصرف‌کننده می‌گذارد. تغییر کنترل‌ها و لینک حذف، page را نگه نمی‌دارند.
- `ShopCategoryTree` فقط Metadata دسته‌ها را می‌خواند؛ شاخه فعال و زیرشاخه‌ها را محاسبه می‌کند و هیچ محصولی را در حافظه فیلتر نمی‌کند.
- `ProductQueryService::archive()` تنها محل ترکیب شروط محصول است؛ `sortArchive()` ترتیب Allowlist و tie-breaker شناسه را اعمال می‌کند. `publishedProducts` و قراردادهای قبلی جست‌وجو/مرتبط‌ها باقی مانده‌اند.
- `ShopArchiveService` اعتبار عمومی انتخاب‌ها، Pagination، Facetهای سبک و فیلترهای فعال را آماده می‌کند. Index و Category هر دو از اکشن داخلی مشترک Controller استفاده می‌کنند.
- شرط‌های ویژگی، برند و برچسب با `whereHas`/EXISTS اجرا می‌شوند؛ به Product JOIN تکثیرکننده اضافه نمی‌شود. OR بین گزینه‌های یک ویژگی و AND بین ویژگی‌ها و دیگر خانواده‌هاست. برندها و برچسب‌های متعدد نیز داخل خانواده خود OR هستند.

## فیلترهای فعال

دسته و زیرشاخه، چند برند، چند برچسب، مقادیر ویژگی‌های فعال و filterable از نوع choice، بازه قیمت مؤثر، موجودی، تخفیف‌دار، ویژه، جست‌وجوی عنوان/خلاصه/محتوا/SKU و علاقه‌مندی Session قبلی. نام هیچ ویژگی یا صنعت در Query/فرم عمومی Hardcode نشده است.

جست‌وجو `%` و `_` ورودی را کاراکتر واقعی در نظر می‌گیرد، نه wildcard تزریقی. پارامترها Bind می‌شوند. Publication و زمان انتشار حفظ شده‌اند و Module در ورودی عمومی کنترل می‌شود.

سیاست دسته غیرفعال: در آرشیو، دسته و تمام زیرشاخه‌های آن پنهان می‌شوند؛ محصولات آن شاخه در نتایج آرشیو نمی‌آیند. URL آرشیو پنهان ۴۰۴ است. دسته‌های پنهان از بخش دسته‌های Sitemap حذف می‌شوند. URL جزئیات محصول منتشرشده، Publication محصول و بخش محصولات Sitemap همچنان قرارداد قبلی را دارند؛ غیرفعال‌کردن دسته جای غیرفعال‌کردن محصول نیست. Noindex دسته نیز به معنی غیرفعال‌شدن آن نیست.

## قیمت و ترتیب

SQL قیمت مؤثر و شرط تخفیف در خود `ProductPricingService` تعریف شده‌اند. Sale غیرمنفی و کمتر از Regular قیمت مؤثر است؛ Sale صفر معتبر، Sale برابر/بالاتر فاقد تخفیف است. مقایسه بازه در SQL با DECIMAL و عادی‌سازی ورودی با مسیر exact/BCMath انجام می‌شود؛ PHP Float وارد محاسبه قیمت فیلتر نشده است. قیمت‌های Legacy خراب، منفی، خارج از ظرفیت یا دارای precision نامعتبر در آرشیو به قیمت ظاهراً معتبر تبدیل نمی‌شوند و از Query آرشیو کنار می‌روند.

Price Freshness تغییر نکرده است: فیلتر قیمت بر مبلغ مؤثر ثبت‌شده اعمال می‌شود؛ انقضا آن را تازه یا قابل خرید نمی‌کند. محصول منقضی/تأییدنشده همچنان پیام «آخرین قیمت ثبت‌شده» دارد و کنترل خرید سرور پابرجاست. موجودی و قابلیت خرید با قیمت معتبر دو مفهوم جدا هستند. ارز IRT حفظ شده است.

Allowlist ترتیب: `default`، `newest`، `oldest`، `price_asc`، `price_desc` و `title`. ترتیب زمانی از زمان انتشار و در نبود آن زمان ایجاد استفاده می‌کند. ترتیب پیش‌فرض قبلی حفظ و ID به‌عنوان معیار نهایی اضافه شده است. پرفروش، محبوب و امتیازدهی ایجاد نشده‌اند.

## قرارداد URL

مسیرها همان `/shop` و `/shop/category/{slug}` هستند. `/shop?category=slug` با ۳۰۱ به مسیر موجود دسته هدایت می‌شود و سایر شروط معتبر را حفظ می‌کند. انتخاب «همه دسته‌ها» در فرم دسته به Index برمی‌گردد؛ پاک‌کردن همه فیلترها در Context دسته، همان آرشیو دسته را نگه می‌دارد. چیپ دسته امکان خروج از Context دسته با حفظ سایر شروط را دارد.

| پارامتر | قرارداد |
|---|---|
| `q` | متن حداکثر ۲۰۰ نویسه |
| `brands[]` و `tags[]` | نامک‌ها؛ یکتا و مرتب‌شده |
| `attributes[attribute-slug][]` | نامک مقادیر، در محدوده همان ویژگی |
| `min_price` و `max_price` | مبلغ غیرمنفی تا دو اعشار و سقف سرویس؛ در صورت جابه‌جایی دو سر، مرتب می‌شوند |
| `stock` | `in_stock` یا `out_of_stock` |
| `featured`، `on_sale`، `favorites` | مقدار فعال canonical برابر `1`؛ حالت خاموش حذف می‌شود |
| `include_children` | پیش‌فرض فعال؛ `0` یعنی فقط دسته مستقیم |
| `sort` | Allowlist؛ مقدار default در URL حذف می‌شود |
| `page` | صحیح مثبت؛ صفحه اول حذف می‌شود؛ ورودی حداکثر ۱۰۰۰۰۰ و صفحه فراتر از آخر ۴۰۴ است |

حد ورودی: حداکثر ۳۰ پارامتر سطح اول، ۲۰ گروه ویژگی، ۳۰ ورودی هر فهرست و مجموعاً ۱۰۰ انتخاب. گزینه‌های تکراری حذف و مرتب می‌شوند. URL غیر canonical با ۳۰۱ به شکل یکتا می‌رود؛ کلیدهای ناشناخته و کنترل‌های قدیمی و بی‌اثر ضخامت/کاربرد حذف می‌شوند، بدون نگاشت حدسی به ویژگی‌ها. ورودی malformed، شناسه ناموجود، ویژگی غیر filterable یا انتخاب غیرفعال ۴۰۴ است.

Pagination فقط Query استاندارد را نگه می‌دارد، نه Query String خام درخواست. علاقه‌مندی Session حفظ شده و محتوای آن برای هر بازدیدکننده شخصی است؛ URL آن فهرست عمومی محبوبیت نیست.

## Facet و رابط

Facetها از محصولات منتشرشده و قابل نمایش در Context پایه دسته، جست‌وجو، قیمت، موجودی و پرچم‌ها استخراج می‌شوند. برای بازماندن گزینه‌های OR، انتخاب برند/برچسب/ویژگی در این مرحله محدودکننده گزینه‌های Facet نیست؛ این یک موتور شمارش disjunctive برای تک‌تک Facetها نیست. فقط مقدار فعال دارای انتساب در Context پایه و برند/برچسب دارای محصول در همان Context نمایش داده می‌شود.

تعداد کل از Pagination دیتابیس است؛ شمارش جداگانه هر گزینه ساخته نشده است. انتخابی که به‌علت شرایط پایه فعلاً گزینه قابل نمایش ندارد، در چیپ‌های فعال و Hidden Input حفظ می‌شود تا تغییر نامرتبط آن را پاک نکند.

کنترل‌های واقعی در قالب `template_shop_complete` و fallback مشترک‌اند. Select مرتب‌سازی سمت سرور متصل است؛ JS موجود فقط ارسال فرم را پس از تغییر Select انجام می‌دهد و دکمه ارسال در نبود JS موجود است. Drawer، Keyboard/Focus، RTL، کارت‌ها، Header، تنظیمات Block و Schema قبلی حفظ شده‌اند. وضعیت فعال، تعداد نتیجه، حذف تک‌گزینه و پاک‌کردن همه اضافه شده‌اند. متن بدون نتیجه فارسی، همراه امکان اصلاح فیلترهاست.

## SEO و Crawl

- آرشیو اصلی و دسته بدون فیلتر: Canonical همان صفحه؛ `page=2` به صفحه دوم اشاره می‌کند و به صفحه اول canonical نمی‌شود. تنظیم Robots قبلی دسته حفظ می‌شود.
- جست‌وجو، فیلتر، علاقه‌مندی، ترتیب جایگزین و حذف زیرشاخه: Noindex، با Canonical خود URL استاندارد؛ Follow مطابق قرارداد دسته باقی می‌ماند. Canonical ابزار عادی‌سازی هویت URL است و جای Noindex را نمی‌گیرد.
- ترکیب فیلتر بدون نتیجه و Pagination ناموجود با همان URL پاسخ ۴۰۴ می‌گیرند؛ صفحه نتایج خالی همچنان کنترل‌ها و مسیر پاک‌کردن را نشان می‌دهد. آرشیو اصلی/دسته معتبر بدون محصول، برای حفظ قرارداد قبلی پاسخ ۲۰۰ دارد.
- URLهای فیلترشده یا Pagination به Sitemap اضافه نشده‌اند. Sitemap محصول و SEO جزئیات و Preview تغییر نکرده‌اند.
- فرم‌ها، URLهای محدود و canonical و `rel=nofollow` روی لینک‌های تغییر فیلتر، تکثیر لینک‌های Crawl را کاهش می‌دهند. robots.txt به‌صورت عمومی بازنویسی نشده است تا Google بتواند Noindex را بخواند. Noindex تضمین کاهش Crawl نیست؛ تصمیم Disallow گسترده باید جداگانه و بر اساس وضعیت Crawl/ایندکس سایت گرفته شود.

منابع رسمی بررسی‌شده: [Faceted Navigation](https://developers.google.com/crawling/docs/faceted-navigation) و [Pagination](https://developers.google.com/search/docs/specialty/ecommerce/pagination-and-incremental-page-loading).

## Performance و حدود شواهد

رسانه‌های legacy و `mediaUsages.media` در آرشیو و مرتبط‌ها گروهی بارگذاری می‌شوند. ProductMediaService نیز Shared Usage را بارگذاری می‌کند تا فراخوانی تکراری Context/SEO دوباره Query رسانه نسازد. Facet Attribute و Value با eager loading آماده می‌شوند و Blade برای دریافت گزینه‌ها Query اجرا نمی‌کند. بلوک legacy محصولات منتخب هم روابط رسانه را eager-load می‌کند.

در Fixture SQLite با چهار و سپس دوازده محصول دارای رسانه مشترک و ویژگی، Queryهای غیر Settings برابر **۱۶ و ۱۶** بودند. کل Queryها **۸۹ و ۹۷** بود؛ SettingsService زیر PHPUnit عمداً Cache درخواست را خاموش می‌کند. SettingsService و قواعد آن تغییر نکرده‌اند. این اعداد اثبات زمان پاسخ، رفتار Production یا هزینه روی کاتالوگ بزرگ نیستند.

ایندکس‌های روابط فاز اول استفاده می‌شوند؛ در این فاز بدون اندازه‌گیری Index حدسی اضافه نشده است. LIKE شامل متن و Sort بر CASE قیمت ممکن است در حجم بالا به بررسی Query Plan و راهکار مستقل نیاز داشته باشند.

## آزمون و Pending

تست‌های Parser، موتور، UI/SEO، N+1 و SQL واقعی به مجموعه اضافه شده‌اند:

| اجرا | نتیجه |
|---|---|
| مجموعه Regression مرتبط، ۲۶ فایل | ۲۸۱ تست، ۲۰۷۹ assertion موفق |
| بازاجرای نهایی Parser/Engine/Performance، رابط و Template Preview پس از اصلاحات نهایی | ۵۲ تست، ۲۴۴ assertion موفق |
| MariaDB موقت واقعی | ۱ تست، ۶ assertion موفق |

اعداد بازاجرا با مجموعه اصلی هم‌پوشانی دارند. PHP lint پانزده فایل PHP فاز دوم، Pint، `git diff --check` و `node --check` موفق بودند. `npm run build` نیز موفق بود؛ هشدار حجم بیش از ۵۰۰ کیلوبایت مربوط به chunk موجود Hero است و در این فاز بازطراحی نشده است.

Query ترکیبی EXISTS، قیمت دقیق، تخفیف، جست‌وجوی wildcard literal، مرتب‌سازی و Pagination همراه اجرای Migrationهای پایه و فاز اول در **MariaDB 10.4.32 موقت** موفق بود؛ EXPLAIN نیز اجرا شد. Data directory و پورت مستقل با helper ایزولاسیون تأیید شد، دیتابیس تست حذف و پردازش موقت خاموش شد. این شاهد، اجرای Migration روی سایت یا تأیید همه نسخه‌های MySQL/MariaDB نیست.

Migration برنامه همچنان Pending است. مرورگر واقعی و Production بررسی نشده‌اند. پیش‌نیاز طراحی بعدی: اعمال Migration فاز اول در محیط مقصد، آماده‌سازی داده واقعی برند/ویژگی و فعال‌کردن filterable، انتخاب قالب مناسب و پذیرش مرورگری موبایل/RTL. مرحله بازطراحی در این فاز شروع نشده است.

این اجرا، کل Suite پروژه نیست. شکست شناخته‌شده تست قدیمی ترتیب Specifications که در گزارش فاز اول ثبت شده بود، موضوع این فاز نبوده و دوباره اجرا نشده است؛ فایل‌های آن قابلیت تغییر نکرده‌اند.

در بررسی نهایی، هر ۳۹ فایل Commitنشده فاز اول با Hash ابتدای فاز دوم یکسان بودند. فاز دوم ۱۱ فایل موجود را ویرایش و ۱۲ فایل جدید ایجاد کرده است؛ هیچ Commit یا Stage انجام نشده است.

## فایل‌های فاز دوم

موجود ویرایش‌شده:

- `app/Http/Controllers/ShopController.php`
- `app/Services/ProductQueryService.php`
- `app/Services/ProductPricingService.php`
- `app/Services/ProductMediaService.php`
- `app/Services/SeoService.php`
- `app/Services/SitemapService.php`
- `resources/js/app.js`
- `resources/views/partials/blocks/template_shop_complete.blade.php`
- `resources/views/partials/blocks/featured_products.blade.php`
- `resources/views/shop/index.blade.php`
- `tests/Feature/DynamicShopMobileToolbarTest.php`

جدید:

- `app/Shop/ShopFilters.php`
- `app/Services/ShopFilterParser.php`
- `app/Services/ShopCategoryTree.php`
- `app/Services/ShopArchiveService.php`
- `resources/views/shop/partials/filter-form.blade.php`
- `resources/views/shop/partials/filter-sort.blade.php`
- `resources/views/shop/partials/filter-summary.blade.php`
- `tests/Feature/ShopFilterParserTest.php`
- `tests/Feature/ShopFilterEngineTest.php`
- `tests/Feature/ShopFilterPerformanceTest.php`
- `tests/Feature/ShopFilterMySqlTest.php`
- `docs/shop-filter-query-engine-phase-2.md`
