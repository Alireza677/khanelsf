# مدیریت وابستگی، جایگزینی انتخابی و حذف رسانه در King CMS

تاریخ بررسی: ۲۰۲۶-۱۱-۰۳. مبنای Git پیش از تغییر: `0113e7a`.

## نتیجه

اکشن حذف موجود کتابخانهٔ رسانه، محل‌های استفاده را در مودال بومی Filament نشان می‌دهد. کاربر می‌تواند با Picker تک‌انتخابی مشترک تصویر جایگزین را انتخاب کند، محل‌های مجاز را جداگانه علامت بزند، پیش‌نمایش و خلاصه را ببیند و عملیات را صریحاً تأیید کند. «فقط جایگزینی» تصویر قبلی را نگه می‌دارد؛ «جایگزینی و حذف تصویر قبلی» فقط با رفع همهٔ وابستگی‌ها اجرا می‌شود. ارجاع دستی، خارج از دسترسی یا نامعتبر همچنان مانع حذف است.

این تغییر روی Foundation موجود انجام شده است؛ جدول یا سیستم موازی Usage، Migration، Block جدید، تغییر Recipe یا Schema Versioning ایجاد نشده است. دادهٔ واقعی سایت تغییر نکرده و Deploy یا Commit انجام نشده است.

## Audit سورس موجود

| بخش | شواهد و قرارداد موجود |
| --- | --- |
| نقطهٔ ورود | `app/Filament/Resources/MediaResource.php` از DeleteAction و DeleteBulkAction عمومی استفاده می‌کرد؛ ارجاع باقی‌مانده می‌توانست به خطای SQL منتهی شود. نام اکشن `delete` حفظ شده تا Grid و Table موجود سازگار بمانند. |
| Media اصلی | `app/Models/Media.php` مدل تنظیم‌شدهٔ Spatie است. `app/Media/CanonicalMediaPathGenerator.php` هویت و مسیر canonical را تعیین می‌کند. |
| رابطهٔ مشترک | `app/Models/MediaUsage.php` و `app/Models/Concerns/HasSharedMediaUsages.php`: `media_id`، مالک polymorphic، `collection_name` و `order_column`. FK محافظ `media_id` حفظ شده است. رابطهٔ polymorphic به مالک FK ندارد؛ بنابراین Usage یتیم ممکن است وجود داشته باشد. |
| اتصال فرم‌ها | `app/Filament/Resources/Concerns/UsesMediaLibraryImages.php` و `HasFeaturedImage`؛ انتخاب از کتابخانه با Reference انجام می‌شود. گالری پروژه از Collection موجود `gallery` استفاده می‌کند. |
| Picker | `resources/views/filament/forms/components/media-library-picker.blade.php`؛ همان فیلد و همان کتابخانه استفاده شده‌اند، بدون آپلودکننده یا Picker موازی. |
| Blockها | تصاویر بعضی Blockهای Page و Template در JSON ذخیره می‌شوند و الزاماً ردیف `media_usages` ندارند؛ صرفاً شمردن جدول Usage کافی نبود. |
| Lifecycle فایل | Observer پیش‌فرض Spatie در رویداد `deleted` فایل را حذف می‌کند؛ این رویداد ممکن است پیش از Commit تراکنش بیرونی اجرا شود. |

## تشخیص محل‌های استفاده و قابلیت توسعه

`MediaConsumerRegistry` آداپتور مدل، Resource، فیلدهای قابل بررسی و مالک مجوز را ثبت می‌کند. `BlockMediaReferences` قراردادهای مشخص ارجاع در Block را می‌شناسد. هر دو متد `register()` دارند و از Service Provider قابل توسعه‌اند؛ Registry جدید، محل ثبت Referenceها نیست و ذخیره‌سازی موازی ندارد.

`MediaDependencyService` به ترتیب این منابع را بررسی می‌کند:

