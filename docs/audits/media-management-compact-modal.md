# بازطراحی فشردهٔ مودال مدیریت رسانه

تاریخ: ۲۰۲۶-۱۱-۰۴ — مبنای بررسی `6aa7cd1`.

## نتیجهٔ ساختاری

مودال موجود مطابق جهت بصری موکاپ پیوست بازچینی شد. هدر و Thumbnail کوچک، نام رسانه و Badge تعداد استفاده‌ها در بالا قرار دارند. فهرست استفاده‌ها ردیفی است و عنوان محتوا، نوع استفاده و لینک‌های مجاز را کنار هم نشان می‌دهد. بخش جایگزینی شامل Checkboxهای مستقل و همان Picker مشترک با Thumbnail کوچک و نام انتخاب‌شده است. Radioهای عملیات و فوتر در پایین قرار دارند.

بخش `change_summary`، مقایسهٔ Before/After، عنوان «خلاصه پیش از اجرا»، پیام‌های تکراری و Checkbox طولانی `confirmed` کاملاً از فرم حذف شدند. فایل Blade مقایسه نیز حذف شد؛ بخش پنهان یا جمع‌شده‌ای از Preview باقی نمانده است.

## Audit سورس پیش از اصلاح

| بخش موجود | مسیر / قرارداد |
| --- | --- |
| Action تکی و گروهی | `app/Filament/Actions/ManageMediaDeletion.php`؛ اتصال از `MediaResource` با نام موجود `delete` |
| Blade گزارش | `resources/views/filament/media/dependencies.blade.php`؛ گزارش مجاز `modalReport()` |
| Blade مقایسهٔ قبلی | `resources/views/filament/media/replacement-summary.blade.php`؛ در این اصلاح حذف شد |
| Picker تک‌انتخابی | `resources/views/filament/forms/components/media-library-picker.blade.php`؛ Entangle فیلد `replacement_media_id` و مودال مشترک موجود |
| Livewire | صفحهٔ موجود `MediaResource/Pages/ListMedia.php` و چرخهٔ اکشن/فرم Filament؛ کامپوننت Livewire جدید ساخته نشد |
| Theme و CSS پنل | `resources/views/filament/theme.blade.php` در Hook موجود `HEAD_END` از `AdminPanelProvider`؛ CSS جدید فقط برای عناصر نام‌گذاری‌شدهٔ این مودال است |
| تشخیص و اجرا | `MediaDependencyService`، `MediaConsumerRegistry`، `BlockMediaReferences` و `media_usages` |
| انتخاب محل | کلیدهای snapshot سمت سرور در `dependencies`؛ ID و مسیر JSON از UI ساخته نمی‌شوند |
| Lifecycle حذف | Guard مدل Media، FK، Transaction و `MediaObserver` موجود با پاک‌سازی پس از Commit |
| تست‌ها | `MediaDependencyManagementTest`، `MediaDeletionCommitTest` و تست‌های Shared Asset / Picker / Project Gallery |

منبع ارتفاع اضافه، قرارگیری عمودی Preview بزرگ Picker، انتخاب‌های یک‌ستونی، مقایسهٔ قبل/بعد و تأیید طولانی پس از آن‌ها بود. Backend تشخیص/جایگزینی برای تغییر ظاهر بازنویسی نشده است.

## Desktop و Mobile

- عرض حداکثر مودال `56rem`، معادل حدود ۸۹۶ پیکسل با اندازهٔ پایهٔ فعلی؛ در عرض کم به فضای موجود محدود می‌شود.
- Padding اصلی ۱۶ تا ۲۰ پیکسل و فاصلهٔ بخش‌ها ۱۲ تا ۱۶ پیکسل است. ارتفاع ثابت بزرگ وجود ندارد؛ فقط سقف متناسب با Viewport اعمال می‌شود.
- Thumbnail اصلی ۶۴ پیکسل و در موبایل ۵۶ پیکسل است. Thumbnail انتخاب جدید ۴۰ پیکسل است و در دسکتاپ کنار دکمه نمایش داده می‌شود؛ در عرض کم می‌تواند به ردیف بعد برود.
- ردیف‌های استفاده در دسکتاپ سه ناحیهٔ عنوان، نوع و لینک دارند. در موبایل عنوان بالاتر و لینک‌ها در ردیف بعد قرار می‌گیرند؛ متن بلند می‌شکند و کل ردیف `nowrap` نشده است.
- فهرست استفاده‌ها و انتخاب‌ها سقف ارتفاع و Scroll مستقل دارند. بدنهٔ مودال نیز در Viewport کوتاه اسکرول می‌شود؛ فوتر بومی Filament در دسترس می‌ماند و محتوا را نمی‌پوشاند.
- عنوان یک محتوای مشترک در Checkboxهای همان محتوا دوباره تکرار نمی‌شود؛ برای چند محتوای متفاوت، عنوان محتوا همراه نوع استفاده می‌آید. هر کلید همچنان مستقل انتخاب می‌شود.
- موارد دستی، محدودیت مجوز، Usage نامعتبر و اثر تغییر Template مشترک، توضیح کوتاه خود را در همان ردیف حفظ کرده‌اند. لینک عمومی یا مدیریت جدیدی ساخته نشده است.
- رنگ‌ها از توکن‌های `primary` و `gray` خود Filament هستند؛ هر دو حالت روشن و تیره بررسی شدند. CSS مختص سایت خانه LSF نوشته نشده است.

