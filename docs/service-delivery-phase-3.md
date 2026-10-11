# فاز ۳: تحویل نسخه‌دار، بررسی مشتری و بایگانی دوره‌ها

این گزارش مربوط به پیاده‌سازی فاز ۳ در ۱۴۰۵/۰۷/۱۹ است. فاز ۴ آغاز نشده است.

## بررسی مبنای موجود و migration

پیش از تغییر، اسناد `service-offering-phase-1.md` و `service-delivery-phase-2.md`، قراردادهای واقعی مدل‌ها و migrationها، سرویس‌های قرارداد/دوره/کار اجرایی، سیاست‌ها، عضویت مشتری، presenterها، controllerها، Blade و مسیرهای Portal بررسی شدند. وضعیت‌های `ClientProjectWorkItemStatus` و جدول رویدادهای فاز ۲ مبنای پیاده‌سازی هستند؛ مدل جدیدی برای کار یا دوره ایجاد نشده است.

در preflight، migrationهای فاز ۱ و ۲ در MySQL اجرا شده بودند و migration معلقی وجود نداشت. در پایان، فقط migration جدید زیر **Pending** است:

`2026_10_11_300000_add_private_deliveries_reviews_and_cycle_reports`

پایگاه اصلی migration نشده است. اجرای معمول `php artisan migrate` بر عهده استقرار است؛ `migrate:fresh` لازم نیست. migration جدید صرفاً جدول‌های جدید و دو ستون رویداد اضافه می‌کند. نام قیدها صریح و کوتاه است. اجرای واقعی DDL روی MySQL انجام نشده؛ SQL آن با grammar واقعی MySQL، بدون اتصال، بررسی شده است. اجرای migration و رفت‌وبرگشت آن در آزمون‌های SQLite حافظه‌ای بررسی شده است. rollback فاز ۳ سوابق جدید همین فاز را حذف می‌کند و روش اصلاح داده تاریخی نیست.

## فایل‌های ایجادشده

- `database/migrations/2026_10_11_300000_add_private_deliveries_reviews_and_cycle_reports.php`
- `app/Models/Concerns/ImmutableDeliveryRecord.php`
- `app/Models/ClientWorkItemDelivery.php`
- `app/Models/ClientWorkItemReview.php`
- `app/Models/ClientDeliveryFile.php`
- `app/Models/ClientCycleReport.php`
- `app/Services/ClientDeliveryAccess.php`
- `app/Services/ClientWorkItemDeliveryManager.php`
- `app/Services/ClientDeliveryPresenter.php`
- `app/Services/ClientCycleReportPublisher.php`
- `app/Services/ClientDeliveryFileDownload.php`
- `app/Http/Controllers/Client/ServiceDeliveryController.php`
- `app/Http/Controllers/Admin/ClientDeliveryFileDownloadController.php`
- `resources/views/client/projects/delivery-cycles.blade.php`
- `resources/views/client/projects/work-item.blade.php`
- `tests/Feature/ClientServiceDeliveryPortalTest.php`
- `docs/service-delivery-phase-3.md`

## فایل‌های تغییرکرده در این فاز

