# Footer Composition — فاز ۲: Runtime و Rendering

به‌روزرسانی بعدی: [مدیریت Composition فاز ۳](footer-composition-phase-3.md) در همان Editor اضافه شد. Runtime/Renderer این مرحله دست‌نخورده‌اند؛ تست‌های ثبت‌شده این سند به معنی آزمون UI جدید نیستند و در فاز ۳ بنا به درخواست تستی اجرا نشده است.

تاریخ: 2026-11-03. مبنا: بخش A.1 [گزارش معماری](KING-FOOTER-SYSTEM-SOURCE-AUDIT-AND-IMPLEMENTATION-PLAN.md) و [قرارداد فازهای ۰ و ۱](footer-composition-phase-0-1.md). Map ایستا نیز طبق درخواست این فاز پیاده شد؛ iframe، SDK و Form در این مرحله اضافه نشده‌اند.

## نتیجه و محدوده

Composition نسخه ۲ اکنون از همان `SiteFooterRuntime` و View ثبت‌شده قبلی رندر می‌شود. تشخیص مسیر از `content.composition` است؛ مقدار null به مسیر Legacy قبلی می‌رود. Runtime/Data Normalizer، Block Key و Template Resolution موازی ساخته نشده‌اند. helper جدید فقط داده نمایشی Composition را پیش از Blade آماده می‌کند؛ Registry و Schema تغییر نکرده‌اند.

خروجی Legacy با Fixture خروجی HTML که پیش از تغییر Renderer ثبت شد، **دقیقاً برابر** است. مسیر Legacy و آرایه داده نمایشی آن به قوت خود باقی است. هیچ قالبی خودکار به Composition تبدیل نشده و قالب جدیدی Seed نشده است.

Seeder، UI مدیریت، فایل‌های CSS، Migration و داده سایت موجود تغییر نکردند. این مرحله، پایان Product Requirement نصب تازه نیست؛ ساخت Preset کامل نصب تازه همچنان پس از آماده‌شدن مراحل بعد انجام می‌شود.

## فایل‌های این فاز

| فایل | تغییر |
|---|---|
| `app/CMS/Blocks/SiteFooter/SiteFooterRuntime.php` | انتخاب مسیر Composition پس از Normalize؛ مسیر Legacy بدون تغییر؛ قرارداد ناشناخته در مسیر خواندن خروجی خالی و امن می‌دهد، بدون تغییر ذخیره‌سازی. |
| `app/CMS/Blocks/SiteFooter/SiteFooterCompositionRuntime.php` | آماده‌سازی عناصر، وراثت Settings، رسانه، Navigation، Action و Rich Text؛ Cache محلی فقط در همان prepare. |
| `resources/views/partials/blocks/site-footer-corporate-dark.blade.php` | فقط شاخه Composition افزوده شد؛ متن Renderer Legacy حفظ شد. |
| `resources/views/partials/blocks/site-footer/composition.blade.php` | main/bottom و ستون‌ها به ترتیب ذخیره‌شده؛ حداکثر یک footer در خروجی این بلوک. |
| `resources/views/partials/blocks/site-footer/element.blade.php` | نمایش عناصر معتبر و آماده با Heading، nav/address و آیکن تزئینی. |
| `resources/views/partials/blocks/site-footer/image.blade.php` | تصویر Media با Alt، lazy loading، decoding و ابعاد در صورت موجودبودن. |
| `resources/views/partials/blocks/site-footer/link.blade.php` | مصرف Action Presentation آماده؛ لینک متنی، Social یا تصویر؛ target/rel از Foundation موجود. |
| `resources/views/partials/blocks/site-footer/navigation.blade.php` | نمایش بازگشتی Payload آماده تا عمق مجاز؛ بدون Model یا Query. |
| `tests/Feature/SiteFooterCompositionRuntimeTest.php` | تست Runtime/Renderer/Settings/Media/Navigation/Action/Preview، ترتیب، داده ناقص و Legacy golden output. |
| `tests/Fixtures/site-footer/legacy-presentation.json` | داده نمایشی ثابت شامل شاخه‌های مختلف Renderer قدیمی برای مقایسه خروجی. |
| `tests/Fixtures/site-footer/legacy-output.html` | خروجی ثبت‌شده Renderer پیش از ویرایش فاز ۲؛ ورودی آن Fixture بالاست. |

