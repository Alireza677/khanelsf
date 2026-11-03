# اتصال گالری پروژه به کتابخانه رسانهٔ King CMS

تاریخ: ۲۰۲۶-۱۱-۰۳

## یافته‌های معماری و علت اصلی

| موضوع | شواهد سورس |
| --- | --- |
| تصویر شاخص | `ProjectResource.php` از `ViewField('featured_media_id')` و View مشترک `media-library-picker` استفاده می‌کند. Hydration و ذخیره با `mediaLibraryFeaturedState` و `syncFeaturedImage` انجام می‌شوند. |
| علت بازشدن File Picker | گالری پروژه مستقیماً `SpatieMediaLibraryFileUpload::make('gallery')->multiple()->reorderable()` بود؛ این Component برای آپلود فایل است، نه انتخاب Reference از کتابخانه. |
| Picker چندانتخابی موجود | `resources/views/filament/forms/components/media-library-multiple-picker.blade.php` از قبل در محصولات و خدمات استفاده می‌شد. مودال جدید ساخته نشد. |
| قرارداد رسانه | `UsesMediaLibraryImages`، `HasSharedMediaUsages` و جدول موجود `media_usages`؛ شناسهٔ رسانه، مالک polymorphic، نام Collection و `order_column` نگهداری می‌شوند. |
| قرارداد گالری پروژه | Collection برابر `gallery` است. `Project::galleryImages()` ابتدا Shared Usageهای مرتب‌شده را می‌خواند و در نبود آن‌ها به Mediaهای قدیمی متعلق به پروژه برمی‌گردد. |
| تصاویر قدیمی | قرارداد موجود `existing:{media_id}` برای تصاویر متعلق به همان رکورد حفظ شد. تصویر موجود در کتابخانه با Media ID عددی انتخاب می‌شود. |
| بارگذاری مرکزی | `MediaResource/Pages/UploadMedia.php` فایل جدید را با هویت canonical در `media_library` ذخیره می‌کند؛ Picker موجود آپلود داخلی نداشت. |

بررسی Picker مشترک نشان داد انتخاب‌ها فوراً در Form State نوشته می‌شدند؛ دکمهٔ تأیید فقط مودال را می‌بست. Thumbnailها هم به ترتیب فهرست کتابخانه نمایش داده می‌شدند و Drag & Drop نداشتند. دادهٔ تصاویر به متغیر سراسری `@once` وابسته بود که با فیلدهای پنهان/رندرهای Livewire قابل اتکا نیست.

یک نقص مرتبط در Synchronization وجود داشت: تصویر قدیمی نگه داشته می‌شد، اما اگر تصویر کتابخانه نیز انتخاب می‌شد، فقط تصویر کتابخانه Usage داشت و `galleryImages()` تصویر قدیمی را نمایش نمی‌داد. ترتیب ترکیبی هم قابل بازیابی نبود.

## اصلاح انجام‌شده

- گالری Create و Edit پروژه از همان ViewField چندانتخابی استاندارد استفاده می‌کند؛ دکمهٔ «انتخاب تصاویر گالری» مودال را باز می‌کند و به File Input متصل نیست.
- دادهٔ هر Picker داخل همان `x-data` با `@js($images)` قرار می‌گیرد؛ تصویر شاخص همچنان از Picker تک‌انتخابی قبلی استفاده می‌کند.
- انتخاب موقت در `draft` نگهداری می‌شود؛ فقط «تأیید انتخاب» آن را در Form State قرار می‌دهد. انصراف، ضربدر، Escape و کلیک Backdrop انتخاب تأییدنشده را اعمال نمی‌کنند.
- نام و عنوان رسانه قابل جست‌وجو هستند. وضعیت انتخاب با Border، نشان SVG و `aria-pressed` مشخص می‌شود و تعداد انتخاب‌ها یک پیام Status با متن کامل دارد.
- Thumbnailها به ترتیب Form State و پیش از ذخیرهٔ پروژه نمایش داده می‌شوند. حذف اتصال، Drag & Drop با Sortable موجود Filament و دکمه‌های جایگاه قبلی/بعدی فراهم‌اند.
- Grid تصاویر براساس عرض Container تطبیق می‌یابد. `x-show` روی ظرف بیرونی است تا Alpine، `display: grid` را حذف نکند. برای موقعیت نشان انتخاب و حذف از Logical Properties و رنگ‌های CSS Variable خود Filament استفاده شده است؛ وابستگی به Utility تولیدنشده اضافه نشده است.
- مودال RTL، عنوان قابل دسترس، Focus Trap و Scroll Lock دارد. انیمیشن مرتب‌سازی غیرفعال است و Transition جدیدی برای مودال اضافه نشده است.
- ذخیره در Hookهای موجود `afterCreate` و `afterSave` با `syncMediaLibraryCollection` انجام می‌شود. رسانه‌های مرکزی کپی، آپلود مجدد یا جابه‌جا نمی‌شوند.
- Helper مشترک برای حفظ ترتیب ترکیبی، تصاویر قدیمیِ معتبر همان رکورد را نیز Reference می‌کند. Hydration شناسهٔ آن‌ها را دوباره با `existing:` برمی‌گرداند. تصاویر متعلق به رکورد دیگر پذیرفته نمی‌شوند؛ تکرارها در UI و Sync حذف می‌شوند.
- حذف رسانهٔ مرکزی از گالری فقط Usage آن را حذف می‌کند؛ Media ID، فایل، URL و Metadata مرکزی باقی می‌مانند. رفتار قدیمی حذف فایلِ اختصاصی و غیرمشترک همان رکورد تغییر نکرده است.

