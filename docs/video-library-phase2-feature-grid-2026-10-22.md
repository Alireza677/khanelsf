# کتابخانه ویدئویی King — اتصال به Feature Grid، فاز دوم

تاریخ: ۲۰۲۶-۱۰-۲۲

## نتیجه

کتابخانه ویدئویی فاز اول اکنون از طریق همان بلوک `feature_grid` به‌صورت انتخابی یا داینامیک قابل استفاده است. بلوک Video Grid، مدل، جدول، migration یا سیستم کارت دیگری ساخته نشد. مرحله A ابتدا با تست‌های کوتاه کامل شد و سپس مرحله B روی همان قرارداد کارت اجرا شد.

## معماری بررسی‌شده

- `FeatureGridBlock` منبع schema مشترک است؛ `PageResource` آن را با context برابر `page` و `TemplateResource` از طریق Registry با context برابر `template` دریافت می‌کنند.
- `BlockEditorHydrator` و `BlockEditorSaveManager` از normalizer ثبت‌شده Feature Grid استفاده می‌کنند. تغییر داده‌ها additive است؛ `block_id` و `schema_version = 1` حفظ شدند و بلوک‌های قدیمی نیازی به ذخیره مجدد ندارند.
- `BlockServiceProvider` پیش از Blade، `FeatureGridRuntime` را اجرا می‌کند. در همان composer، پرچم Preview موجود در `context.isPreview` نیز شناسایی می‌شود.
- `partials.page-blocks` و `templates.render` مسیر مشترک نمایش در صفحه اصلی، برگه، قالب خدمت و قالب پروژه هستند. هیچ وابستگی به شناسه صفحه یا قالب خاص اضافه نشد.
- `Video`، `VideoCategory`، `VideoLibrarySettings`، `AparatVideoProvider`، قرارداد رسانه مشترک و انتشار فاز اول دوباره استفاده شدند.
- اطلاعات پخش در Runtime resolve می‌شود؛ Blade کارت و آرشیو query دیتابیس یا درخواست metadata خارجی ندارند.

## مرحله A — کارت انتخابی و پخش

- هر کارت نوع رسانه `image` یا `video` دارد؛ پیش‌فرض کارت قدیمی و جدید تصویر است. کنترل‌های فعلی تصویر، چیدمان، پس‌زمینه، فاصله‌ها، اندازه و variantهای Default/Icon List حفظ شده‌اند.
- در حالت ویدئو، انتخابگر قابل جستجو عنوان و تصویر بندانگشتی را نشان می‌دهد. انتخاب فقط از کتابخانه فعال و رکوردهای منتشرشده با زمان انتشار رسیده مجاز است؛ کنترل سمت سرور نیز اعمال می‌شود. labelهای HTML انتخابگر escape می‌شوند.
- متن کارت می‌تواند داینامیک از `Video.title`/`Video.excerpt` یا سفارشی باشد. در حالت سفارشی، تغییر عنوان و خلاصه کتابخانه متن کارت را بازنویسی نمی‌کند؛ تصویر، مدت و منبع پخش همچنان از Video می‌آیند.
- شناسه‌های ویدئو در هر شبکه یک‌جا خوانده می‌شوند و `categories`، `media` و `mediaUsages.media` eager-load هستند. برای هر کارت query جداگانهٔ عمومی ایجاد نمی‌شود.
- ویدئوی حذف‌شده، پیش‌نویس، انتشار آینده یا دارای provider/شناسه نامعتبر به کارت پخش عمومی تبدیل نمی‌شود. با غیرفعال شدن کتابخانه، کارت‌های ویدئویی عمومی نیز حذف می‌شوند. Preview به‌جای اطلاعات ویدئوی پنهان پیام «ویدئو در دسترس نیست» نشان می‌دهد؛ انتخابگر هم مرجع نامعتبر را مشخص می‌کند.
- دکمه Play یک button مستقل است و Action معمولی کارت همچنان از `RuntimeActionResolver` و `ActionPresentation` فعلی عبور می‌کند. یک بلوک می‌تواند هم‌زمان کارت تصویری و ویدئویی داشته باشد.

## قرارداد داده کارت

ساختار اصلی بلوک همچنان `block_id`, `schema_version`, `template`, `content`, `settings` است. کلیدهای زیر به هر عضو `content.items` اضافه شده‌اند:

| فیلد | مقادیر / پیش‌فرض |
| --- | --- |
| `media_type` | `image` یا `video`؛ پیش‌فرض `image` |
| `video_id` | شناسه مثبت Video یا `null` |
| `video_text_mode` | `dynamic` یا `custom`؛ پیش‌فرض `dynamic` |
| `title`, `description` | همان فیلدهای قبلی؛ برای متن سفارشی استفاده می‌شوند |
| `button_label`, `action` | همان قرارداد Action قبلی؛ مستقل از Play |

مثال یک کارت با متن سفارشی:

```json
{
  "media_type": "video",
  "video_id": 42,
  "video_text_mode": "custom",
  "title": "آموزش راه‌اندازی",
  "description": "توضیحات مخصوص این صفحه",
  "button_label": "خدمت مرتبط",
  "action": {
    "schema_version": 1,
    "type": "service",
    "reference_id": 7,
    "open_in_new_tab": false
  }
}
```

برای متن داینامیک، مقدار `video_text_mode` را `dynamic` انتخاب کنید؛ لازم نیست عنوان و خلاصه Video را در کارت کپی کنید. URL iframe، عنوان دریافت‌شده، thumbnail و مدت در JSON بلوک snapshot نمی‌شوند. normalizer فیلدهای تزریقی مانند `embed_url` و دادهٔ `video` را حذف می‌کند. دادهٔ `video` شامل embed/title/duration فقط در خروجی موقت Runtime وجود دارد.

## Modal و Click-to-Play

- در HTML اولیه کارت فقط thumbnail، مدت و کنترل Play وجود دارد؛ هیچ iframe یا درخواست metadata آپارات ساخته نمی‌شود.
- ماژول `feature-grid-videos.js` یک listener مشترک روی document دارد و به کارت‌هایی که بعداً به DOM اضافه شوند هم پاسخ می‌دهد.
- نخستین Play یک `dialog` مشترک برای کل صفحه می‌سازد؛ iframe فقط پس از کلیک و داخل همان dialog ایجاد می‌شود. کلیک‌های بعدی همان dialog را دوباره استفاده می‌کنند.
- URL سمت سرور با `Video::embedUrl()` و Provider موجود ساخته می‌شود. JavaScript نیز پیش از تخصیص `src`، scheme، origin، مسیر و نبود query/fragment/userinfo را بررسی می‌کند؛ HTML خام وارد پلیر نمی‌شود.
- پخش نسبت ۱۶:۹ دارد؛ عرض dialog با موبایل سازگار و ارتفاع آن محدود به viewport است. عنوان modal با `textContent` نوشته می‌شود.
- native `showModal()` پس‌زمینه را inert و فوکوس را در modal نگه می‌دارد. دکمه بستن فوراً فوکوس می‌گیرد، ESC و کلیک روی backdrop پشتیبانی می‌شوند و اسکرول ریشه صفحه هنگام باز بودن قفل می‌شود.
- با بستن، iframe از DOM حذف می‌شود، قفل اسکرول برداشته می‌شود و فوکوس به Play برمی‌گردد. خروج از صفحه نیز پلیر را پاک می‌کند.

## مرحله B — منبع داینامیک

منبع `videos` به گزینه‌های قبلی `posts` و `projects` اضافه شد. منطق و Actionهای منابع قبلی حفظ شدند.

| کلید | کاربرد |
| --- | --- |
| `content.items_mode` | `dynamic` |
| `content.dynamic_source` | `videos` |
| `content.video_category_id` | `null` برای همه یا شناسه یک دسته ویدئو |
| `content.video_sort` | `newest`، `oldest` یا `manual`؛ پیش‌فرض `newest` |
| `settings.video_limit` | ۱ تا ۷۲؛ پیش‌فرض ۶ |