## تأیید، اعتبارسنجی و خطا

«فقط جایگزینی» با دکمهٔ «اجرای تغییرات» اجرا می‌شود. برای «جایگزینی و حذف» و حذف رسانهٔ بدون وابستگی، ابتدا دیالوگ کوتاه استاندارد Filament با دکمهٔ «تأیید و اجرا» باز می‌شود. تا تأیید، تغییری در داده اتفاق نمی‌افتد. «بازگشت» انتخاب تصویر، محل‌ها و عملیات فرم والد را حفظ می‌کند.

دکمهٔ اجرا در نبود تصویر معتبر، نبود محل انتخاب‌شده، انتخاب غیرمجاز، یا انتخاب ناقص برای حذف غیرفعال است. اعتبارسنجی فرم و کنترل سمت Action نیز باقی است؛ غیرفعال‌بودن ظاهری تنها محافظ نیست. پس از تأیید، همان `MediaDependencyService::apply()` با همان قرارداد اجرا می‌شود و وابستگی‌ها/مجوزها دوباره بررسی می‌شوند.

Loading، غیرفعال‌سازی Submit هنگام درخواست و مدیریت فوکوس، از رفتار بومی Filament استفاده می‌کنند؛ Submit سفارشی موازی یا درخواست جدید اضافه نشده است. Escape داخل Picker فقط Picker را می‌بندد و Focus به دکمهٔ انتخاب تصویر برمی‌گردد. بستن با Backdrop برای فرم مدیریت و تأیید حذف غیرفعال است تا انتخاب‌ها ناخواسته از دست نروند؛ انصراف و Escape بومی حفظ‌اند.

تست خطا نشان داد خطاهای سطح Action/Service با کلید عمومی `operation` به State Path فرم تعلق نمی‌گرفتند. نگاشت نمایش خطا در مرز همین Action به `mountedTableActionsData.<index>.operation` اصلاح شد تا پیام زیر Radioهای عملیات دیده شود. نوع تصمیم Backend، Rollback و متن خطاها تغییر دامنه‌ای نکرده‌اند؛ پس از خطا، انتخاب‌های فرم حفظ می‌شوند.

## استفادهٔ واقعی از ui-ux-pro-max

فایل `C:/Users/Marco/.agents/skills/ui-ux-pro-max/SKILL.md` خوانده شد. جست‌وجوی اولیهٔ Compact Dialog نتیجهٔ اختصاصی مناسبی نداشت؛ جست‌وجوی محدودترِ Spacing و Text Reflow، همراه Modal Focus و Confirmation Dialogs و راهنمای Grid/Spacing برای HTML/Tailwind بررسی شد. توصیه‌های نسخهٔ دیگر Tailwind فقط در حد اصول چیدمان استفاده شدند؛ API و کلاس‌ها با Filament/Tailwind 3 موجود پروژه تطبیق داده شدند.

اثر تصمیم‌ها در UI: حذف تکرار به‌جای کوچک‌کردن افراطی متن، گروه‌بندی افقی، مقیاس فاصلهٔ منظم، ارتفاع مبتنی بر محتوا، نگهداری متن در عرض کم، فاصلهٔ کنترل‌های قابل لمس، نشانه‌های متنی وضعیت، تأیید کوتاه عملیات مخرب و حفظ Focus مودال داخلی. فونت یا سیستم طراحی تازه جایگزین Theme موجود نشده است. انیمیشن جدیدی اضافه نشده است.

## فایل‌های تغییرکرده

| فایل | تغییر |
| --- | --- |
| `app/Filament/Actions/ManageMediaDeletion.php` | ساختار فشردهٔ فرم، دیالوگ تأیید بومی، وضعیت Submit و اتصال صحیح پیام خطا به فرم |
| `resources/views/filament/media/dependencies.blade.php` | خلاصهٔ رسانه و ردیف‌های فشردهٔ استفاده |
| `resources/views/filament/media/replacement-heading.blade.php` | عنوان و توضیح کوتاه جایگزینی |
| `resources/views/filament/media/management-styles.blade.php` | استایل محدودشده، Grid، Scroll و Mobile |
| `resources/views/filament/forms/components/media-library-picker.blade.php` | حالت نمایشی اختیاری `compact`؛ ظاهر پیش‌فرض سایر فرم‌ها حفظ شده است |
| `resources/views/filament/media/replacement-summary.blade.php` | حذف کامل فایل و استفادهٔ آن |
| `resources/views/filament/theme.blade.php` | بارگذاری استایل محدودشده از Hook موجود پنل |
| `tests/Feature/MediaDependencyManagementTest.php` | سناریوهای جدید فرم فشرده، تأیید/لغو، حالت نامعتبر، مجوز و خطای قابل نمایش |
| `docs/audits/media-management-compact-modal.md` | این گزارش |

