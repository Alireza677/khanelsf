# Shop Stabilization — اصلاحات هدفمند

تاریخ: ۲۰۲۶-۱۱-۰۹. محدوده: پنج اصلاح تعیین‌شده؛ هیچ بازطراحی یا تغییر CSS/JS انجام نشد. گزارش UI Audit قبلی در گفت‌وگو بود و فایل مستقلی در مخزن نداشت.

## Preflight و حفظ داده

شروع: ۲۱ فایل Modified و ۴۱ فایل Untracked؛ Hash هر ۶۲ فایل ثبت شد. این مرحله هیچ reset، clean، stash، Stage یا Commit ندارد. اسناد فازهای اول/دوم، Reference Block، Template/Recipe/Collection و Commerce/Freshness و اسکیل کامل ui-ux-pro-max مبنای بررسی بودند.

برخلاف وضعیت Audit قبلی، در شروع این مرحله Migration `2026_11_09_000001_add_universal_product_catalog` **از قبل ثبت شده بود**. اتصال محلی از نوع mysql، سرور MariaDB 10.4.32 و جداول InnoDB/utf8mb4_unicode_ci هستند. ستون‌های nullable جدید unsigned bigint، هفت جدول و Foreign Keyها، از جمله قید مرکب Assignment–Value، با SELECT بررسی شدند. این مرحله روی دیتابیس برنامه، Staging یا Production هیچ Migration یا تغییر داده اجرا نکرد.

دیتابیس محلی: ۱۱ Product، چهار Category و جدول‌های کاتالوگ جدید خالی بودند و در بررسی پایانی همین شمارش حفظ شد. قالب ۸ همچنان published/default از نوع shop_index با بلوک کامل است؛ قالب ۹ draft است؛ product_category قالب ثبت‌شده ندارد. Fingerprint داده دو Template قبل و بعد یکسان بود. بازخوانی رسانه نیز فقط یک مرجع تصویر شاخص برای ۱۱ محصول منتشرشده نشان داد. این بررسی صرفاً درباره اتصال محلی است، نه محیط استقرار دیگر.

## اصلاحات

- `template_shop_complete`: همان H2 مخفی و `aria-labelledby`، مقدار nonblank از `products_title` می‌گیرد؛ نبود/null/فاصله به «محصولات فروشگاه» برمی‌گردد. عنوان بصری یا Schema/version تازه ساخته نشد. این بلوک legacy-flat از مسیر ذخیره فعلی و Block identity عبور می‌کند؛ Normalizer اختصاصی که این فیلد را حذف کند ندارد. ذخیره واقعی Filament و خروجی در تست پوشش داده شدند.
- Hero: فقط `maxlength=200` اضافه شد. Parser، URL و اعتبارسنجی سرور تغییر نکردند؛ مرز فارسی و ورودی Unicode بیش از حد تست شد.
- `ShopArchiveService::viewData`: قرارداد داده مشترک عمومی/Preview را با همان Query، Facet، دسته، قیمت و Pagination می‌سازد. Controller عمومی همچنان Module Gate، Canonical Redirect و Template Resolution خودش را دارد.
- Preview: پس از Authorization موجود، برای shop_index/product_category همان Parser/validate/viewData اجرا می‌شود. `context_id` فقط برای انتخاب نمونه است و وارد Parser نمی‌شود. انتخاب دسته باید قابل نمایش باشد؛ محصولات فقط published و رسیده به زمان انتشار هستند. Draft بودن Template، امکان Preview مدیر هنگام خاموش‌بودن فروشگاه و noindex/nofollow باقی‌اند؛ Product Detail Runtime تغییر نکرده است. فرم‌ها و URLهای تولیدشده همچنان مقصد عمومی دارند؛ Preview مسیر ویرایش داده یا انتشار Template نیست.
- متن‌ها: Defaults بلوک کامل، Seed نصب تازه و Fallback توضیح SEO آرشیو فارسی شدند؛ «Out of stock» در کارت مشترک Shop به «ناموجود» تبدیل شد. این تغییر متن در Featured/Related/Content Grid مصرف‌کننده همان کارت نیز دیده می‌شود؛ Partial قیمت و Shared Collection تغییر نکردند. Seeder اجرا نشد. `Categories` ذخیره‌شده در Template ۸ عمداً حفظ شد و اصلاح آن باید از پنل با تصمیم مدیر انجام شود.
- Empty State: ترکیب معتبر با صفر نتیجه HTTP 200 دارد؛ دسته/فیلتر نامعتبر ۴۰۴، صفحه بیشتر از lastPage برابر ۴۰۴. URL استاندارد، Redirect، Canonical، Noindex و نگهداری انتخاب‌های خارج از Facet حفظ شدند. انتظار دو تست موتور با قرارداد تازه همسو شد.
- تست قدیمی Complete Shop در TemplateBuilder: اکنون Redirect قرارداد فاز دوم را بررسی و برای آرشیو دسته Template جداگانه در Fixture ایجاد می‌کند؛ Template عمومی به دسته تحمیل نمی‌شود.