- `app/Enums/ClientProjectWorkItemStatus.php`: توضیح مسیر بررسی؛ وضعیت‌ها و انتقال‌های مدیریتی قبلی حفظ شده‌اند.
- `app/Models/ClientProjectWorkItem.php`: رابطه نسخه‌های تحویل.
- `app/Models/ClientProjectCycle.php`: رابطه گزارش‌های نسخه‌دار.
- `app/Services/ClientProjectWorkItemManager.php`: طبقه‌بندی رویدادهای عمومی برنامه/وضعیت؛ علت خصوصی همچنان در سابقه مدیریتی می‌ماند.
- `app/Policies/ClientProjectWorkItemPolicy.php`: خواندن کار عمومی توسط عضو و بررسی توسط مالک؛ نوشتن مدیریتی همچنان فقط مدیر فعال.
- `app/Filament/Resources/ClientProjectWorkItemResource.php`: ارسال نسخه، بارگذاری، فایل‌ها، تصمیم‌ها، تاریخچه و فیلتر انتظار تأیید.
- `app/Filament/Resources/ClientProjectCycleResource.php`: انتشار و نمایش گزارش در همان رابط دوره.
- `app/Services/ClientProjectPresenter.php`: ارائه حالت تحویل.
- `app/Services/ClientServicesDashboardPresenter.php`: نمایش تعهدات؛ حذف پروژه وظیفه‌ای از جمع زمانی؛ جلوگیری از سهمیه/مازاد کاذب در ترکیبی بدون سهمیه.
- `app/Http/Controllers/Client/ProjectController.php`: انتخاب دوره واقعی و ارائه داده امن تحویل.
- `resources/views/client/projects/show.blade.php`
- `resources/views/client/projects/index.blade.php`
- `resources/views/client/dashboard.blade.php`
- `config/filesystems.php`: دیسک اختصاصی خصوصی.
- `routes/web.php`: مسیرهای افزوده مشاهده/بررسی/دانلود؛ مسیرهای قبلی حفظ شده‌اند.
- `tests/Feature/ServiceDeliveryMigrationCompatibilityTest.php`: بررسی نام‌های SQL فاز ۳ در کنار فازهای قبلی.

## قراردادهای پایگاه داده

| جدول | قرارداد |
| --- | --- |
| `client_work_item_deliveries` | کار، شماره ترتیبی نسخه، ارسال‌کننده/زمان، پیام، دسترسی و snapshot نیاز به تأیید و اصلاح مجاز؛ یکتایی کار/نسخه |
| `client_work_item_reviews` | یک تصمیم برای هر نسخه، تصمیم‌گیرنده/زمان/دلیل؛ یکتایی delivery مانع دو تصمیم متضاد است |
| `client_delivery_files` | نسخه تحویل، uploader، disk/path خصوصی، نام اصلی، MIME واقعی، حجم، SHA-256، دسترسی، زمان؛ باینری خارج از DB |
| `client_cycle_reports` | دوره، شماره نسخه، وضعیت انتشار، نتیجه، snapshot عمومی، منتشرکننده/زمان؛ یکتایی دوره/نسخه |

به رویدادهای قبلی `visibility` و `kind` افزوده شده است؛ پیش‌فرض هر دو `internal` است. تاریخچه قبلی تغییر محتوا یا backfill عمومی ندارد. ارتباط‌های تحویل، بررسی، فایل و گزارش حذف آبشاری سوابق تجاری ندارند؛ ارتباط کاربر مانند قراردادهای قبلی در صورت حذف کاربر nullable می‌شود.

## چرخه تحویل و بررسی

۱. مدیر کار را در رابط فاز ۲ برنامه‌ریزی و برای مشتری قابل نمایش می‌کند، سپس به `in_progress` می‌برد.

۲. اقدام «ارسال نسخه تحویل» پیام و فایل‌های عمومی/داخلی را ثبت می‌کند. سرور cycle و work item را قفل و `lock_version` فرم را بررسی می‌کند؛ نسخه جدید شماره ترتیبی می‌گیرد و کار `submitted` می‌شود. کار نیازمند تأیید باید پیش از ارسال عمومی باشد. فرم معمول تغییر وضعیت دیگر جایگزین ارسال نسخه نیست.

۳. عضو مشتری نسخه‌های عمومی را می‌بیند. فقط مالک فعلی مشتری می‌تواند آخرین نسخه منتظر بررسی را تأیید یا با دلیل درخواست اصلاح کند. دسترسی از عضویت/کاربر/مشتری فعلی خوانده می‌شود؛ مسیر mutation از locking read استفاده می‌کند. نسخه قدیمی، تصمیم تکراری و lock version قدیمی رد می‌شوند. قفل دوره/کار، compare-and-swap و قید یکتایی تصمیم، تصمیم‌های متضاد را مهار می‌کنند.

۴. تأیید، `accepted_at` را ثبت و تعهد را تکمیل می‌کند؛ مدیر از انتقال موجود `accepted → completed` برای بستن اجرا استفاده می‌کند. درخواست اصلاح، همان نسخه را دارای سابقه review مستقل می‌کند؛ مدیر کار را دوباره به اجرا می‌برد و ارسال بعدی **نسخه جدید** می‌سازد. حد اصلاح از snapshot تعهد کنترل می‌شود؛ صفر یعنی اصلاح قراردادی مجاز نیست. فایل و پیام نسخه قبلی تغییر یا حذف نمی‌شوند.

