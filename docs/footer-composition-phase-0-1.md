# Footer Composition — فازهای ۰ و ۱

به‌روزرسانی بعدی: [مدیریت فاز ۳](footer-composition-phase-3.md) جای Hiddenهای موقت Composition/settings را با فرم واقعی در همان بلوک گرفته است؛ قرارداد داده این سند تغییر نکرده است. محدودیت اجباری سه منوی فرم Legacy نیز در فاز مدیریت برداشته شد.

تاریخ: 2026-11-03. مبنا: Product Requirement قطعی بخش A.1 در [گزارش معماری](KING-FOOTER-SYSTEM-SOURCE-AUDIT-AND-IMPLEMENTATION-PLAN.md).

به‌روزرسانی بعدی: Runtime و Rendering در [فاز ۲](footer-composition-phase-2.md) پیاده‌سازی شد. توصیف‌های «هنوز Renderer اضافه نشده» در این سند، وضعیت پایان فازهای ۰ و ۱ را ثبت می‌کنند؛ Schema این سند در فاز ۲ تغییر نکرده است.

وضعیت: زیرساخت قرارداد v2 و تست‌های Regression پیاده‌سازی شده‌اند. این مرحله فوتر پیش‌فرض جدید را فعال نمی‌کند. Seeder، Runtime/Renderer، Template Resolution، UI قابل مشاهده و CSS تغییر نکرده‌اند؛ Migration یا بازنویسی داده سایت موجود انجام نشده است.

## تغییرات

| فایل | تغییر |
|---|---|
| `app/CMS/Blocks/SiteFooter/SiteFooterDataNormalizer.php` | نسخه canonical برابر ۲؛ حفظ فیلدهای Legacy؛ composition اختیاری؛ رد صریح نسخه/Template ناشناخته؛ ارجاع اعتبارسنجی ساختار جدید به helper محلی. |
| `app/CMS/Blocks/SiteFooter/SiteFooterCompositionNormalizer.php` | قرارداد محلی ستون‌ها، عناصر، شناسه‌ها و Design settings؛ استفاده از Action Normalizer/Validator موجود؛ بدون Query، Resolve منابع، تولید شناسه یا Renderer جدید. |
| `app/CMS/Blocks/SiteFooter/SiteFooterBlock.php` | `version()` همچنان از ثابت Normalizer می‌آید و اکنون ۲ است؛ دو Hidden field برای حفظ `content.composition` و `settings` در Dehydration فرم موجود. هیچ کنترل نمایشی جدیدی اضافه نشده است. |
| `tests/Fixtures/site-footer/legacy-v1.json` | Fixture کامل Legacy شامل سه منو، About، Badgeهای فعال/غیرفعال و دو Action حقوقی. |
| `tests/Fixtures/site-footer/legacy-empty-v1.json` | Fixture محتوای خالی Legacy و پیش‌فرض‌های قدیمی. |
| `tests/Fixtures/site-footer/composition-v2.json` | نمونه کامل قرارداد مطابق مثال گزارش، با همه Typeهای شناخته‌شده؛ داده نمونه تست است و Preset نصب محسوب نمی‌شود. |
| `tests/Unit/SiteFooterDataNormalizerTest.php` | Regression، Idempotence، Query-free بودن، ترتیب/هویت، حدود payload، داده ناقص، رد نسخه/نوع/فیلد ناشناخته و عبور از مرز Save مشترک. |
| `tests/Feature/SiteFooterSchemaCompatibilityTest.php` | برابری Runtime/HTML برای داده Legacy و نسخه Normalized، نبود Write هنگام Read/Fill، Save/Reload واقعی Template Editor و جلوگیری از Save ناسازگار. |

Block Registry، Hydrator، Save Manager، Identity Manager و Action Foundation بازنویسی نشده‌اند؛ helper جدید فقط متعلق به داده Footer است و Registry یا Builder موازی ایجاد نمی‌کند.

همین سند نیز ایجاد و ابتدای گزارش معماری برای ارجاع به وضعیت اجرای فازهای ۰ و ۱ به‌روز شد؛ یافته‌های تاریخی Audit به‌عنوان پیاده‌سازی کامل محصول بازنویسی نشده‌اند.

## قرارداد نهایی canonical

Envelope همان `{ "type": "site_footer", "data": ... }` است. خروجی `data` پنج کلید دارد: `block_id`، `schema_version`، `template`، `content` و `settings`. نمونه زیر یک خروجی canonical معتبر است؛ Preset نهایی محصول یا محتوای Seeder نیست:

```json
{
  "type": "site_footer",
  "data": {
    "block_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "schema_version": 2,
    "template": "corporate-dark-v1",
    "content": {
      "menu_columns": [],
      "contact_title": "ارتباط با ما",
      "about_title": "درباره ما",
      "about_text": null,
      "badges_title": "مجوزها و نمادهای اعتماد",
      "badges": [],
      "privacy_label": "حریم خصوصی",
      "privacy_action": null,
      "terms_label": "قوانین و مقررات",
      "terms_action": null,
      "composition": {
        "main": {
          "columns": [
            {
              "id": "01ARZ3NDEKTSV4RRFFQ69G5FAW",
              "title": null,
              "settings": {"span": 1},
              "elements": [
                {
                  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAX",
                  "type": "branding",
                  "enabled": true,
                  "content": {"source": "site", "name": null, "logo_media_id": null, "logo_alt": null}
                }
              ]
            }
          ]
        },
        "bottom": {
          "elements": [
            {
              "id": "01ARZ3NDEKTSV4RRFFQ69G5FAY",
              "type": "copyright",
              "enabled": true,
              "content": {"source": "site", "text": null, "show_year": true}
            }
          ]
        }
      }
    },
    "settings": {
      "container": "default",
      "spacing": "comfortable",
      "grid": {"desktop": "auto", "tablet": 2, "mobile": 1}
    }
  }
}
```

برای Legacy، همین envelope با `content.composition=null` تولید می‌شود. هیچ ستونی از منوهای قدیمی به Composition تبدیل نمی‌شود. خواندن v1 فقط خروجی در حافظه را canonical v2 می‌کند؛ فوتر در DB تا Save صریح مدیر v1 باقی می‌ماند. Save صریح نیز layout را Legacy نگه می‌دارد، حتی وقتی نسخه ذخیره‌شده ۲ شده باشد. تنظیمات جدید فعلاً توسط Renderer قدیمی مصرف نمی‌شوند و ظاهر قبلی را تغییر نمی‌دهند.

### انواع و مقادیر پیش‌فرض عناصر

همه عناصر `id`، `type`، `enabled` و `content` دارند. enabled غایب برابر true است؛ مقدار false حذف نمی‌شود. فیلدهای اختیاری زیر در نبود مقدار، null هستند مگر پیش‌فرض دیگری نوشته شده باشد.

| Type | ناحیه مجاز | فیلدهای content و پیش‌فرض |
|---|---|---|
| `branding` | main | source=site، name، logo_media_id، logo_alt |
| `navigation` | main | title، menu_id، depth=1 |
| `contact` | main | title، source=site، fields=[phone,mobile,email,address,working_hours]، overrides=[] |
| `social_links` | main | source=site، items=[]؛ هر آیتم label، icon، action دارد. |
| `image` | main | title، media_id، alt، action؛ تصویر و نشان اعتماد از همین Type استفاده می‌کنند. |
| `map` | main | mode=static، title، media_id، alt، directions_action |
| `rich_text` | main | title، source=site، text |
| `form` | main | title، form_id؛ فقط قرارداد داده آماده است و embed در این مرحله اضافه نشده است. |
| `copyright` | bottom | source=site، text، show_year=true؛ سال جاری در JSON ذخیره نمی‌شود. |
| `legal_links` | bottom | items=[]؛ هر آیتم label و action دارد. |

source فقط site/manual/override می‌پذیرد. در Contact، override غایب یا null برای هر فیلد، قرارداد ارث‌بری است؛ fields ترتیب نمایش و انتخاب فیلدها را نگه می‌دارد. خود Normalizer مقدار Settings را نمی‌خواند یا کپی نمی‌کند. خواندن منابع و اعمال معنای source بر عهده Runtime فاز بعد است.

### هویت، ترتیب و Validation