## آمادگی Migration و بازیابی

Migration هیچ DML، Backfill، مقدار حدسی یا Seeder ندارد. دو FK nullable به جدول‌های قبلی و هفت جدول تازه اضافه می‌کند. Pricing، تاریخچه، موجودی، Snapshot سفارش و روابط قدیمی بازنویسی نمی‌شوند. فاز دوم حتی بدون انتخاب فیلتر، برای Facetها به جدول‌های تازه نیاز دارد؛ ازاین‌رو کد و Schema باید در مقصد هماهنگ شوند.

در مقصد اعمال‌نشده، اجرای ALTER/FK ممکن است منتظر Metadata Lock بماند یا نوشتن روی Product/Category را متوقف کند؛ این توقف می‌تواند خواندن محصول در Cart/Checkout و Import را نیز معطل کند. مدت Lock و رفتار Online روی دیتاست Production اندازه‌گیری نشده است. Import Preview قدیمی ممکن است با افزوده‌شدن ستون برند Fingerprint متفاوت ببیند؛ باید از مسیر Preview مجدد فعلی استفاده شود.

DDL در MariaDB Implicit Commit دارد؛ Migration چنددستوری این پروژه تضمین Rollback تراکنشی ندارد و برای اجرای مجدد پس از شکست جزئی idempotent نیست. در شکست جزئی، اجرای کور مجدد، rollback خودکار یا دست‌کاری دستی رکورد Migration مجاز نیست؛ ابتدا Metadata کامل با خروجی مورد انتظار مقایسه، سپس ترمیم مشخص و بازبینی‌شده یا بازیابی Backup انجام شود. down جدول‌های جدید و ارتباط‌ها را حذف می‌کند؛ پس از ورود داده تجاری راه بازگشت عادی نیست. برای مشکل کد، Roll-forward اصلاحی یا بازگشت نسخه کد همراه حفظ Schema افزایشی بر حذف داده ترجیح دارد؛ سازگاری نسخه بازگشتی باید جدا بررسی شود.

پیش از استقرار مقصد: Backup سازگار و قابل بازیابی از DB و فایل‌های پایدار، نسخه کد و تنظیمات؛ نگهداری نسخه خارج از سرور و آزمون Restore در محیط ایزوله. از مسیر فعلی Backup خصوصی/دانلود مدیر و DatabaseBackupProducer استفاده شود؛ این سرویس dump را با single-transaction تولید می‌کند ولی برخی جداول عملیاتی طبق config حذف می‌شوند، پس دامنه Backup قبل از پذیرش بازیابی باید کنترل شود. در این مرحله Backup/Restore واقعی اجرا نشده است. اجرای Migration مقصد همچنان به تأیید صریح نیاز دارد.

