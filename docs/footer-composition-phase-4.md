# Footer Composition — فاز ۴: ظاهر و Responsive

تاریخ: 2026-11-03. مبنا: تصمیم قطعی A.1 در [گزارش معماری](KING-FOOTER-SYSTEM-SOURCE-AUDIT-AND-IMPLEMENTATION-PLAN.md)، قرارداد [فازهای ۰ و ۱](footer-composition-phase-0-1.md)، [Runtime فاز ۲](footer-composition-phase-2.md) و [مدیریت فاز ۳](footer-composition-phase-3.md).

در این مرحله تصویر موکاپ واقعاً دریافت شد. سابقه «موکاپ در دسترس نبود» در Audit مربوط به مأموریت اولیه است. طراحی تازه از زمینه سرمه‌ای، تیترهای خط‌دار آبی، ردیف‌های جداشده منو و تماس، دکمه‌های Social، کارت نقشه، نشان‌ها و نوار حقوقی تصویر پیروی می‌کند؛ نام، لوگو، تماس، نقشه، نشان یا متن اختصاصی تصویر وارد محصول نشده است.

وضعیت: کد ظاهر و اتصال تنظیمات پیاده‌سازی شده، Build و بررسی‌های ایمن سمت سرور انجام شده‌اند. مرورگر متصل در دسترس نبود؛ تأیید بصری در پنج عرض و اندازه‌گیری واقعی DOM Overflow انجام نشده‌اند. این محدودیت از نتایج محاسباتی و تست PHP زیر جداست.

## فایل‌های این فاز

| فایل | تغییر |
|---|---|
| `resources/css/app.css` | افزوده‌شدن CSS محدود به `[data-footer-composition]` در همان فایل موجود؛ Tokens محلی، چیدمان، Typography، کارت‌ها، Focus/Hover، Grid و Container Queries. CSS قبلی Legacy و سایر اجزای سایت ویرایش نشد. |
| `resources/views/partials/blocks/site-footer/composition.blade.php` | اتصال Grid و span به متغیرهای CSS عددی؛ گروه‌بندی صرفاً نمایشی تصاویر متوالی بدون جابه‌جایی داده؛ حفظ یک footer و ترتیب main/bottom. |
| `resources/views/partials/blocks/site-footer/element.blade.php` | ساختار نمایشی Branding، تماس، Social با نام قابل دسترس، کارت Map و Copyright؛ همان داده آماده Runtime. |
| `resources/views/partials/blocks/site-footer/link.blade.php` | Class نمایشی مشترک Composition؛ پشتیبانی از Social آیکنی با Label قابل دسترس؛ حفظ href/target/rel آماده Foundation. |
| `resources/views/partials/blocks/site-footer/navigation.blade.php` | ردیف منو با پیکان تزئینی و متن با bdi؛ حفظ عمق و ترتیب Runtime. |
| `resources/views/partials/blocks/site-footer/image.blade.php` | Classهای نمایشی Logo/Map و حذف inline height قبلی برای مصرف CSS؛ حفظ alt، lazy loading و ابعاد رسانه. |
| `app/CMS/Blocks/SiteFooter/SiteFooterCompositionEditor.php` | اصلاح دو خطای اجرایی فاز ۳ که در بررسی این فاز پیدا شدند؛ به‌روزرسانی راهنمای Design/span پس از اتصال ظاهر. |
| `tests/Feature/SiteFooterSchemaCompatibilityTest.php` | بررسی واقعی Actionهای تبدیل/Add/Clone/Move/Reorder/Disable، Save/Reload، وراثت و بازگشت Legacy؛ به‌روزرسانی Fixture برای ActionPicker واقعی و کلیدهای Repeater. |
| `tests/Feature/SiteFooterCompositionRuntimeTest.php` | بررسی اتصال تنظیمات به Renderer و نبود Query در Blade؛ مستقل‌کردن Golden HTML از کامنت‌های داخلی Livewire که به ترتیب اجرای تست وابسته‌اند. |
| `tests/Feature/SiteFooterTemplateTest.php` | جست‌وجوی کامپوننت‌های Shared در فرم واقعی mountشده، به‌جای فرض Schema مسطح قبل از فاز ۳. |
| همین سند، `docs/footer-composition-phase-3.md` و گزارش معماری | گزارش نتیجه، مرز تأیید و ادامه فازها. |

