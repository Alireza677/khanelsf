# King CMS — نهایی‌سازی Footer پیش‌فرض نصب تازه

مبنا: تصمیم قطعی A.1 در [گزارش معماری](KING-FOOTER-SYSTEM-SOURCE-AUDIT-AND-IMPLEMENTATION-PLAN.md) و قرارداد و پیاده‌سازی [۰ و ۱](footer-composition-phase-0-1.md)، [۲](footer-composition-phase-2.md)، [۳](footer-composition-phase-3.md) و [۴](footer-composition-phase-4.md).

وضعیت: کد نصب پیش‌فرض و Guard انتشار اصلاح شد. در این مأموریت هیچ Seeder، Migration، Laravel، PHPUnit، Browser Test یا Build اجرا نشد و داده سایت تغییر نکرد. بررسی مجاز فقط نحو چهار فایل PHP تغییرکرده است؛ نتیجه واقعی نصب تازه و Re-seed هنوز تأیید اجرایی ندارد.

## فایل‌ها

| فایل | تغییر |
|---|---|
| `database/seeders/CorporateFooterTemplateSeeder.php` | Preset کامل canonical v2، ULID معتبر، Published/Default/all، منوی دارای Reference واقعی و انتخاب خودکار؛ فقط در نصب pending و داخل Transaction. |
| `database/seeders/DatabaseSeeder.php` | ثبت intent نصب پیش از هر داده پایه؛ حفظ فراخوانی رسمی Footer Seeder؛ حذف Bootstrap موازی منوی Footer قدیمی؛ حذف مقدارهای ساختگی اولیه نام/معرفی/تماس از تنظیمات تازه. |
| `app/CMS/Templates/TemplatePublicationValidator.php` | اعتبارسنجی اختصاصی و محدود داده `site_footer` با Normalizer نصب‌شده، کنترل ID/Renderer و جلوگیری از انتشار عنصر Form فعالِ فاقد Renderer؛ Legacy و حالت عمداً خالی معتبر باقی می‌مانند. |
| `app/Filament/Resources/Concerns/ValidatesTemplatePublication.php` | اتصال `site_footer` به همان Guard موجود، پس از Prepare مشترک Editor. |
| `README.md` و گزارش معماری | قرارداد نصب رسمی و مرز حفظ سایت موجود. |
| همین سند | نتیجه و موارد تأییدنشده. |

Registry، Builder، Schema، Runtime، Template Resolution، Renderer، UI و CSS تغییر نکردند. جدول یا Migration جدیدی اضافه نشده است.

## قرارداد نصب رسمی

مسیر مستند مخزن همان `php artisan migrate` و سپس `php artisan db:seed` است؛ اجرای `DatabaseSeeder` اکنون پیش از اولین فراخوانی Seeder و ساخت هر کاربر/Setting/محتوا، `prepareFooterBootstrap()` را اجرا می‌کند. این دستورات در این مأموریت اجرا نشدند.

تشخیص نصب تازه بر نبود Footer متکی نیست: اگر Setting داخلی `king_footer_bootstrap` وجود نداشته باشد، تمام جدول‌های اتصال فعلی، به‌جز تاریخچه Migration و جدول‌های داخلی SQLite، برای وجود داده بررسی می‌شوند. وجود هر رکورد، حتی در جدولی غیر از Footer، ثبت intent نصب تازه را متوقف می‌کند. در نتیجه سایتی که فقط Footer، انتخاب، منو یا Default خود را حذف کرده، نصب تازه فرض نمی‌شود. مسیر رسمی روی دیتابیس تازه Migrated و بدون داده سایت، intent را خودکار ثبت می‌کند و نیاز به انتخاب دستی قالب ندارد.