- شناسه Column/Element، ULID معتبر و در همان Footer یکتا است؛ مقایسه بدون حساسیت به حروف انجام و خروجی uppercase می‌شود. شناسه مفقود/نامعتبر/تکراری خطا می‌دهد؛ Normalizer شناسه تصادفی یا مبتنی بر زمان نمی‌سازد. تولید ID هنگام Add/Clone بعداً در مرز Editor انجام خواهد شد. `block_id` همچنان تابع Identity Manager مشترک است.
- ترتیب آرایه مرجع نمایش است؛ کلیدهای UUID مربوط به Repeater به array_values تبدیل می‌شوند و ترتیب iteration حفظ می‌شود. Reorder شناسه‌ها را عوض نمی‌کند. sort_order فقط برای Badgeهای Legacy با معنای قبلی باقی مانده است.
- حداکثر ۸ ستون، ۱۲ عنصر در هر ستون و ۴۸ عنصر کل، شامل عناصر غیرفعال. این حدود برای payload است و چهار ستون را در معماری ثابت نمی‌کند. صفر ستون و صفر عنصر نیز معتبر است تا Composition عمداً خالی دوباره پر نشود؛ این نقطه، حداقل یک ستون پیشنهادی در Audit اولیه را دقیق‌تر می‌کند.
- حداکثر ۲۰ آیتم در هر Social/Legal group؛ Contact fields بدون تکرار و از فهرست مشخص. طول متن ساده/عنوان/Alt حداکثر ۲۵۵، آدرس ۲۰۰۰ و Rich Text حداکثر ۲۰۰۰۰ کاراکتر است.
- Reference جدید فقط عدد صحیح مثبت یا رشته صحیح مثبت است؛ اعشار، boolean و overflow رد می‌شوند. Reference معتبر از نظر شکل، حتی اگر رکورد آن هنوز وجود نداشته باشد، حفظ می‌شود؛ وجود/انتشار/مجوز و فایل واقعی باید در Runtime کنترل شود، نه با Query داخل Normalizer.
- Action جدید به قرارداد مشترک نسخه ۱ Normalize و Validate می‌شود؛ Action خالی null است. Action نامعتبر یا نسخه آینده باعث ValidationException می‌شود و به null تبدیل نمی‌شود. رفتار Actionهای Legacy برای حفظ Regression همان رفتار قبلی است.
- span از ۱ تا ۸؛ depth از ۱ تا ۳. container فقط default/narrow/full؛ spacing فقط compact/comfortable/spacious؛ grid.desktop برابر auto یا ۱ تا ۸، tablet برابر ۱ تا ۴ و mobile برابر ۱ تا ۲ است. همه شمارنده‌ها خروجی صحیح دارند و boolean/اعشار پذیرفته نمی‌شود.
- Map فعلاً فقط قرارداد static دارد. iframe/srcdoc، Script، CSS و HTML به‌عنوان فیلد/Type Composition مجاز نیستند. Rich Text به‌صورت متن ذخیره می‌شود؛ Sanitization هنگام نمایش با RichText موجود، وظیفه Renderer فاز بعد است. icon فقط شناسه ساده می‌پذیرد؛ تطبیق با فهرست Iconهای موجود در مرحله نمایش/انتخاب آیکن انجام می‌شود، نه با خواندن فایل یا Query در Normalizer.
- نسخه Footer غایب/null، Legacy نسخه ۱ محسوب می‌شود؛ فقط ۱ و ۲ پشتیبانی می‌شوند. Composition غیرnull به schema_version=2 صریح نیاز دارد. این شرط از تبدیل ضمنی v1 جلوگیری می‌کند.
- نسخه ناشناخته، Renderer key ناشناخته، Type یا Region نامعتبر، فیلد اضافی در Composition/Settings، یا داده ساختاری ناسازگار با ValidationException متوقف می‌شود. حتی Type ناشناخته غیرفعال بی‌صدا حذف نمی‌شود. کل Save ناموفق می‌ماند؛ تست Editor، ثابت‌ماندن JSON ذخیره‌شده را بررسی می‌کند.

## سازگاری Legacy و محدودیت مرحله فعلی

فیلدهای موجود v1، مقدار About، سه menu_id، عناوین، Badge، ترتیب sort_order و دو Action حقوقی حفظ می‌شوند. Fixture کامل و خالی این رفتار را ثبت می‌کنند؛ Runtime و HTML Legacy در برابر ورودی v1 و داده Normalized برابرند. Badgeهای UUID-keyed نیز پیش از محاسبه index به لیست تبدیل می‌شوند تا داده Repeater به‌دلیل کلید رشته‌ای دچار خطای نوع index نشود.

UI فعلی همچنان سه ردیف منو می‌خواهد و هیچ Composition control ندارد. دو Hidden field صرفاً از حذف State جدید هنگام Save/Reload جلوگیری می‌کنند. تست Save/Reload واقعی Editor همان سه ردیف Legacy را نگه می‌دارد؛ تست Normalizer و مرز Save، حالت Legacy بدون ستون و Composition کاملاً خالی را نیز پوشش می‌دهد. رفع محدودیت نمایشی/Validation تعداد منوهای Legacy مربوط به فاز مدیریت UI است و این مرحله آن را تغییر نداده است.

Normalizer در هر فراخوانی، از جمله Hydration و فراخوانی Runtime فعلی، نسخه/نوع ناشناخته را رد می‌کند. در این مرحله صفحه Read-only برای ویرایش داده آینده یا fallback نمایشی نسخه آینده ساخته نشده است؛ فاز Runtime باید خطاهای قرارداد ناسازگار را در مرز مناسب مدیریت کند. رد داده ناسازگار با پشتیبانی از نمایش آن داده یکسان نیست.

## مرز آمادگی فاز ۲