- جدیدترین/قدیمی‌ترین با زمان انتشار و در نبود آن زمان ایجاد مرتب می‌شوند؛ شناسه ترتیب را در زمان‌های مساوی پایدار می‌کند.
- ترتیب دستی همان ترتیب کتابخانه است: `sort_order` صعودی، سپس `published_at` و شناسه نزولی.
- `VideoQueryService::featureGridQuery()` فیلتر دسته را بر query عمومی فاز اول اعمال می‌کند. وضعیت انتشار و toggle در سرور اجباری‌اند و گزینه‌ای برای عمومی کردن پیش‌نویس‌ها وجود ندارد.
- تعداد ویدئو مستقل از `dynamic_rows` است؛ ستون‌ها، عرض شبکه و حداقل عرض کارت از تنظیمات فعلی Feature Grid می‌آیند. تنظیم تعداد ردیف و دکمه‌های مخصوص Post/Project برای منبع ویدئو نمایش داده نمی‌شوند.
- کارت‌های داینامیک ویدئو Play دارند؛ Action اختصاصی اختیاری در کارت‌های انتخابی Static قابل تنظیم است. منبع داینامیک نیازی به تعریف تک‌تک کارت‌ها ندارد و تغییر کتابخانه در بارگذاری بعدی منعکس می‌شود.

## استفاده در Page Builder و Template Editor

۱. کتابخانه ویدئویی را در تنظیمات سایت فعال و ویدئو را با وضعیت منتشرشده و زمان رسیده ثبت کنید.

۲. در ویرایش برگه یا قالب، بلوک موجود «شبکه ویژگی‌ها / Feature Grid» را اضافه یا ویرایش کنید.

۳. برای انتخاب دستی، نوع آیتم‌ها را Static نگه دارید، یک کارت باز کنید و نوع رسانه را ویدئو بگذارید. از انتخابگر جستجو استفاده و سپس حالت متن داینامیک یا سفارشی را تعیین کنید. در صورت نیاز Action و متن دکمهٔ معمولی را جدا تنظیم کنید.

۴. برای فهرست خودکار، نوع آیتم‌ها را Dynamic و منبع را «کتابخانه ویدئویی / Video library» بگذارید. دسته و ترتیب را در محتوای بلوک و تعداد ویدئو و ابعاد شبکه را در بخش طراحی تنظیم کنید.

۵. ذخیره و Preview کنید. برای تغییر بین تصویر و ویدئو نیازی به تغییر نوع بلوک نیست. در مرجع نامعتبر یا منبع خالی، Preview پیام مناسب نشان می‌دهد.

همین مراحل برای قالب‌های صفحه، خدمت و پروژه قابل استفاده است؛ نه بلوک جدیدی لازم است و نه Template خاصی.

## آرشیو `/videos` و Shared Collection

- `VideoController` همان query عمومی و pagination قبلی را اجرا و paginator را به `VideoCollectionAdapter` می‌دهد. toggle، مسیر و نام route فاز اول حفظ شده‌اند.
- adapter یک `VideoArchivePresentation` دامنه‌ای می‌سازد: `CollectionPresentation` معمولی برای header، حالت خالی و pagination، و خروجی Runtime کارت در یک بخش جداگانه.
- DTO عمومی Collection هیچ فیلد ویدئو، embed یا player نگرفت. تصویر، عنوان، excerpt و مدت به‌صورت meta معمولی در فیلدهای موجود آن نگاشت می‌شوند؛ اطلاعات پخش در presentation دامنه‌ای باقی می‌ماند.
- کارت آرشیو دقیقاً از `partials.blocks.feature-grid.cards` استفاده می‌کند؛ همان partial و همان resolver برای کارت‌های انتخابی و داینامیک Feature Grid استفاده می‌شوند.
- header، empty و pagination از partialهای Shared Collection موجودند. افزودن ویدئوی منتشرشده جدید در کتابخانه، بدون ویرایش دستی آرشیو در فهرست منعکس می‌شود.

## فایل‌های این فاز

فایل‌های موجود ویرایش‌شده، شامل فایل‌های فاز اول که هنوز در working tree ثبت‌نشده بودند:

- `app/CMS/Blocks/FeatureGrid/FeatureGridBlock.php`
- `app/CMS/Blocks/FeatureGrid/FeatureGridDataNormalizer.php`
- `app/CMS/Blocks/FeatureGrid/FeatureGridRuntime.php`
- `app/Providers/BlockServiceProvider.php`
- `app/Services/VideoQueryService.php`
- `app/Http/Controllers/VideoController.php`
- `resources/views/partials/blocks/feature_grid.blade.php`
- `resources/views/videos/index.blade.php`
- `resources/js/app.js` — فقط import و فراخوانی initializer جدید
- `resources/css/app.css` — فقط import فایل CSS جدید