## اعتبارسنجی

### مرورگر و مقایسهٔ ارتفاع

Chrome Headless از HTML واقعی پنجرهٔ بومی Filament، Bladeهای پروژه، CSS و فونت بسته‌بندی‌شده و Alpine/Focus موجود استفاده کرد. نسخهٔ پیشین Action و سه View مرتبط مستقیماً از `HEAD` به پوشهٔ موقت خوانده شدند؛ دادهٔ یکسان با دو Usage پروژه در هر دو نسخه ساخته شد. کلاس‌های مدل و سرویس‌ها مشترک و بدون تغییر بودند.

| عرض Viewport | ارتفاع پیشین پنجره | ارتفاع جدید پنجره | نتیجه |
| --- | --- | --- | --- |
| ۱۲۸۰ | ۱۴۳۶px | ۶۷۹px | حدود ۵۳٪ کاهش؛ بدون اسکرول بدنه برای دو Usage |
| ۷۶۸ | ۱۴۳۶px | ۷۲۷px | حدود ۴۹٪ کاهش |
| ۳۷۵ | ۱۶۴۲px | حداکثر ۸۶۸px در Viewport با ارتفاع ۹۰۰ | اسکرول بدنه، فوتر قابل دسترس |
| ۳۲۰ | ۱۷۴۴px | حداکثر ۸۶۸px در Viewport با ارتفاع ۹۰۰ | بدون Overflow افقی |

بخش مقایسه در سورس و فرم حذف شده است. بازشدن Picker، انتخاب، Escape بدون بستن والد، بازگشت دقیق Focus به دکمهٔ Picker و نبود خطای JavaScript بررسی شدند. حالت چندین محتوا با عنوان‌های بلند و یک ارجاع دستی نیز برای Scroll و Overflow بررسی شد. تصاویر روشن/تیره و نتایج خام در پوشهٔ موقت `king-media-compact-ui` قرار دارند.

در Fixture مرورگر، اتصال Entangle با State محلی جایگزین و Bindingهای غیرمرتبط Filament برای نمایش مستقل غیرفعال شدند. ارتباط واقعی فرم/سرور و دیالوگ تأیید در Livewire جداگانه تست شد. بنابراین این بررسی، تست سرتاسریِ نشست احرازهویت‌شده یا آزمون شبکهٔ کند در Production نیست؛ Loading از پیاده‌سازی استاندارد Filament استفاده می‌کند.

### آزمون‌های خودکار و Build

**۴۶ تست و ۳۶۵ Assertion موفق** در مجموعه‌های `MediaDependencyManagementTest`، `MediaDeletionCommitTest`، `SharedMediaAssetTest`، `MediaLibraryDetailsTest`، `ProjectMediaLibraryGalleryTest`، `ProductMediaLibraryGalleryTest`، `BlockMediaPickerTest` و `DynamicShopMediaPickerTest`.

سناریوهای فرم فشرده: نبود Preview و Checkbox قبلی، نبود تصویر/محل انتخاب‌شده، فعال/غیرفعال‌بودن Submit، جایگزینی مستقل، تأیید دو مرحله‌ای حذف، لغو و حفظ انتخاب‌ها، عدم حذف با انتخاب ناقص یا ارجاع دستی، تصویر خارج از مجوز و خطای عملیات همراه Rollback و حفظ فرم. آزمون‌های Foundation نیز ترتیب گالری، ارجاع هم‌زمان، FK و پاک‌سازی پس از Commit را پوشش دادند.

در اجرای میانی، دو Assertion خطا محل نمایش نادرست پیام در Form State را آشکار کردند که در مرز Action اصلاح شد. یک Assertion دیگر به بازاستفادهٔ سرویس scoped در Container مشترک تست مربوط بود؛ تست بازکردن دوبارهٔ مودال با نمونهٔ scoped تازه مدل شد. اجرای نهایی هیچ Failure یا Error نداشت. کل Suite نامرتبط پروژه دوباره اجرا نشده است؛ نتیجهٔ این گزارش به مجموعه‌های نام‌برده محدود است.

`npm run build` موفق بود؛ هشدار قبلی Chunk بزرگ `hero-dotted-surface` باقی است. Pint فایل‌های PHP تغییرکرده و `git diff --check` موفق بودند. تست‌ها و Fixture مرورگر فقط از SQLite در حافظه و Storage آزمایشی استفاده کردند؛ دیتابیس و فایل‌های واقعی سایت تغییر نکردند.

## مرز تغییر

`MediaDependencyService`، Registryها، مدل‌ها، FK، Transaction، روش جایگزینی Reference، ترتیب گالری و Observer پاک‌سازی پس از Commit تغییر نکرده‌اند. تنها Schema نمایشی و اتصال تأیید/نمایش خطای Action تغییر کرده است. Migration، Route، Block، Job یا Livewire Component جدید، Commit و Deploy انجام نشده است. محدودیت‌های تشخیص ارجاع و پاک‌سازی فایلِ Foundation همچنان همان موارد گزارش قبلی هستند.