1. ردیف‌های `media_usages` و Consumer معتبر آن‌ها؛ نوع polymorphic فقط از مدل‌های ثبت‌شده resolve می‌شود.
2. Blockهای ذخیره‌شدهٔ Page و Template، با نگهداری مسیر دقیق Reference و شناسهٔ Block.
3. فیلدهای ثبت‌شدهٔ متن، SEO، تنظیمات، فرم و تصویر ویدئو برای تشخیص ارجاع ID یا URL؛ این موارد خودکار بازنویسی نمی‌شوند.
4. تصاویر اختصاصی قدیمی که هنوز fallback فعال همان محتوای مالک هستند؛ در مودال نیازمند اصلاح از فرم محتوا معرفی می‌شوند.

هر محل یک کلید snapshot سمت سرور دارد. کلاینت فقط کلیدهای انتخاب‌شده را می‌فرستد؛ نام مدل، مسیر JSON یا شناسهٔ مالک از کلاینت پذیرفته نمی‌شود. جابه‌جایی/تغییر ارجاع بعد از بازشدن مودال، عملیات قدیمی را نامعتبر می‌کند. تغییر عنوانِ غیرمرتبط به‌تنهایی کلید ارجاع را باطل نمی‌کند.

Usage فاقد Consumer معتبر حذف یا ترمیم حدسی نمی‌شود. وضعیت `unresolved` و شمارهٔ تشخیصی Usage در گزارش می‌آید و حذف Media تا بررسی مجاز مسدود می‌ماند.

## Consumerهای پشتیبانی‌شده

| Consumer / نوع استفاده | جایگزینی خودکار |
| --- | --- |
| Project، Page، Post، Product، Service، Video و Gallery با Usage معتبر `featured_image` یا `gallery` و قرارداد `mediaUsages()` | بله؛ فقط `media_id` همان Usage تغییر می‌کند. |
| Page / Template، بلوک `text_image` | `content.media_id` |
| Page / Template، بلوک `template_shop_complete` | `background_media_id` و `all_categories_media_id` |
| Page / Template، Hero با ID و URL سازگار | `content.media.source_id` همراه `content.media.url`؛ یا `poster_source_id` همراه `poster_url` |
| HTML، متن غنی، SEO، URLهای قدیمی، Footer، تنظیمات سایت، تنظیمات/Schema فرم، تصویر ویدئو و کلیدهای شناخته‌شدهٔ خارج از قراردادهای بالا | تشخیص و نمایش به‌صورت نیازمند جایگزینی دستی؛ بدون تغییر خودکار |
| تصویر اختصاصی قدیمی بدون Usage، Collection پشتیبانی‌نشده، Usage نامعتبر یا Consumer خارج از مجوز | جایگزینی از این مودال انجام نمی‌شود. |

مدل‌های ثبت‌شده برای بررسی دستی علاوه بر محتواهای بالا شامل Setting، Form، Category، ProjectCategory، ProductCategory، GalleryCategory و ProjectVideo هستند. مجوز و لینک ProjectVideo از پروژهٔ والد گرفته می‌شود.

ارجاع به Template یک محل مشترک است و تغییر آن در تمام محتواهای استفاده‌کننده از آن قالب دیده می‌شود. این دامنهٔ اثر هم در فهرست وابستگی و هم در خلاصهٔ تأیید توضیح داده شده است؛ برای هر محتوای استفاده‌کننده از قالب، Reference مستقل ساختگی ایجاد نمی‌شود.

## لینک‌ها و مجوزها