۵. برای کار بدون الزام تأیید، تکمیل مدیریتی فاز ۲ حفظ شده است. ارسال‌های قدیمی فاز ۲ که رکورد delivery ندارند، از اقدام ارسال می‌توانند نخستین نسخه واقعی را دریافت کنند؛ پیش از آن قابل بررسی مشتری نیستند. وضعیت review یک مقدار مشتق از سابقه بررسی است و برای تغییر آن محتوای delivery بازنویسی نمی‌شود.

## تجربه مدیریت و Portal

همان WorkItem Resource و Cycle Resource گسترش یافته‌اند؛ صفحه مدیریت موازی ساخته نشده است. مدیر نسخه‌ها، فایل‌های عمومی و داخلی، زمان/عامل ارسال، تصمیم و نظر مشتری، موارد منتظر تأیید و نسخه‌های گزارش را می‌بیند. هر ارسال فایل‌های خودش را دارد؛ مدیریت محتوای منتشرشده از طریق ارسال نسخه بعدی انجام می‌شود، نه ویرایش یا حذف فایل قدیمی.

در جزئیات پروژه، `time` همان رابط ساعتی را حفظ می‌کند؛ `tasks` تعهدات، مقدار تکمیل/باقی‌مانده مستقل هر نوع، برنامه، موعد، مسئول، وضعیت و تحویل‌ها را نمایش می‌دهد؛ `hybrid` این بخش را در کنار زمان نشان می‌دهد. ترکیبی بدون سهمیه با عبارت «بدون سقف زمانی» نمایش داده می‌شود. نمودار درصد ساختگی برای تحویل‌های با واحد متفاوت ساخته نشده است.

انتخاب دوره از مرزهای واقعی `starts_at` و `ends_at` با پایان غیرشامل استفاده می‌کند. قیمت، شرایط، خدمت و بسته از snapshot همان دوره خوانده می‌شوند. مسیرهای قدیمی پروژه و dashboard پابرجا هستند؛ لینک کار اجرایی به مسیر افزوده حساب کاربری می‌رود. انتخاب customer در تمام لینک‌ها و فرم‌های جدید حفظ می‌شود.

## فایل خصوصی و مجوز

دیسک `service_deliveries` در `storage/app/service-deliveries-private` قرار دارد، خارج از ریشه local قابل serve و خارج از public storage. `serve=false` و دسترسی فایل/پوشه خصوصی است. فایل نام تصادفی دارد؛ برای مشتری storage URL، مسیر فیزیکی یا payload خام ارائه نمی‌شود.

فقط فایل آپلودشده پذیرفته می‌شود؛ مسیر ذخیره ورودی معتبر نیست. MIME از محتوای فایل تشخیص داده می‌شود و فهرست مجاز شامل اسناد، تصویر، متن، archive و صوت/ویدیوی عمومی است. سقف دامنه/فرم ۱۰۰ MiB برای هر فایل و ۱۰ فایل برای هر گروه دسترسی است. محدودیت upload موقت Livewire و PHP/server ممکن است پایین‌تر باشد؛ تنظیمات عمومی upload موجود برای افزایش این سقف تغییر نکرده‌اند.

دانلود مشتری ابتدا پروژه را به customer انتخاب‌شده محدود می‌کند، سپس تعلق کار/نسخه/فایل، عضویت فعال فعلی و visibility هر سه سطح را کنترل می‌کند. فایل داخلی فقط برای مدیر مجاز پروژه قابل دانلود است. پاسخ attachment با `nosniff` و `private, no-store` است. با لغو عضویت یا خصوصی‌شدن کار، لینک دانلود قبلی مجوز دسترسی ایجاد نمی‌کند. فایل‌های موفق قبلی باقی می‌مانند؛ شکست ثبت ارسال، فایل‌های تازه آن تلاش را پاک می‌کند. ذخیره‌ساز این فاز local است؛ آپلود multipart/resumable و نگهداری فضای ابری در این فاز اضافه نشده‌اند.

## تاریخچه و گزارش