Schema v2، Normalizerها، Runtime، Resolver، Registry، Seeder، Bootstrap، انتخاب Default و داده سایت موجود تغییر نکردند. Builder یا stylesheet/سیستم CSS مستقلی اضافه نشده است. خروجی Build در `public/build` ساخته شد؛ این مسیر طبق تنظیم مخزن generated/ignored است. Migration، Seed دستی، Commit و Deploy انجام نشد.

## قرارداد ظاهر و رفتار Responsive

- `container=default` حداکثر عرض موجود سایت `--theme-container-width` با fallback برابر 1200px را مصرف می‌کند؛ `narrow` برابر 960px و `full` بدون سقف عرض است. Padding در اندازه نهایی ظرف حساب می‌شود.
- `spacing` سه حالت واقعی دارد: compact، comfortable و spacious. فاصله بالای Footer، میان ستون‌ها، میان عناصر و قبل از نوار پایین با آن تغییر می‌کنند.
- زیر 640px، `grid.mobile`؛ از 640 تا قبل از 1024px، `grid.tablet`؛ از 1024px، `grid.desktop` مصرف می‌شود. تعداد ستون صریح تا حدود Schema حفظ می‌شود، حتی اگر همه ستون‌ها در یک ردیف جا نگیرند.
- در desktop=auto، تعداد Track تابع عرض داخلی واقعی ظرف است، نه صرفاً عرض viewport. Container Query به ازای ظرفیت تقریبی 15rem، از یک تا هشت Track انتخاب می‌کند و به مجموع span ستون‌های دارای خروجی محدود می‌شود. چهار ستون، محدودیت معماری نیست؛ مثلاً ظرف full عریض و Composition شش یا هشت‌ستونه می‌تواند بیش از چهار Track داشته باشد.
- span هر ستون به تعداد Track فعال همان اندازه محدود می‌شود؛ span=8 در موبایل تک‌ستونه، هشت ستون ضمنی و Overflow نمی‌سازد. Grid از `minmax(0, 1fr)` و عناصر از حداقل عرض صفر و Wrap متن استفاده می‌کنند. Grid auto-flow معمولی است؛ dense و تغییر CSS order که ترتیب خواندن را عوض کند به‌کار نرفته است.
- برای مرورگر بدون Container Query، شبکه صریح و breakpointها باقی می‌مانند و ظرفیت خودکار دسکتاپ fallback برابر چهار دارد؛ شمار ستون‌ها همچنان از محتوای موجود بیشتر فرض نمی‌شود. پشتیبانی اجرایی مرورگر در این محیط بررسی نشده است.
- تصاویر متوالی صرفاً در Renderer داخل گروه شبکه‌ای قرار می‌گیرند تا نشان‌ها کنار هم باشند؛ یک تصویر تنها تمام عرض گروه را می‌گیرد. ترتیب و ID عناصر تغییر نمی‌کنند. تصویر نشان object-fit:contain و تصویر Map نسبت رزروشده 16:10 با object-fit:cover دارد؛ فایل یا تصویر فرضی ساخته نمی‌شود.
- مسیریابی روی تصویر Map در ستون مناسب به شکل دکمه قرار می‌گیرد. در ظرف باریک‌تر از 12rem، لینک زیر تصویر قرار می‌گیرد تا از کارت بیرون نزند. حالت تصویر تنها، لینک تنها و نبود کامل داده همان رفتار Runtime فاز ۲ را دارند. پین با مختصات حدسی، iframe یا SDK اضافه نشده است.
- نوار پایین Wrap می‌شود و در موبایل عمودی است؛ DOM order مرجع ترتیب Copyright و Legal باقی می‌ماند. متن حقوقی و سال همان داده Runtime هستند؛ سال شمسی یا نام نمونه تصویر Hardcode نشده‌اند.

## طراحی، RTL و دسترس‌پذیری

Skill سراسری `C:/Users/Marco/.agents/skills/ui-ux-pro-max/SKILL.md` مجدداً خوانده شد. جست‌وجوی `focus visible contrast` در domain=ux بر Focus قابل مشاهده و تفکیک الزامات AA/AAA تأکید داشت؛ جست‌وجوی `responsive grid overflow` در stack=html-tailwind اصول فاصله منسجم و مهار متن را ارائه کرد. داده Stack مربوط به نسخه ۴ بود؛ API آن به Tailwind 3 پروژه تعمیم داده نشد و این پیاده‌سازی از CSS بومی در همان فایل موجود استفاده می‌کند.