این سند ایجاد شد و ابتدای دو سند پیشین برای ارجاع به وضعیت فاز ۲ به‌روز شد؛ محتوای تاریخی Audit به‌عنوان وضعیت جاری بازنویسی نشده است.

## رفتار عناصر

| عنصر | رفتار پیاده‌شده |
|---|---|
| Branding | نام سایت و لوگو از Settings؛ source=manual مستقل و source=override به تفکیک فیلد. لوگوی دستی با Media ID؛ لوگوی محلی ناموجود پنهان می‌شود. |
| معرفی / Rich Text | همان `rich_text` در Schema قبلی، با source=site از footerText و fallback موجود siteDescription. معرفی کنار Branding به‌صورت این عنصر قابل ترکیب است؛ فیلد جدیدی به Schema Branding اضافه نشده و متن معرفی دوبار تزریق نمی‌شود. |
| Navigation | همان MenuService، Source Registry و Visibility؛ menu_id فعال؛ ترتیب Core و depth از ۱ تا ۳. Referenceهایی که Model آن‌ها scopePublished دارد با همان Scope عمومی کنترل می‌شوند تا صرف داشتن slug، لینک Draft نسازد. |
| Contact | fields ترتیب و انتخاب Phone/Mobile/Email/Address/Working Hours را تعیین می‌کند. Site/Manual/Override رعایت می‌شود؛ تلفن و Email با Action Foundation اعتبارسنجی و آماده می‌شوند. مقدار نامعتبر پنهان می‌شود. |
| Social | site از socialLinks موجود؛ manual فقط items؛ override با items غیرخالی جایگزین کل لیست است و با لیست خالی ارث‌بری می‌کند. Schema کلید شبکه برای Merge موردی ندارد؛ Merge حدسی با عنوان یا index انجام نمی‌شود. برای پنهان‌کردن کل عنصر از enabled=false استفاده شود. |
| Image / Trust Badge | فقط reusable image موجود در Media Library و دارای فایل؛ Alt دستی سپس Metadata/Title. مقصد اختیاری Action است؛ مقصد ناموجود تصویر معتبر را به تصویر بدون لینک تبدیل می‌کند. فایل کپی نمی‌شود. |
| Map | تصویر Media و لینک مسیریابی از Action Foundation؛ بدون تصویر، فقط لینک معتبر؛ بدون لینک، تصویر معتبر می‌تواند نمایش داده شود؛ بدون هر دو، عنصر و Wrapper آن حذف نمایشی می‌شوند. |
| Copyright | متن source=site از نام سایت، manual مستقل و override با fallback؛ سال جاری هنگام Runtime خوانده می‌شود و show_year رعایت می‌شود. |
| Legal Links | فقط آیتم دارای Label و Action قابل رندر؛ ترتیب حفظ می‌شود. مقصد ناموجود یا غیرقابل نمایش حذف نمایشی می‌شود. |
| Form | Schema حفظ می‌شود، اما عنصر form و Form Actionهای داخل Composition رندر نمی‌شوند؛ نه Form Query و نه فرم ارسال جدیدی افزوده شده است. |

در وراثت فیلدی، site مقادیر دستی را نادیده می‌گیرد؛ manual به Settings fallback نمی‌کند؛ override مقدار دستی غیرnull را مصرف و در غیر این صورت ارث‌بری می‌کند. Reference دستی مثبتی که رسانه آن حذف شده باشد، با لوگوی سایت بی‌صدا جایگزین نمی‌شود. همه تغییرات نمایشی در حافظه انجام می‌شوند و JSON قالب بازنویسی نمی‌شود.