timeline از رویدادهای فاز ۲ با طبقه‌بندی صریح عمومی برنامه/وضعیت استفاده می‌کند. پیام/علت داخلی، actor مدیریتی و payload خام رویداد ارائه نمی‌شوند. علت درخواست اصلاح از review مشتری نمایش داده می‌شود. رویدادهای قبلی بدون طبقه‌بندی عمومی، خصوصی باقی می‌مانند.

انتشار گزارش فقط برای دوره پایان‌یافته وظیفه‌ای یا ترکیبی مجاز است. snapshot مستقل شامل مرز دوره، قیمت/شرایط تاریخی، تعهدات و مقادیر، وضعیت دقیق کارهای عمومی، نسخه‌های تحویل، فایل‌های عمومی، بررسی‌ها و timeline امن در لحظه انتشار است. گزارش داخلی/منتشرنشده به مشتری ارائه نمی‌شود. گزارش موفق در صورت هر تعهد ناقص یا تأییدنشده رد می‌شود؛ کار لغوشده مقدار تکمیل تعهد تولید نمی‌کند. کار اضافی در تعهدات جمع نمی‌شود و وضعیت لغو/نقص آن در snapshot خودش حفظ می‌شود؛ «موفق» به معنای تکمیل تعهدات قرارداد است، نه اعلام تکمیل همه کارهای اضافی.

گزارش، فایل، delivery و review در مسیر Eloquent immutable هستند. اصلاح گزارش نسخه جدید با snapshot جدید می‌سازد و نسخه قبلی ثابت می‌ماند. تغییر نام یا قیمت کاتالوگ، سوابق دوره و گزارش را تغییر نمی‌دهد. مشاهده فایل از گزارش تاریخی نیز مجوز فعلی دانلود را کنترل می‌کند.

## سازگاری و بررسی

هیچ تغییری در محاسبات مالی، Invoice/InvoiceItem، snapshotهای صورتحساب، پرداخت، قیمت عملیاتی Service، URLs عمومی، Pricing Cards، Block Registry، Template Runtime یا Action Foundation انجام نشده است. داده‌های Activity و allocation/overage ساعتی حفظ شده‌اند. عملیات سفارش عمومی، درگاه پرداخت، workflow و فاز ۴ اضافه نشده‌اند.

بررسی محدود انجام‌شده:

```text
php artisan test --compact tests/Feature/ClientServiceDeliveryPortalTest.php tests/Feature/ServiceDeliveryMigrationCompatibilityTest.php tests/Feature/ClientProjectTaskDeliveryTest.php tests/Feature/ClientProjectCycleBillingTest.php tests/Feature/ClientServicesDashboardEnhancementTest.php
```

این انتخاب، جداسازی مشتری، خواندن اعضا، بررسی مالکان و لغو نقش، دلیل و تعداد اصلاح، حفظ نسخه/فایل قبلی، تصمیم قدیمی/تکراری/متضاد، دانلود خصوصی، فایل نامعتبر، عدم نشت اطلاعات، تاریخچه گزارش و قیمت، فرم واقعی upload در Filament، migration رفت‌وبرگشت و محدودیت شناسه MySQL، دوره‌های فاز ۲ و سازگاری ساعتی/صورتحساب/dashboard را پوشش می‌دهد. بررسی رقابت از طریق دو اقدام با نسخه قدیمی/تصمیم متضاد و قراردادهای قفل/قید انجام شده است؛ آزمون هم‌زمان چند اتصال واقعی MySQL اجرا نشده است.

نتیجه نهایی: **۴۰ آزمون موفق، ۱۰۲۸ assertion** در حدود ۱۵ ثانیه. کل مجموعه آزمون اجرا نشده است. PHP lint روی **۲۷ فایل مرتبط با صفر خطا** و Pint روی همین فایل‌ها موفق بود. `git diff --check` نیز خطای whitespace نداشت.

محدودیت‌های باقی‌مانده: اعلان خودکار تصمیم/موعد، uploader بزرگ و resumable، ذخیره‌ساز ابری، سفارش عمومی، پرداخت آنلاین و workflow قابل تنظیم همچنان کار جداگانه آینده هستند. هیچ‌کدام خودکار آغاز نشده‌اند.