فونت از متغیر فعلی سایت ارث می‌برد و fallback فارسی Tahoma دارد؛ فونت یا کتابخانه آیکن جدید دانلود نشده است. نسبت خطوط، وزن تیترها، طول متن و Wrap برای فارسی در نظر گرفته شده‌اند. تماس‌ها bdi/LTR، آیکن‌ها تزئینی، Socialهای آیکنی دارای Label مخفی قابل دسترس و لینک‌ها دارای Focus دوپیکسلی روشن هستند. کاهش حرکت، Transition لینک‌ها را خاموش می‌کند. تزئینات زمینه عمومی و غیرتعاملی‌اند و به کسب‌وکار نمونه وابسته نیستند.

محاسبه رنگ‌های اصلی: متن روشن روی زمینه 16.39:1، متن فرعی روی سطح کارت 9.03:1 و Focus روی سطح Hover برابر 5.95:1 است. این اعداد محاسبه رنگ‌های مشخص CSS هستند؛ گواهی جامع WCAG یا ارزیابی کنتراست رسانه‌های دلخواه مدیر نیستند.

## بررسی پنج عرض

ابزار Computer Use فهرست خالی مرورگرها را برگرداند. تلاش برای `createBrowserTab('iab', 'http://localhost')` نیز با `Browser is not available: iab` متوقف شد. Screenshot، بازکردن Admin در مرورگر و اندازه‌گیری scrollWidth/clientWidth ممکن نبود؛ نبود مرورگر با آزمون تصویری جایگزین یا تصویر ساختگی پوشانده نشده است.

جدول زیر **فقط محاسبه ایستای قواعد CSS** برای چهار ستون با span=1، حالت default/comfortable و Grid پیش‌فرض است؛ نتیجه مشاهده مرورگر نیست. فرض اندازه rem برابر 16px است.

| عرض viewport | عرض داخلی ظرف | Track فعال | عرض هر Track | تأیید بصری/DOM Overflow |
|---|---:|---:|---:|---|
| 320px | 288px | 1 | 288px | انجام نشده؛ مرورگر موجود نیست |
| 375px | 343px | 1 | 343px | انجام نشده؛ مرورگر موجود نیست |
| 768px | 706.56px | 2 | 339.84px | انجام نشده؛ مرورگر موجود نیست |
| 1024px | 942.08px | 3 | 290.13px | انجام نشده؛ مرورگر موجود نیست |
| 1440px | 1088px | 4 | 236px | انجام نشده؛ مرورگر موجود نیست |

محاسبه ترکیبی 69,120 حالت از همین پنج عرض، سه Container، Gridهای مجاز و تعداد/spanهای یک تا هشت، عرض Track مثبت و span محدود به Track را نشان داد. این بررسی، هندسه شبکه و نبود Track ضمنی را ارزیابی می‌کند؛ جایگزین بررسی محتوا، فونت، Focus و Overflow واقعی مرورگر نیست. Parse CSS و بررسی Selector نیز ۷۸ Rule با scope Composition و صفر Selector خارج از آن نشان داد.

## وضعیت UI فاز ۳

بررسی اجرایی ایزوله دو ایراد واقعی پیدا کرد و در همان فایل Editor اصلاح شدند:

1. Actions ثابت پس از Clone بلوک به ActionContainer اولیه بدون Container اشاره می‌کردند و بازشدن فرم خطا می‌داد. اکنون Actions پس از Bind شدن Container بلوک از Schema پویا ساخته می‌شوند؛ هسته Filament یا Builder تغییر نکرد.
2. انتقال عنصر، خروجی متد `getLivewire()` را مستقیم به پارامتر by-reference تابع `data_set` می‌داد؛ PHP 8.4 این حالت را خطا می‌کرد. اکنون شیء Livewire ابتدا در متغیر محلی قرار می‌گیرد.