فایل‌های جدید:

- `app/CMS/Blocks/FeatureGrid/FeatureGridVideoResolver.php`
- `app/CMS/Blocks/FeatureGrid/FeatureGridVideoPicker.php`
- `app/CMS/Collections/Video/VideoCollectionAdapter.php`
- `app/CMS/Collections/Video/VideoArchivePresentation.php`
- `resources/views/partials/blocks/feature-grid/cards.blade.php`
- `resources/views/videos/partials/archive.blade.php`
- `resources/js/components/feature-grid-videos.js`
- `resources/css/components/feature-grid-videos.css`
- `tests/Feature/FeatureGridVideoTest.php`
- `tests/js/feature-grid-videos.test.mjs`
- `docs/video-library-phase2-feature-grid-2026-10-22.md`

هیچ migration جدیدی در این فاز ایجاد یا روی دیتابیس کاری اجرا نشد. مدل‌ها، Gallery، ProjectVideo، Navigation، Recipe، Action Foundation و DTOهای عمومی Collection تغییر نکردند. تغییرات قبلی `PageResource`، `TemplateResource` و FAQ دست‌نخورده‌اند؛ تغییرات قبلی CSS و JavaScript حفظ و فقط اتصال فایل جدید اضافه شد.

## بررسی‌های سبک

```text
php vendor/bin/phpunit tests/Feature/FeatureGridVideoTest.php tests/Unit/FeatureGridDataNormalizerTest.php tests/Feature/VideoLibraryTest.php
node --test tests/js/feature-grid-videos.test.mjs
npm run build
```

- ۱۹ تست PHP هدفمند موفق؛ پوشش قرارداد افزایشی و idempotency، رفت‌وبرگشت ذخیره ویرایشگر و هویت بلوک، جلوگیری از snapshot، متن سفارشی، batching، مجوز انتشار، فیلتر/ترتیب/تعداد، سازگاری Post/Project، Query-free بودن partialها، نبود iframe اولیه، انتخابگر و escape عنوان، آرشیو و pagination، و تست‌های فاز اول.
- دو تست کوتاه Node موفق؛ allowlist URL پلیر، نبود ساخت iframe پیش از Play، یک dialog مشترک، پاک‌شدن پلیر و قفل اسکرول و بازگشت فوکوس در close/ESC. تست lifecycle از DOM کوچک شبیه‌سازی‌شده استفاده می‌کند و جایگزین تست مرورگر نیست.
- build فرانت‌اند موفق شد. اخطار اندازه chunk مربوط به `hero-dotted-surface` بزرگ‌تر از ۵۰۰ kB بود؛ این فایل از کارهای قبلی است و در این فاز تغییر نکرد.
- syntax PHP، `node --check` و `git diff --check` بررسی شدند. Pint فقط روی فایل‌های PHP مرتبط این فاز اجرا شد.
- تست‌ها از SQLite در حافظه و چند migration موجودِ کوچک استفاده کردند؛ HTTP metadata mock بود. Full Regression، مرورگر، بررسی بصری و ارتباط زنده پلیر آپارات اجرا نشدند.

## محدودیت‌ها و فاز بعد

- بررسی نهایی ظاهر، کار با کیبورد و screen reader، RTL، موبایل، fullscreen و رفتار واقعی پلیر با کاربر است. پخش modal به مرورگر دارای native `dialog.showModal()` و فایل‌های buildشده نیاز دارد.
- امکان پخش واقعی در آپارات تابع وضعیت خود ویدئو و محدودیت‌های provider است. حذف یا خصوصی شدن ویدئو در خود آپارات با درخواست زنده هنگام render بررسی نمی‌شود؛ دسترسی عمومی داخلی King، وضعیت انتشار، toggle و اعتبار provider/شناسه سمت سرور بررسی می‌شوند.
- metadata و thumbnail همان محدودیت best-effort فاز اول را دارند؛ در زمان render دریافت یا refresh نمی‌شوند. اولویت تصویر مشترک جایگزین، cached provider و placeholder حفظ شده است.
- طراحی نهایی آرشیو، جستجوی زنده، فیلتر تعاملی، VideoObject/SEO، analytics، روابط اختصاصی Video با Project/Product/Service و مدیریت providerهای دیگر در این فاز پیاده‌سازی نشده‌اند.