- برای Media، مدیر فعال بودن و `MediaResource::canAccess/canView/canDelete` بررسی می‌شود. حذف گروهی در UI به `canDeleteAny` نیز وابسته است و سرور مجوز هر Media را جداگانه بررسی می‌کند.
- نام، نوع و لینک هر Consumer تنها پس از `Resource::canAccess/canView` نمایش داده می‌شود. برای ویرایش، `canEdit` و وجود صفحهٔ ثبت‌شدهٔ `edit` نیز لازم است.
- لینک مدیریت با `Resource::getUrl('edit', ['record' => $owner])` ساخته می‌شود؛ URL پنل حدس زده نشده است.
- لینک عمومی فقط با وضعیت انتشار معتبر Resource/Domain، قرارداد `ResolvesNavigationUrl::resolveNavigationUrl()` یا `Resource::publicUrl()` موجود و بررسی `ModuleService::urlIsVisible()` نمایش داده می‌شود. Draftها لینک عمومی ندارند؛ برای Template نشانی عمومی مستقل ساخته نمی‌شود.
- موارد خارج از دسترسی فقط به‌صورت مانع حذف نمایش داده می‌شوند؛ نام و لینکشان افشا نمی‌شود و قابل انتخاب نیستند.
- فهرست Picker، پیش‌نمایش خلاصه و اجرای جایگزینی همگی مجوز مشاهدهٔ تصویر جدید را بررسی می‌کنند؛ دست‌کاری ID در Form State مجوز را دور نمی‌زند.
- مجوز تمام Consumerهای انتخاب‌شده پیش از اولین تغییر و دوباره پیش از پایان تراکنش بررسی می‌شود.

## قرارداد جایگزینی و حذف

1. تصویر قدیم و جدید با ترتیب ID قفل می‌شوند. تصویر جدید باید متفاوت، عضو کتابخانهٔ مشترک، از نوع تصویر و دارای فایل موجود باشد.
2. snapshot انتخاب‌ها با ارجاع‌های فعلی تطبیق داده می‌شود. مالک و Usage با locking read دوباره خوانده می‌شوند تا snapshot عادی MySQL در سطح Repeatable Read مبنا قرار نگیرد.
3. تمام انتخاب‌ها پیش از اولین نوشتن اعتبارسنجی و مجوزسنجی می‌شوند. برای چند ارجاع در یک Page/Template، همان نمونهٔ قفل‌شدهٔ مالک استفاده می‌شود تا ذخیرهٔ یکی، تغییر قبلی را از بین نبرد.
4. Usage فقط Media ID خود را عوض می‌کند؛ ترتیب، ردیف Usage و تصاویر دیگر حفظ می‌شوند. اگر تصویر جدید از قبل در همان Collection باشد، عملیات با پیام روشن رد می‌شود؛ Merge یا مرتب‌سازی حدسی نداریم.
5. Block فقط در مسیرهای ثبت‌شدهٔ ID و URL همراه تغییر می‌کند؛ JSON و HTML جست‌وجو/جایگزینی سراسری نمی‌شوند. فایل جدید کپی یا دوباره آپلود نمی‌شود.
6. Referenceهای تغییرکرده، ترتیب گالری و مجوزها دوباره بررسی می‌شوند. خطا، تغییر هم‌زمان، snapshot قدیمی یا رد مجوز باعث Rollback کل عملیات می‌شود.
7. در حالت «فقط جایگزینی»، Media قدیمی باقی می‌ماند. برای حذف یا عملیات ترکیبی، همهٔ Consumerهای ثبت‌شده با خواندن قفل‌شده دوباره بررسی می‌شوند؛ هر ارجاع باقی‌مانده مانع حذف است.
8. حذف از متد استاندارد مدل Spatie می‌گذرد. Guard مدل و FK باقی‌اند؛ اکشن گروهی ابتدا همهٔ رکوردها را بررسی می‌کند و سپس حذف می‌کند، بنابراین حذف ناقصِ گروه رخ نمی‌دهد.