## امنیت، Query و دسترس‌پذیری

- رسانه‌های موردنیاز عناصر فعال، با یک whereIn خوانده می‌شوند. دریافت منوی یکسان و Resolve Action یکسان در همان prepare تکرار نمی‌شود؛ Cache جدیدی میان Requestها، سایت‌ها یا Preview/Production نگهداری نمی‌شود.
- تمام Modelها و URLها پیش از Blade به Array نمایشی تبدیل می‌شوند. تست رندر Payload آماده، صفر Query را بررسی می‌کند؛ این به معنی صفر Query در مرحله prepare یا کل Request نیست.
- URLهای Settings و Navigation نیز با ActionDestinationNormalizer/Validator موجود بررسی می‌شوند. خروجی Action از RuntimeActionResolver و ActionPresentation موجود می‌آید؛ javascript/protocol-relative و Placeholder `#` لینک عمومی ایجاد نمی‌کنند. تصاویر Media و لوگوی Settings به HTML/SVG خام تبدیل نمی‌شوند.
- آدرس Logo محلی با وجود فایل تطبیق داده می‌شود. URL خارجی معتبر از نظر ساختار، بدون درخواست شبکه هنگام Render پذیرفته می‌شود؛ قابل دسترس‌بودن سرویس خارجی در این مرحله تضمین نشده است.
- Rich Text پیش از Blade با `RichText::render()` مشترک sanitize می‌شود؛ محتوای پاک‌شده بدون متن مؤثر، عنصر خالی ایجاد نمی‌کند. متن ساده، نام، عنوان، Alt و Label توسط Blade Escape می‌شوند.
- عناصر غیرفعال، ناموجود یا فاقد خروجی، و ستون/ناحیه بدون عنصر مؤثر پنهان می‌شوند. فوتر کاملاً خالی هیچ footer خالی نمی‌سازد؛ حالت دارای خروجی فقط یک `<footer>` در این بلوک دارد.
- Footer جدید dir=rtl دارد؛ تلفن/Email در bdi با جهت ltr، Navigation و Legal/Social با نام قابل فهم، آیکن تزئینی aria-hidden و تصاویر با Alt نمایش داده می‌شوند. ترتیب DOM همان ترتیب داده است. اصول مرتبط Skill سراسری `ui-ux-pro-max` درباره Icon semantics و Keyboard Navigation خوانده و در این خروجی اعمال شدند؛ کتابخانه آیکن جدید اضافه نشد.
- از کلاس‌های CSS موجود استفاده شده است. تنها محافظ تصویری inline برای max-width:100% و height:auto اضافه شده تا تصویر از ظرف بیرون نزند؛ بازطراحی یا تغییر فایل CSS انجام نشده است. تنظیمات container/spacing/grid/span روی data attributes حفظ شده‌اند؛ اعمال Design controls و breakpointهای قابل تنظیم در مرحله طراحی Responsive تکمیل می‌شود.

## Preview، Legacy و قرارداد ناشناخته

View Composer و مسیر Preview قبلی تغییر نکرده‌اند. Production و Preview هر دو Runtime مشترک دارند؛ تفاوت ResolutionMode همان سیاست Action Foundation است. لینک Page Draft در Production حذف می‌شود و در Preview مجاز، مقصد Admin Preview می‌گیرد. Navigation از Visibility و Scope عمومی استفاده می‌کند و Draft آن در هر دو حالت لینک عمومی تولید نمی‌کند. Route Preview همچنان Admin-only و noindex/nofollow است.

Normalizer/مرز Save فاز ۱ برای نسخه، Type یا فیلد ناشناخته همچنان ValidationException می‌دهد و داده را تنزل نسخه یا حذف نمی‌کند. فقط مسیر خواندن در SiteFooterRuntime آن خطای قرارداد را به خروجی خالی تبدیل می‌کند تا Footer ناسازگار باعث خطای کل صفحه نشود. چنین داده‌ای خودکار به Legacy تبدیل یا ذخیره نمی‌شود.