### مسیر بارگذاری جدید

زیرساخت فعلی Picker، آپلود داخل خود مودال ندارد. به‌جای ساخت آپلودکنندهٔ موازی، در مودال پروژه لینک «بارگذاری تصویر جدید (پنجره جدید)» به UploadMedia موجود و دکمهٔ «تازه‌سازی کتابخانه» اضافه شده است. تازه‌سازی، فهرست را از سرور دوباره می‌گیرد و فقط Picker با State Path مربوط را به‌روز می‌کند؛ Form State و انتخاب موقت تغییر نمی‌کنند. لینک بارگذاری تابع دسترسی موجود MediaResource است. انتخاب رسانهٔ تازه‌بارگذاری‌شده پس از بازگشت و تازه‌سازی ممکن است؛ خود آپلود در پنجرهٔ جدا انجام می‌شود.

## سایر Domainها و سازگاری

محصول و خدمت از قبل همین Picker، `gallery_media_ids`، Collection برابر `gallery` و Helper مشترک را داشتند. Schema و Hookهای فرم آن‌ها تغییر نکردند؛ بهبودهای مشترک انتخاب/لغو/ترتیب و اصلاح حفظ تصاویر قدیمی به همان قرارداد اعمال می‌شوند و تست رگرسیون دارند.

`GalleryResource` نیز FileUpload دارد، اما Collection آن `images` و Domain آن گالری مستقل است؛ فرم آن تغییر نکرد. Picker تک‌انتخابی، مدل Project، Blockهای عمومی، Template Runtime، Media Foundation Schema و مسیر عمومی پروژه تغییر نکرده‌اند. Migration یا وابستگی جدید ایجاد نشده است.

## استفاده از ui-ux-pro-max

فایل سراسری `C:/Users/Marco/.agents/skills/ui-ux-pro-max/SKILL.md` در همین گفتگو خوانده شد. جست‌وجوهای متمرکز UX برای انتخاب چندگانه، پیام شمارش، Focus و مودال بررسی شدند. جست‌وجوی Stack لاراول نتیجهٔ مرتبط نداشت و جست‌وجوی جایگزین UX مبنا قرار گرفت.

نتیجه در پیاده‌سازی: Status شامل تعداد و موضوع انتخاب، تغییرات موقت قابل لغو، نشانهٔ انتخاب فراتر از رنگ، Focus Trap، کنترل‌های دارای نام، جایگزین دکمه‌ای برای کشیدن، Grid تطبیقی، ارتفاع مودال محدود به Viewport و اسکرول مستقل فهرست. رنگ‌ها و دکمه‌ها از Filament موجود می‌آیند؛ سیستم طراحی یا مودال موازی اضافه نشده است.

## فایل‌های این اصلاح

| فایل | تغییر |
| --- | --- |
| `app/Filament/Resources/ProjectResource.php` | فیلد گالری مشترک، Hydration و Action تازه‌سازی |
| `app/Filament/Resources/ProjectResource/Pages/CreateProject.php` | Sync Referenceهای گالری بعد از ایجاد |
| `app/Filament/Resources/ProjectResource/Pages/EditProject.php` | Sync Referenceهای گالری بعد از ذخیره |
| `app/Filament/Resources/Concerns/UsesMediaLibraryImages.php` | حفظ ترتیب تصاویر قدیمی/مرکزی و پاک‌سازی Cache رابطه پس از Sync |
| `resources/views/filament/forms/components/media-library-multiple-picker.blade.php` | همان مودال مشترک با انتخاب موقت، Thumbnail مرتب، Drag & Drop، جست‌وجو، RTL و کنترل‌های دسترس‌پذیر |
| `tests/Feature/ProjectMediaLibraryGalleryTest.php` | شش تست رفتاری جدید برای Create/Edit، رسانهٔ قدیمی، Upload/Refresh، Domainهای مشترک و صفحهٔ عمومی |
| `docs/audits/project-gallery-media-library.md` | این گزارش |