در Page و Template، `GuardsBlockMediaReferences` ذخیرهٔ IDهای شناخته‌شده را با قفل Media هماهنگ می‌کند. فرم قدیمی نمی‌تواند پس از حذف تصویر، ID تازه و ناموجودی اضافه کند. ID خرابِ ازقبل‌ذخیره‌شده فقط به‌دلیل ذخیرهٔ فیلد دیگری حذف یا ترمیم نمی‌شود؛ سازگاری دادهٔ قدیمی حفظ است. این Guard تغییر در Template Runtime، Registry بلوک‌ها یا نسخهٔ Schema ایجاد نمی‌کند.

## فایل فیزیکی و Commit

Observer اختصاصی از Observer استاندارد Spatie ارث می‌برد و فقط زمان اجرای پاک‌سازی `deleted` را به `DB::afterCommit` منتقل می‌کند. سایر Hookهای استاندارد حفظ‌اند. در Rollback فایل دست‌نخورده می‌ماند. حذف از متادیتای پایدارِ خوانده‌شده زیر قفل استفاده می‌کند و snapshot مدل برای Callback کپی می‌شود؛ مدل قدیمی یا تغییر نمونهٔ PHP پس از حذف، مسیر پاک‌سازی را عوض نمی‌کند.

اگر پاک‌سازی Storage پس از Commit شکست بخورد، خطا ثبت و هشدار قابل مشاهده ارسال می‌شود؛ DB به وضعیت ارجاع نامعتبر برگردانده نمی‌شود. باقی‌ماندن فایل اصلی نیز بررسی می‌شود. Job، Outbox یا Retry پایدار جدید اضافه نشده است؛ شکست Storage یا توقف فرایند بین Commit و پاک‌سازی ممکن است فایل یتیم باقی بگذارد و رسیدگی عملیاتی لازم داشته باشد.

## تصمیم‌های UI با ui-ux-pro-max

فایل سراسری `C:/Users/Marco/.agents/skills/ui-ux-pro-max/SKILL.md` خوانده و راهنمای مرتبط با تأیید عملیات مخرب، Error Feedback، Success Feedback و Focus مودال بررسی شد. اثر مستقیم آن در پیاده‌سازی:

- مودال بومی Filament، Checkbox تأیید صریح، عملیات‌های دارای برچسب متمایز و خلاصهٔ اثر پیش از اجرا؛ انتخاب محل‌ها به‌صورت پیش‌فرض خالی است.
- نمایش وضعیت با متن، نه صرفاً رنگ؛ پیام روشن برای ارجاع دستی، خطا، موفقیت و شکست پاک‌سازی فایل.
- پیش‌نمایش قبل/بعد کنار هم، فهرست قابل اسکرول، شکستن عنوان‌های بلند، RTL و Grid متناسب با عرض؛ بدون CSS مخصوص یک سایت.
- استفاده از همان Picker مشترک، عنوان و دکمهٔ قابل تنظیم، Focus Trap و بازگشت Focus. Escape در Picker داخلی فقط همان Picker را می‌بندد و مودال تأیید والد باز می‌ماند.
- Grid تطبیقی تصاویر و رنگ‌های Theme خود Filament؛ فرمت Alpha متغیرهای رنگ در دو Picker با توکن‌های comma-separated Filament اصلاح شده است. انیمیشن جدید برای مودال داخلی اضافه نشده است.

## فایل‌های تغییرکرده