منابع فنی: [MariaDB ALTER TABLE](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table)، [Metadata Locking](https://mariadb.com/docs/server/reference/sql-statements/transactions/metadata-locking)، [Implicit Commit](https://mariadb.com/docs/server/reference/sql-statements/transactions/sql-statements-that-cause-an-implicit-commit).

## موارد UI/UX خارج از اجرا

تمام موارد زیر Not Visually Verified هستند؛ ابزار مرورگر فهرست خالی برگرداند.

| مورد | علت/اثر | اصلاح محدود احتمالی | ریسک و تصمیم |
|---|---|---|---|
| اسکرول داخلی | ارتفاع محدود و overflow-y در product-scroll؛ پیمایش تو‌در‌تو دسکتاپ | تغییر فقط همان Container | متوسط؛ نیازمند تأیید |
| تکرار عنوان بدون تصویر | Placeholder عنوان + عنوان اصلی | تصمیم درباره Placeholder | کم؛ نیازمند تأیید |
| تصاویر ناقص | Audit قبلی فقط یک مرجع برای ۱۱ محصول یافت؛ این مرحله رسانه وارد نکرد | تکمیل رسانه واقعی | کم؛ داده و تأیید مدیر |
| هم‌ترازی قیمت | عنوان متغیر و بلوک Freshness/تخفیف | بررسی واقعی سپس رزرو فضای محدود | متوسط؛ نیازمند تأیید |
| موبایل | دو ستون، Drawer در ۹۰۰px، چیدمان کوچک در ۵۶۰px | پذیرش مرورگری پیش از تغییر | متوسط؛ ساختار فعلی حفظ شد |
| Reduced Motion | اسلایدر/Drawer شرط اختصاصی ندارند | محدودکردن Motion در همان تعامل | کم تا متوسط؛ نیازمند تأیید |
| کنتراست | تصویر/Overlay و چند سطح سفید ثابت | اندازه‌گیری Theme واقعی و اصلاح محلی | متوسط؛ نیازمند تأیید |
| تفاوت Shop/Category | Template مستقل دسته ثبت نشده؛ Fallback فعال است | تصمیم انتخاب Template دسته | متوسط؛ بدون ساخت/انتشار خودکار |

## شواهد و محدودیت‌ها

| اجرای نهایی | نتیجه |
|---|---|
| Regression مرتبط در ۲۵ فایل، شامل ۱۱ تست Stabilization و Cart/Checkout/Import/Freshness/Runtime/Preview/Filter | ۲۸۴ تست، ۲۱۶۶ assertion موفق؛ ۳ دقیقه و ۴ ثانیه |
| TemplateBuilder با استثنای دو تست قدیمی Gallery | ۲۲ تست، ۷۴ assertion موفق |
| Migration/Query روی MariaDB 10.4.32 موقت | یک تست، شش assertion موفق |
| PHP lint و Pint در ۹ فایل PHP و git diff --check | موفق |

دو تست قدیمی `test_gallery_single_template_fully_replaces_default_gallery_layout` و `test_gallery_category_template_applies_to_galleries_in_that_category` در اجرای کامل TemplateBuilder شکست خوردند: انتظار ۲۰۰ دارند، ولی GalleryController فعلی برای show/category صریحاً abort(404) دارد. مسیر و Controller گالری در این مرحله تغییر نکردند؛ این دو مورد حذف یا skip دائمی نشدند و فقط از اجرای مرتبط نهایی کنار گذاشته شدند. این اجرا کل Suite پروژه نیست؛ شکست قدیمی ترتیب Specifications در فاز اول نیز در محدوده این اجرا نبود.

اجرای اولیه بزرگ به سقف حافظه پیش‌فرض ۱۲۸MB رسید؛ اجرای نهایی با `php -d memory_limit=512M vendor/bin/phpunit ...` موفق شد و تنظیم PHP برنامه تغییر نکرد. همچنین تست تازه ویرایش Template به‌علت Cache استاتیک موجود انتخابگر رسانه روی تست بعدی اثر می‌گذاشت؛ فقط Fixture جدید با ذخیره/بازگردانی Cache در setUp/tearDown ایزوله شد. Cache محصول تغییر نکرد و تست Media Picker در اجرای نهایی موفق بود.

تست‌های PHPUnit از تنظیم testing/SQLite :memory: استفاده می‌کنند؛ تست SQL واقعی، سرور و Data directory موقت مستقل با helper بررسی datadir داشت. دیتابیس تصادفی تست در finally حذف و سرور موقت خاموش شد. هیچ نتیجه SQLite/SQL ایزوله معادل پذیرش Production، Browser یا هم‌زمانی مقصد نیست. Queryهای آرشیو غیر Settings در Fixture چهار/دوازده محصول همچنان ۱۶/۱۶ و کل Queryها ۸۹/۹۷ بودند؛ SettingsService عمداً زیر تست Cache درخواست ندارد. اجرای Build برای این اصلاحات Blade/PHP لازم نبود و انجام نشد.

از ۶۲ فایل اولیه، ۵۶ فایل Hash یکسان دارند؛ شش فایل قبلی در محدوده همین اصلاحات تغییر کردند. پنج فایل tracked که ابتدای این فاز تغییر نداشتند نیز ویرایش و دو فایل جدید (این سند و ShopStabilizationTest) ایجاد شدند. پایان: ۲۶ Modified و ۴۳ Untracked، بدون Stage/Commit. CSS، JS، همه فایل‌های فاز اول، Pricing، Freshness، Import، Cart/Checkout، TemplateService، Registry، Recipe و Product Detail Runtime در این مرحله تغییر نکردند.