Setting داخلی روی نصب واقعاً تازه با مقدار `pending` ساخته می‌شود؛ روی سایت موجودِ بدون marker، backfill یا ایجاد marker انجام نمی‌شود. این Setting نه Snapshot اطلاعات سایت است و نه تنظیم نمایشی Footer. مقدار pending از نصب نیمه‌تمام باقی می‌ماند تا ادامه Bootstrap قابل انجام باشد؛ نباید برای Restore یا Upgrade دستی تنظیم شود.

در نقطه قبلی فراخوانی Corporate Footer در `DatabaseSeeder`، همان Seeder اجرا می‌شود. ساخت منو، آیتم‌ها، Template، انتخاب‌های `footer_template_id`/`footer_menu_id` و انتقال marker به `completed` در یک Transaction و با قفل سطر marker انجام می‌شوند. خطا در ساخت یا Guard، این مجموعه را Rollback می‌کند و pending برای ادامه نصب باقی می‌ماند. این تغییر کل DatabaseSeeder را به Installer یا Transaction موازی تبدیل نمی‌کند.

اگر در نصب pending، مدیر قبلاً Footer، انتخاب حتی خالی، یا یکی از منوهای رزروشده قبلی را ساخته باشد، Seeder آن را حفظ می‌کند و marker را `preserved` می‌گذارد. نصب بدون تعارض، قالب `corporate-dark-footer-v1` را با **Schema v2**، `type=site_footer`، `status=published`، `is_default=true`، `conditions.type=all` و انتخاب صریح همان ID ایجاد می‌کند. نام slug و کلید Renderer قبلی حفظ شده‌اند؛ معنای Schema از composition می‌آید، نه پسوند slug.

## ترکیب پیش‌فرض و داده پویا

| ناحیه | عناصر اولیه |
|---|---|
| درباره کسب‌وکار | Branding، Rich Text معرفی، Social Links؛ همگی source=site |
| دسترسی سریع | Navigation با menu_id واقعی ایجادشده در همان نصب |
| اطلاعات تماس | Contact با source=site و پنج فیلد تلفن، موبایل، ایمیل، آدرس و ساعات کاری |
| موقعیت و اعتماد | Map ایستا و Image/Trust Badge با Referenceهای null و قابل تنظیم |
| پایین | Copyright با source=site و سال Runtime؛ Legal Links با دو ورودی قابل تنظیم قوانین و حریم خصوصی |

این چهار ستون Preset هستند؛ حدود و قابلیت افزودن/حذف/انتقال Schema و Editor تغییر نکرده‌اند. همه ۹ عنصر فعال‌اند، زیرا Renderer آن‌ها آماده است؛ فعال بودن عنصر بی‌داده، مجوز تولید تصویر، لینک یا تماس ساختگی نیست. Normalizer مقدارهای اختیاری را canonical می‌کند و Runtime قبلی عناصر/ستون‌های بدون خروجی مؤثر را پنهان می‌کند. عناصر در JSON و Editor باقی می‌مانند.

نام، لوگو، معرفی، تماس و Social در Footer کپی نمی‌شوند. انتخاب source=site در Runtime به Settings موجود متصل است؛ Map، Image و Legal تا تنظیم Reference واقعی، مقدار null دارند. هیچ تصویر/نشان اعتماد، مختصات نقشه یا URL حقوقی حدسی ایجاد نمی‌شود. منو نیز فقط به Pageهای واقعاً موجود و Published با شناسه همان رکوردها ارجاع می‌دهد؛ URL Resolveشده داخل Composition ذخیره نمی‌شود.

Bootstrap قبلی `hello@example.com`، تلفن `555`، آدرس فرضی و متن معرفی Starter می‌ساخت که از مسیر Settings وارد Footer پویا می‌شدند. defaults تازه برای معرفی و تماس خالی شدند؛ نام اولیه از `config('app.name')` به Site Settings می‌رود. این تغییرها در defaultsِ `firstOrCreate` هستند و مقدارهای موجود را پاک یا اصلاح نمی‌کنند. پاک‌کردن مقدار نمونه موجود یک سایت، اقدام این مأموریت نیست.