خروجی Legacy از روی Schema v1 و v2 با composition=null همان Runtime قبلی را می‌گیرد؛ Snapshot جدید، HTML قبلی شامل Navigation، Contact، About، Badgeهای لینک‌دار/بدون لینک و Legal را تثبیت می‌کند.

## نتایج واقعی تست

راهنمای `docs/test-database-safety.md` پیش از تست خوانده شد. تست‌ها با APP_ENV=testing، SQLite :memory:، DB_URL خالی و Guardهای پروژه اجرا شدند؛ Config Cache وجود نداشت. دستور دستی Migration، Reset یا Seed روی دیتابیس سایت اجرا نشد. RefreshDatabase و Seeder موجود در تست Legacy فقط در دیتابیس حافظه‌ای تست اجرا شدند.

**مجموعه مرتبط: موفق — ۱۶۱ تست، ۵۶۵ Assertion، حدود ۲۴ ثانیه.** Runner از `php -d memory_limit=512M vendor/bin/phpunit --colors=never --do-not-cache-result` استفاده کرد؛ تنظیم PHP پروژه تغییر نکرد.

```text
tests/Feature/SiteFooterCompositionRuntimeTest.php
tests/Feature/SiteFooterSchemaCompatibilityTest.php
tests/Feature/SiteFooterTemplateTest.php
tests/Unit/SiteFooterDataNormalizerTest.php
tests/Unit/NavigationRendererTest.php
tests/Unit/NavigationSourceVisibilityTest.php
tests/Unit/NavigationSourceRegistryTest.php
tests/Feature/ServiceNavigationSourceTest.php
tests/Feature/TemplatePreviewTest.php
tests/Unit/Actions/ActionDestinationNormalizerTest.php
tests/Unit/Actions/ActionDestinationValidatorTest.php
tests/Unit/Actions/RuntimeActionResolverTest.php
```

پوشش اختصاصی شامل داده پویا و تمام حالت‌های source، هشت ستون، ترتیب main/bottom، Media batch و Metadata/حذف فایل، رسانه non-reusable/PDF، Map fallback، Navigation Source/عمق/Inactive/Draft، Publication و Preview Action، نبود Query در Blade، HTML Legacy، خالی/غیرفعال/ناسازگار و Auth Preview است.

**شکست‌های قدیمی جداگانه:** `tests/Feature/BlockFoundationTest.php` دوباره اجرا شد و همان سه شکست قبلی را داشت: یک فهرست Registry قدیمی که text_image/pricing_cards/site_footer را ندارد و دو انتظار قدیمی فیلد title در Hero. این شکست‌ها در فاز قبل با کلاس‌های Footer پیش از تغییر نیز بازتولید شده بودند. در فاز ۲ Registry، Hero و خود این فایل تست تغییر نکرده‌اند؛ این سه مورد Regression جدید Footer نیستند و برطرف‌شده گزارش نمی‌شوند. کل Suite پروژه سبز اعلام نمی‌شود.

Pint روی سه فایل PHP این فاز و diff whitespace بررسی شد. تست مرورگر/Device و تأیید بصری Production انجام نشده است؛ طراحی Responsive کامل و UI مدیریت خارج از این فاز هستند.

## آمادگی فاز ۳

مدیریت بصری ستون‌ها و عناصر اکنون می‌تواند به همان Schema و Runtime متصل شود. شناسه‌ها، ترتیب، enabled، source و Override آماده‌اند و عناصر بی‌داده در JSON باقی می‌مانند. فاز بعد باید کنترل‌های Add/Delete/Disable/Move، نمایش وضعیت Reference ناموجود و Preview ذخیره‌شده را روی فرم موجود اضافه کند؛ Parser، Renderer یا Builder دوم لازم نیست.

انتشار Preset استاندارد نصب تازه همچنان به تکمیل کنترل‌های مدیریت، Design/Responsive و Bootstrap غیرمخرب وابسته است. Form و Map تعاملی به این مرحله اضافه نشده‌اند.