تست Livewire، تبدیل صریح بدون Write تا Save، افزودن ستون و Branding، کپی با شناسه‌های تازه، انتقال با شناسه ثابت، Move Up، Disable، نگهداری نام دستی پنهان‌شده در حالت source=site، ذخیره، بازگشایی، ذخیره مجدد و بازگشت صریح Legacy را اجرا می‌کند. تست دیگر، تمام تنظیمات و داده عنصر Form خارج از محدوده را در Save/Reload حفظ می‌کند. مرجع‌های Page لازم برای Fixture در همان SQLite حافظه‌ای ساخته شدند؛ مرجع مفقود از اعتبارسنجی واقعی ActionPicker دور زده نشد.

کنترل‌های خالی Contact ممکن است در اولین Save کلیدهای اختیاری Override را با null صریح ثبت کنند؛ این همان قرارداد ارث‌بری است، نه Snapshot اطلاعات سایت. Save بعدی ثابت می‌ماند و مقادیر قبلی، IDها و ترتیب حفظ می‌شوند. نسخه و نوع ناشناخته نیز Save را ناموفق می‌کنند و سند ذخیره‌شده دست‌نخورده می‌ماند.

این تأیید سمت سرور است. تعامل واقعی مرورگر، Drag، Overlay انتخاب رسانه، رفتار Keyboard/Focus پس از Modal، dirty notice و ظاهر فرم هنوز تأیید نشده‌اند.

## نتایج نهایی

مجموعه مرتبط موفق بود: **۶۸ تست، ۳۸۹ Assertion**، حدود ۴۲ ثانیه و ۱۰۸MB حافظه. دستور Runner: `php -d memory_limit=512M vendor/bin/phpunit --colors=never --do-not-cache-result` همراه پنج فایل زیر. Build نیز موفق بود؛ تأیید مرورگر در این عددها گنجانده نشده است.

راهنمای `docs/test-database-safety.md` پیش از اجرای تست خوانده شد؛ Config Cache وجود نداشت. Runner فقط با `APP_ENV=testing`، `DB_CONNECTION=sqlite`، `DB_DATABASE=:memory:` و `DB_URL` خالی اجرا شد؛ Guard محیط تست فعال بود. دستور دستی Migration/Reset/Seed روی دیتابیس سایت اجرا نشد. تست قبلی Seeder فقط در محیط حافظه‌ای خودش اجرا شد؛ کد Seeder تغییر نکرد.

مجموعه مرتبط:

```text
tests/Feature/SiteFooterSchemaCompatibilityTest.php
tests/Feature/SiteFooterCompositionRuntimeTest.php
tests/Feature/SiteFooterTemplateTest.php
tests/Unit/SiteFooterDataNormalizerTest.php
tests/Feature/TemplatePreviewTest.php
```

CSS و Renderer Legacy با SHA-256 قبل/بعد برابر بودند. Golden HTML نیز با حذف فقط کامنت‌های داخلی Morph از خود آزمون مقایسه می‌شود؛ این کامنت‌ها به ترتیب Boot تست Livewire وابسته‌اند. محتوای واقعی HTML Legacy حذف یا Snapshot تازه‌ای برای پذیرفتن Regression ثبت نشد. کل Suite پروژه اجرا و سبز اعلام نشده است؛ سه شکست تاریخی BlockFoundationTest خارج از مجموعه این مرحله بودند.

Build با Vite موفق بود. هشدار Chunk بزرگ مربوط به فایل موجود `hero-dotted-surface` است؛ Footer JavaScript یا Chunk جدیدی اضافه نکرد. نحو PHP، Pint محدود و whitespace بررسی شدند.

## ادامه فازها

کد فاز ۴ برای ادامه برنامه آماده است؛ مانع شناخته‌شده سمت سرور در مجموعه مرتبط باقی نماند. پذیرش بصری قطعی پنج عرض و نبود Overflow واقعی تا دسترسی به مرورگر تأیید نشده است. ترتیب Audit حفظ می‌شود: تکمیل بررسی hardening Map/Badge در فاز ۵، سپس Publication/Preview فاز ۶ و Bootstrap غیرمخرب نصب تازه در فاز ۷. نقشه ایستا و عناصر پایه قبلاً Renderer دارند و اکنون ظاهر آن‌ها متصل است؛ این موضوع مجوز تغییر زودهنگام Seeder نیست. Form و نقشه تعاملی خارج از این فاز باقی ماندند.