## اجرای مجدد و Legacy

- Seeder اختصاصی، بدون marker=pending پیش از ساخت یا به‌روزرسانی هر منو/قالب/انتخاب برمی‌گردد؛ اجرای مستقیم آن روی سایت موجود نیز همین رفتار را دارد.
- اجرای مجدد پس از completed یا preserved، هیچ Write متعلق به Footer انجام نمی‌دهد؛ حتی اگر قالب کاملاً خالی یا حذف شده، منو تغییرنام یافته، آیتم حذف شده، Default جابه‌جا شده یا انتخاب مدیر خالی/حذف شده باشد، Preset بازسازی نمی‌شود.
- منوی Footer قدیمی دیگر در DatabaseSeeder با `firstOrCreate` ردیف‌به‌ردیف دوباره پر نمی‌شود. Seeder اختصاصی نیز `updateOrCreate` آیتم‌های منو و شرط «اگر blocks خالی بود پر کن» ندارد.
- هیچ v1 موجود به Composition تبدیل نمی‌شود. SiteFooterTemplateResolver، Default Resolution و انتخاب صریح قبلی تغییر نکرده‌اند. تبدیل Legacy همچنان تنها اقدام صریح Editor است.
- این ضمانت مربوط به Footer و منوهای Bootstrap آن است. سایر بخش‌های قدیمی DatabaseSeeder، از جمله Demo/Product و Header، خارج از این تغییر هستند؛ کل DatabaseSeeder به‌عنوان Upgrade عمومیِ بدون تغییر داده معرفی نمی‌شود.

## Publication Guard

هم Seeder و هم Create/Edit قالب منتشرشده از `TemplatePublicationValidator` موجود استفاده می‌کنند. شاخه Footer، بلوک‌های ساختاری `site_footer` را با Normalizer فعلی بررسی می‌کند؛ نسخه/فیلد/نوع ناشناخته، شناسه نامعتبر یا تکراری، Renderer مفقود و Form فعالِ هنوز خارج از محدوده، خطای انتشار می‌دهند. خواندن Legacy با Normalizer در حافظه، تبدیل یا Write روی سند ذخیره‌شده نیست.

برای جلوگیری از Regression، Footer عمداً خالی و Composition بدون عنصر معتبر از نظر ساختار رد نمی‌شوند؛ فقدان داده رسانه/تماس/لینک نیز با فساد Schema یکی نیست. قالب‌های قدیمی دارای بلوک‌های دیگر، همان رفتار انتشار قبلی آن بلوک‌ها را حفظ می‌کنند و چند بلوک Footer قدیمی خودکار حذف نمی‌شوند. Preset نصب تازه خودش دقیقاً یک site_footer دارد. Guard جدید بررسی وجود/انتشار همه Referenceها یا اثبات خروجی عمومی نیست؛ آن منابع همچنان با سیاست Runtime موجود Resolve و در حالت نامعتبر پنهان می‌شوند.

## نتیجه و موارد تأییدنشده

فقط `php -l` روی چهار فایل PHP تغییرکرده مجاز و اجرا شده است. تست خودکار، PHPUnit، Browser، Build، Pint، اجرای Seeder و Migration انجام نشده‌اند. نتیجه‌های تست فازهای قبلی، نتیجه آزمون این تغییر Seeder/Guard محسوب نمی‌شوند.

نصب واقعی از صفر، Resume نصب نیمه‌تمام، Re-seed پس از شخصی‌سازی/حذف، Default Resolution و ذخیره/انتشار Legacy پس از Guard جدید، تأیید اجرایی نشده‌اند. محدودیت مرورگر و تأیید بصری پنج عرضِ فاز ۴ نیز پابرجاست. پیاده‌سازی این قرارداد انجام شده است؛ پذیرش اجرایی کامل Product Requirement بدون این بررسی‌ها ادعا نمی‌شود.