تغییرات قبلی کارت‌های آمار پروژه در Workspace از کار قبلی همین گفتگو هستند و بخشی از این اصلاح گالری نیستند.

## اعتبارسنجی و معیارهای پذیرش

- **PHPUnit:** مجموع ۴۰ تست و ۳۸۷ Assertion موفق؛ شامل تست جدید پروژه و مجموعه‌های `SharedMediaAssetTest`، `ProductMediaLibraryGalleryTest`، `BlockMediaPickerTest`، `DynamicShopMediaPickerTest`، `MediaLibraryDetailsTest`، `ProjectMetricsFoundationTest`، `ProjectCaseStudyFoundationTest`، `ProjectBlocksFoundationTest`، `ProductMediaSeoContractTest` و `ServiceFoundationTest`.
- **Build:** `npm run build` موفق؛ هشدار غیرمسدودکنندهٔ Chunk بزرگ `hero-dotted-surface` باقی است.
- **کیفیت:** Pint برای PHPهای همین اصلاح و `git diff --check` موفق.
- **محیط دادهٔ تست:** SQLite در حافظه و Storage آزمایشی؛ Migration اجرایی روی دیتابیس واقعی و Seed دادهٔ سایت انجام نشد.

| سناریو | شواهد |
| --- | --- |
| ۱. ایجاد و انتخاب چند تصویر موجود | تست Livewire واقعی Create، اثبات ترتیب، ثابت‌ماندن تعداد Media و فایل‌ها |
| ۲. بازیابی تصاویر در Edit | تست Hydration و وجود دادهٔ Thumbnail در HTML؛ بررسی ترتیب Thumbnail در مرورگر محلی |
| ۳. افزودن به گالری قبلی | تست Edit و ترکیب تصویر اختصاصی قدیمی با Reference جدید |
| ۴. حذف اتصال بدون حذف فایل مرکزی | تست حذف یک/همهٔ انتخاب‌ها، حفظ Mediaها، فایل‌ها و تصویر شاخص |
| ۵. جلوگیری از تکرار | تست Sync با شناسهٔ تکراری و بررسی Toggleهای مکرر در مرورگر |
| ۶. ذخیره/بازیابی ترتیب | تست Livewire Save/Reload؛ Drag واقعی ماوس با Sortable در مرورگر محلی و تطابق ترتیب DOM و State |
| ۷. بارگذاری و انتخاب رسانهٔ جدید | تست UploadMedia واقعی، Action تازه‌سازی و ذخیرهٔ انتخاب جدید؛ آپلود داخلی مودال وجود ندارد و مسیر پنجرهٔ جدا استفاده می‌شود. |
| ۸. لغو مودال | مرورگر: تغییر Draft، انصراف، بازکردن مجدد و ثابت‌ماندن انتخاب اصلی |
| ۹. حفظ تصویر شاخص | تست Create و Edit؛ حفظ تصویر شاخص هنگام حذف تمام تصاویر گالری |
| ۱۰. صفحهٔ عمومی | درخواست HTTP آزمایشی `projects.show` و بررسی ترتیب خروجی Block گالری و Alt |

بررسی Chrome Headless از Blade فعلی، CSS موجود Filament، فونت بسته‌بندی‌شده، Alpine و Sortable خود پروژه استفاده کرد. اتصال `entangle` در این Fixture رابط با State محلی جایگزین شد؛ ارتباط واقعی Livewire/دیتابیس در تست‌های PHP بالا جداگانه بررسی شد. عرض‌های ۳۲۰، ۳۷۵، ۷۶۸ و ۱۲۸۰ بدون Horizontal Overflow بودند. علامت انتخاب، جست‌وجوی عنوان، تأیید/لغو، ترتیب Thumbnailها، حذف و تازه‌سازیِ بدون پاک‌شدن Draft کنترل شدند. تست رابط موقت و تصاویر آن در پوشهٔ Temp هستند و فایل اجرایی یا دادهٔ نمایشی به پروژه اضافه نشده است.

این شواهد شامل نشست کامل مرورگر روی فرم احرازهویت‌شدهٔ واقعی یا Production نیستند؛ آن محیط‌ها اجرا یا Deploy نشده‌اند.