آماده: قرارداد canonical، تشخیص Legacy در composition=null، typed elements، هویت و ترتیب پایدار، محافظت Save در برابر داده ناشناخته، نگهداری State در فرم فعلی و تست Regression. داده نمونه هیچ Settings یا سالی را Snapshot نمی‌کند و Reference ناموجود را جعل نمی‌کند.

مرحله بعد: Runtime باید بر اساس composition غیرnull مسیر جدید را انتخاب و Settings/Menu/Media/Action را پیش از Blade Resolve کند، خروجی عناصر و نواحی خالی را حذف کند و از Partialهای بدون Query استفاده کند. Renderer فعلی Composition را نمایش نمی‌دهد؛ انتشار/Seed Preset جدید تا آماده‌شدن Renderer، کنترل‌های مدیریت و Responsive انجام نشود. قرارداد هشت‌گروهی پیش‌فرض نصب تازه در A.1 پابرجاست؛ انجام این دو فاز، به معنی پایان کل Product Requirement نیست.

## نتیجه بررسی‌ها

اجرای تست‌ها طبق `docs/test-database-safety.md` با `APP_ENV=testing`، `DB_CONNECTION=sqlite`، `DB_DATABASE=:memory:` و `DB_URL` خالی در فرایند تست انجام شد؛ Config Cache موجود نبود. Guard محیط تست و TestCase مشترک فعال بودند. دستور دستی Migration/Reset/Seed روی دیتابیس سایت اجرا نشد؛ RefreshDatabase و فراخوانی Seeder موجود در تست قبلی Footer فقط در SQLite حافظه‌ای تست اجرا شدند.

| بررسی | نتیجه |
|---|---|
| Baseline پیش از تغییر کد تولیدی؛ Fixture/Normalizer Legacy + Runtime/HTML + تست فعلی Footer | موفق: ۱۰ تست، ۹۲ Assertion. |
| مجموعه نهایی مرتبط؛ Footer، Shared Editor، Identity، Action، Picker و Preview | موفق: ۱۵۸ تست، ۵۴۴ Assertion، حدود ۵۴ ثانیه. |
| `pint --test` فقط روی ۵ فایل PHP این تغییر | موفق. |
| `git diff --check` روی فایل‌های تولیدی تغییرکرده | موفق؛ هیچ خطای whitespace. |
| Fixtureها و مثال‌های JSON مستندات | UTF-8 و JSON معتبر. |
| مرورگر/Device/Production | اجرا نشده؛ UI/CSS این مرحله تغییر نکرده است. |

مجموعه موفق از این فایل‌ها تشکیل شد:

```text
tests/Unit/SiteFooterDataNormalizerTest.php
tests/Feature/SiteFooterSchemaCompatibilityTest.php
tests/Feature/SiteFooterTemplateTest.php
tests/Unit/BlockEditorHydratorTest.php
tests/Unit/BlockEditorSaveManagerTest.php
tests/Unit/BlockIdentityManagerTest.php
tests/Unit/BlockInspectorTabsTest.php
tests/Unit/Actions/ActionDestinationNormalizerTest.php
tests/Unit/Actions/ActionDestinationValidatorTest.php
tests/Feature/BlockEditorHydratorIntegrationTest.php
tests/Feature/TemplateBlockPickerTest.php
tests/Feature/TemplatePreviewTest.php
```

دستور Runner: `php -d memory_limit=512M vendor/bin/phpunit --colors=never --do-not-cache-result` همراه فایل‌های بالا. اجرای گسترده اولیه با سقف پیش‌فرض ۱۲۸MB به محدودیت حافظه رسید؛ افزایش سقف فقط برای همان فرایند PHPUnit انجام شد و تنظیمات PHP/پروژه تغییر نکرد.

**شکست‌های مستقل موجود:** در اجرای گسترده شامل `tests/Feature/BlockFoundationTest.php`، ۱۶۱ تست اجرا و سه تست همان فایل شکست خوردند: فهرست مورد انتظار Registry، بلوک‌های موجود text_image/pricing_cards/site_footer را ندارد؛ دو تست دیگر هنوز فیلد Legacy به نام title را در Hero انتظار دارند. برای تشخیص Regression، همان سه تست در فرایند جدا با بارگذاری نسخه HEAD هر دو کلاس تغییرکرده موجود Footer (`SiteFooterBlock` و `SiteFooterDataNormalizer`) اجرا شدند و همان سه شکست با همان پیام‌ها بازتولید شدند. هیچ فایل پروژه برای این مقایسه rollback نشد؛ کلاس‌های Baseline از فایل‌های موقت خارج پروژه بارگذاری و سپس فایل‌های موقت پاک شدند. آن تست‌ها تغییر نکردند و کل Suite پروژه به‌عنوان سبز گزارش نمی‌شود.