| فایل | مسئولیت |
| --- | --- |
| `app/Media/MediaConsumerRegistry.php` | مدل‌ها، مجوزها و Resolverهای لینک |
| `app/Media/BlockMediaReferences.php` | قراردادهای دقیق ID/URL و تشخیص دستی |
| `app/Media/MediaDependencyService.php` | Inventory، گزارش، جایگزینی و حذف اتمیک |
| `app/Media/MediaInUseException.php` | خطای دامنهٔ قابل کنترل |
| `app/Media/MediaObserver.php` | پاک‌سازی استاندارد پس از Commit و گزارش خطا |
| `app/Filament/Actions/ManageMediaDeletion.php` | اکشن موجود، Picker، انتخاب محل، خلاصه و تأیید |
| `app/Filament/Resources/MediaResource.php` | اتصال اکشن تکی و گروهی |
| `app/Models/Media.php` | Guard حذف و متادیتای صحیح پاک‌سازی |
| `app/Models/Concerns/GuardsBlockMediaReferences.php` | هماهنگی ذخیرهٔ IDهای Block و حذف Media |
| `app/Models/Page.php`، `app/Models/Template.php` | استفاده از Guard مشترک |
| `app/Providers/MediaAssetServiceProvider.php`، `bootstrap/providers.php` | ثبت آداپتورها و سرویس scoped |
| `config/media-library.php` | Observer استاندارد توسعه‌یافته |
| `resources/views/filament/media/dependencies.blade.php` | فهرست محل‌ها، لینک و وضعیت |
| `resources/views/filament/media/replacement-summary.blade.php` | قبل/بعد و اثر عملیات |
| `resources/views/filament/media/bulk-dependencies.blade.php` | گزارش حذف گروهی |
| `resources/views/filament/forms/components/media-library-picker.blade.php` | همان Picker با پشتیبانی live، مودال داخلی و چیدمان تطبیقی |
| `resources/views/filament/forms/components/media-library-multiple-picker.blade.php` | فقط اصلاح فرمت Alpha سه رنگ Theme؛ منطق گالری تغییر نکرد |
| `tests/Feature/MediaDependencyManagementTest.php` | سناریوهای وابستگی، جایگزینی، مجوز و UI واقعی Livewire |
| `tests/Feature/MediaDeletionCommitTest.php` | Commit/Rollback، Storage failure و متادیتای پاک‌سازی |
| `tests/Feature/SharedMediaAssetTest.php` | انتظار خطای دامنه به‌جای SQL؛ حفاظت FK جداگانه نیز تست شده |
| `tests/Feature/SiteFooterTemplateTest.php` | ساخت Fixture خراب قدیمی با DB مستقیم؛ نوشتن ID ناموجود جدید اکنون مجاز نیست |
| `docs/audits/media-dependencies-and-replacement.md` | این گزارش |

## اعتبارسنجی

### آزمون‌های مرتبط نهایی

**۴۳ تست و ۳۱۵ Assertion موفق** در مجموعه‌های زیر:

`MediaDependencyManagementTest`، `MediaDeletionCommitTest`، `SharedMediaAssetTest`، `MediaLibraryDetailsTest`، `ProjectMediaLibraryGalleryTest`، `ProductMediaLibraryGalleryTest`، `BlockMediaPickerTest` و `DynamicShopMediaPickerTest`.

پوشش شامل یک/چند Consumer، لینک واقعی، جایگزینی جزئی/کامل، عدم حذف با ارجاع باقی‌مانده، ترتیب و سایر تصاویر، تداخل تصویر تکراری، Rollback خطا، ارجاع دیررس، snapshot قدیمی، مجوز لغوشده، Usage نامعتبر، FK خام، تأیید Livewire، پیش‌نمایش غیرمجاز، حذف گروهی، Commit واقعی و خطای فایل است. اجرای تست‌ها با SQLite `:memory:` و Storage آزمایشی بود؛ هیچ Migration روی دیتابیس واقعی اجرا نشد. سقف حافظه فقط در فرمان CLI تست ۲۵۶ مگابایت بود.

### رگرسیون گسترده‌تر و مشکلات قبلی

پیش از دو تست تکمیلی نهایی، اجرای گسترده‌تر **۸۰ تست و ۷۴۸ Assertion** شامل TextImage، Footer Runtime و Hero Editor انجام شد: **۷۷ تست موفق، ۱ Error و ۲ Failure**. سه مورد باقی‌مانده روی سورس بدون تغییر `0113e7a` در checkout موقت نیز عیناً بازتولید شدند:

| آزمون قبلی | نتیجه |
| --- | --- |
| `SiteFooterTemplateTest::test_draft_preview_seed_and_scoped_responsive_contract_are_available` | انتظار Seed بدون شرط؛ Seeder فعلی به `king_footer_bootstrap=pending` نیاز دارد. |
| `HeroV2EditorTest::test_hero_one_appearance_transitions_show_only_the_active_mode_and_preserve_effect_state` | Assertion متن رابط با برچسب فعلی هماهنگ نیست. |
| `HeroV2EditorTest::test_hero_two_hides_independent_primary_destination_in_page_and_template_editors` | انتظار `Destination` انگلیسی در رابطی با برچسب فارسی. |

برای اثبات مبنا، namespaceهای App و Tests به checkout موقت مقدم شدند و مسیر واقعی کلاس‌های بارگذاری‌شده کنترل شد؛ تست مبنا از کلاس‌های تغییرکردهٔ Workspace استفاده نکرد. این سه آزمون نامرتبط در این تغییر اصلاح نشده‌اند. نتیجهٔ کل Suite سبز اعلام نمی‌شود.

### Build و رابط

- `npm run build` موفق؛ هشدار غیرمسدودکنندهٔ Chunk بزرگ `hero-dotted-surface` باقی است.
- Pint فایل‌های PHP این تغییر و `git diff --check` موفق.
- Chrome Headless محلی با HTML فرم واقعی اکشن، CSS/فونت Filament و Alpine موجود: عرض‌های ۳۲۰، ۳۷۵، ۷۶۸ و ۱۲۸۰ بدون Overflow افقی؛ بازشدن Picker، انتخاب، Escape فقط برای Picker داخلی و بازگشت Focus موفق، بدون خطای JavaScript.
- در Fixture مرورگر، Entangle با State محلی جایگزین شد؛ اتصال واقعی فرم/سرور جداگانه در Livewire بررسی شد. این شواهد، نشست کامل احرازهویت‌شده روی سایت یا Production نیستند.

## محدودیت‌های باقی‌مانده

- Inventory فقط مدل‌ها، فیلدها، URLهای canonical/مسیر متناظر و قراردادهای ثبت‌شده را پوشش می‌دهد؛ فایل‌های دلخواه، سامانه‌های خارجی، نسخه‌های تاریخی و فیلدهای سفارشی ناشناخته اسکن عمومی نمی‌شوند. Consumer جدید باید در Registry و در صورت نیاز در قرارداد Block ثبت شود.
- HTML/URL-only و سایر موارد دستی خودکار بازنویسی نمی‌شوند؛ پس از اصلاح در فرم محتوا باید مودال دوباره باز شود تا گزارش تازه گرفته شود.
- بررسی نهایی حذف، ردیف‌های Consumerهای ثبت‌شده را برای خواندن جاری قفل می‌کند. این راه‌حل اسکن خطی دارد و روی دیتاست بزرگ می‌تواند هزینه و انتظار قفل ایجاد کند؛ Benchmark سایت بزرگ انجام نشده است.
- FK و Guard نوشتن IDهای پشتیبانی‌شده فعال‌اند. SQL مستقیم، حذف Query Builder بدون رویداد مدل، یا نوشتن دیررس URL آزاد/فیلد ناشناخته خارج از این قراردادها است. محافظت جامع همهٔ شکل‌های محتوای آزاد ادعا نمی‌شود.
- تست هم‌زمانی با تزریق تغییر در حین تراکنش و snapshot قدیمی در SQLite انجام شد؛ رقابت واقعی دو اتصال/فرایند MySQL بررسی نشده است. Locking readهای نهایی برای رفتار Repeatable Read در سورس لحاظ شده‌اند.
- پاک‌سازی فایل پس از Commit پایدارِ صف‌شده نیست؛ خطا ثبت و هشدار داده می‌شود، اما Retry خودکار یا تضمین ادامه پس از Crash وجود ندارد.
