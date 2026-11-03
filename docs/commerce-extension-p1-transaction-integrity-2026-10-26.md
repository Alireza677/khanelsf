# King CMS — Commerce Extension — P1 Completion Report

تاریخ: ۲۰۲۶-۱۰-۲۶؛ محدوده: Transaction Integrity & Authoritative Pricing.

## A. Implementation Summary

P1 در همان Product، CartService، CheckoutController و Order/OrderItem موجود پیاده شد. Order دیگر از title، SKU، قیمت یا total ذخیره‌شده در Session ساخته نمی‌شود؛ اطلاعات کالا و قیمت از DB خوانده و در transaction ثبت سفارش دوباره اعتبارسنجی می‌شوند.

تغییرات اصلی:

- مرجع مشترک قیمت در ProductPricingService، همراه مسیر exact رشته‌ای برای تراکنش و حفظ APIهای float قدیمی برای نمایش Product/SEO؛
- کنترل انتشار، وجود و قابلیت خرید Product، تعداد نهایی، قیمت، حاصل‌ضرب و جمع کل قبل از ایجاد هر Order؛
- خواندن Productها به ترتیب ID با lockForUpdate داخل همان transaction ایجاد Order/اقلام؛ سه تلاش برای خطاهای قابل retry transaction؛
- بازبینی مبلغ تغییرکرده با token تصادفی و fingerprint نگهداری‌شده در Session سرور؛
- حفظ سبد، اطلاعات مشتری و خطای فارسی در حالت بازیابی؛
- محدودیت ۱ تا ۹۹ در add تجمعی، update و Checkout؛
- نمایش دقیق مبلغ در سبد، Checkout، drawer، تأیید سفارش و متن ایمیل بدون گردکردن نمایشی به تومان صحیح.

هیچ Migration، Schema، Model، Cart/Checkout/Order/Payment موازی، نوع محصول جدید، Buy Now، اتصال Service، setting ارز یا وضعیت مالی/انجام جدید اضافه نشده است. هیچ داده یا وضعیت پرداخت قدیمی بازنویسی نشده است. تغییرات قبلی Homepage، تنظیمات و گزارش منو در workspace حفظ شدند.

## B. Files Changed

فقط تغییرات همین P1 در این جدول آمده‌اند؛ فایل‌های دارای تغییر از مراحل قبلی، جزو P1 محسوب نشده‌اند.

| فایل | دلیل |
| --- | --- |
| `app/Services/ProductPricingService.php` | تعریف قیمت exact، قواعد sale، اعتبارسنجی DECIMAL، ضرب/جمع BCMath و formatter بدون rounding |
| `app/Models/Product.php` | اتصال currentPrice و hasSalePrice به همان سیاست مشترک؛ حفظ نوع خروجی نمایشی موجود |
| `app/Services/CartService.php` | مقدارهای دقیق رشته‌ای، اعتبارسنجی تعداد، بازخوانی Product، fingerprint بازبینی و نمایش امن اقلام نیازمند اصلاح |
| `app/Http/Controllers/CartController.php` | refresh سبد از DB، خطاهای قابل بازیابی، تعداد یکسان و نمایش مبلغ دقیق پاسخ حذف |
| `app/Http/Controllers/CheckoutController.php` | بازبینی GET، validation و lock در POST، snapshot از DB و حفظ اطلاعات مشتری؛ متن مبلغ ایمیل دقیق |
| `app/CMS/Blocks/SiteHeader/SiteHeaderRuntime.php` | حذف sum مبتنی بر float برای subtotal نمایشی drawer و استفاده از CartService |
| `resources/views/shop/cart.blade.php` | خطاهای فارسی، مبلغ دقیق، min=1 و جلوگیری از رفتن به Checkout برای سبد نامعتبر |
| `resources/views/shop/checkout.blade.php` | نمایش اقلام/مبالغ به‌روز و token بازبینی در فرم فعلی، بدون بازطراحی صفحه |
| `resources/views/partials/header/cart-drawer.blade.php` | نمایش اعشار معتبر بدون گردکردن مبلغ |
| `resources/views/shop/thank-you.blade.php` | نمایش دقیق مبلغ snapshot موجود؛ دسترسی signed و ownership تغییر نکرده‌اند |
| `tests/Feature/CheckoutTransactionIntegrityTest.php` | تست‌های مالی، تعداد، اعتبار Product، بازبینی، snapshot و مرز transaction |
| `tests/Feature/ShopTest.php` | تطبیق دو assertion با subtotal رشته‌ای exact؛ مرتب‌سازی import توسط Pint |
| همین گزارش | قرارداد و شواهد تکمیل P1 و موارد باقی‌مانده |

عدم تغییر Order، OrderItem، ProductAvailabilityService، PaymentGateway، ManualPaymentGateway، کلاس‌های زرین‌پال، callback و تمام migrationها با diff بررسی شد. رفتار availability موجود از همان ProductAvailabilityService مصرف می‌شود.

## C. Pricing Contract

### مرجع و دقت

- واحد این فاز همان IRT است. تبدیل ارز یا setting جدیدی اضافه نشده است.
- مبالغ authoritative به شکل رشته decimal با دو رقم اعشار نگهداری و محاسبه می‌شوند؛ مانند `0.10` و `1234.50`.
- دامنه برابر ظرفیت ستون‌های فعلی DECIMAL(12,2) است: از `0.00` تا `9999999999.99`، برای unit price، line total و جمع سفارش.
- عدد منفی، متن نامعتبر، scientific notation، بیش از دو رقم اعشار و مبلغ/حاصل/جمع خارج از ظرفیت رد می‌شود؛ هیچ rounding یا truncation برای جا دادن مبلغ در DB انجام نمی‌شود.
- ضرب با bcmul و جمع با bcadd، پس از اعتبارسنجی ورودی‌های دو اعشاری انجام می‌شود. scale=2 داده معتبر را گرد یا قطع نمی‌کند؛ حاصل ضرب تعداد صحیح و مبلغ دو اعشاری نیز دو اعشاری است.
- PDO SQLite یا سبدهای Legacy ممکن است scalar عددی برگردانند. scalar پیش از validation به متن تبدیل می‌شود؛ برای float، نمایش JSON حفظ‌کننده ارقام معنادار استفاده می‌شود تا تبدیل کوتاه معمول PHP، دقت اضافی را پنهان نکند. هیچ محاسبه پولی با float انجام نمی‌شود. non-finite نیز رد می‌شود.

BCMath در PHP محیط اجرای تست فعال بود. وجود همین extension در محیط استقرار باید بررسی شود؛ قبلاً نیز محاسبات عملیاتی بخش خدمات از BCMath استفاده می‌کردند. در این مرحله تنظیم PHP سرور تغییر نکرده است.

### قیمت ویژه و Legacy

1. قیمت اصلی باید مبلغ معتبر و قابل نمایش در DECIMAL فعلی باشد.
2. sale خالی/null به معنی نبود تخفیف است.
3. sale معتبر غیرمنفی و **کمتر از regular** قیمت مؤثر است؛ sale صفر برای regular مثبت پذیرفته می‌شود.
4. sale برابر یا بیشتر از regular، اگر از نظر مبلغ معتبر باشد، تخفیف محسوب نمی‌شود و regular استفاده می‌شود.
5. sale خراب، منفی، خارج از ظرفیت یا precision مجاز، باعث رد قیمت تراکنش می‌شود؛ silently round یا به تخفیف معتبر تبدیل نمی‌شود.

Product.currentPrice و ProductPricingService.effectivePrice اکنون همین انتخاب را دارند. خروجی float APIهای قدیمی فقط برای سازگاری context و SEO باقی مانده و در ساخت Order مصرف نمی‌شود. شکل context Product، Template Runtime و قرارداد SEO تغییر نکرده است.

### مثال‌ها

| ورودی | نتیجه |
| --- | --- |
| regular=10.25، sale=20.00 | unit=10.25 |
| regular=10.00، sale=0.00 | unit=0.00؛ سفارش همچنان unpaid و manual باقی می‌ماند |
| unit=0.10، quantity=3 | total=0.30 دقیق |
| line totals=0.30 و 0.40 | Order.total=0.70 |
| unit=5000000000.00، quantity=2 | رد به علت ظرفیت line total |
| دو قلم هرکدام total=5000000000.00 | رد به علت ظرفیت Order.total؛ هیچ Order/OrderItem جزئی ایجاد نمی‌شود |
| مبلغ 1.234 یا 10000000000.00 | رد صریح، بدون rounding |

CartService.items.unit_price/total و subtotal اکنون رشته‌های exact هستند. مقدار خام subtotal در JSON حذف سبد نیز رشته decimal است؛ کلیدها، count و subtotal_formatted حفظ شده‌اند. مصرف‌کننده JS موجود و تست حذف پوشش داده شدند؛ مصرف‌کننده سفارشی خارج از Repository که نوع عددی JSON را الزام کرده باشد، نیازمند تطبیق با این قرارداد دقیق است.

## D. Checkout Validation Flow

### نمایش و بازبینی

GET سبد و Checkout، Productها را دوباره می‌خوانند. GET Checkout اقلام و جمع دقیق را نمایش می‌دهد و یک token تصادفی ۴۰ کاراکتری می‌سازد. سرور حداکثر ۱۰ fingerprint اخیر را در همان Session ذخیره می‌کند؛ fingerprint شامل Product ID، quantity، unit price دقیق و IRT است. price یا total کلاینت داخل fingerprint trusted قرار نمی‌گیرد.

token فقط شناسه بازبینی سمت سرور است؛ تأیید مالی یا Payment idempotency key نیست. فرم قدیمی یک تب با refresh تب دیگر، بی‌صدا معتبر نمی‌شود. token نامعتبر/حذف‌شده نیز جای بازبینی معتبر را نمی‌گیرد.

### POST نهایی

1. همان اعتبارسنجی مشتری و بررسی module/gateway قبلی اجرا می‌شود؛ کلید بازبینی فقط برای کنترل فرم است و در Order mass assign نمی‌شود.
2. از سبد فقط Product ID و quantity خوانده می‌شود؛ ID تکراری/نامعتبر و quantity خراب رد می‌شوند.
3. داخل DB transaction، query منتشرشده Product با ترتیب ID و lockForUpdate اجرا می‌شود.
4. وجود همه اقلام، published_at و status با scope فعلی و flagهای قابلیت خرید با سرویس موجود بررسی می‌شوند.
5. unit price، line total و Order total با رشته/BCMath از همان Productهای خوانده‌شده ساخته و bounds بررسی می‌شوند.
6. fingerprint این اقلام با بازبینی سرور مقایسه می‌شود.
7. فقط پس از موفقیت همه اقلام، Order و OrderItemهای فعلی از title/SKU/ID/price/quantity/total معتبر ساخته می‌شوند.
8. commit انجام می‌شود؛ سپس سبد پاک و ایمیل‌های فعلی ارسال می‌شوند. ارسال ایمیل داخل قفل Product نیست.

Order.total برابر جمع همان totalهایی است که در اقلام ثبت می‌شوند. چون همه ورودی‌های ذخیره‌شده دو اعشاری و در ظرفیت DB هستند، به rounding ضمنی برای ساخت برابری متکی نیستیم. تغییر یا حذف Product بعداً snapshot را تغییر نمی‌دهد؛ nullable FK فعلی با حذف Product حفظ می‌شود.

### قیمت یا اقلام تغییرکرده

- POST با فرم قدیمی هیچ Orderی ایجاد نمی‌کند؛ به Checkout با خطای فارسی و old input نام/تلفن/ایمیل/آدرس/notes برمی‌گردد.
- GET بعدی اقلام و جمع جدید را برای بازبینی نمایش می‌دهد؛ نام و notes واردشده باقی می‌مانند.
- کاربر فرم جدید را دوباره submit می‌کند؛ در همان POST دوباره DB و قیمت بررسی می‌شوند. تغییر دوم قیمت نیز دوباره رد می‌شود.
- flag ارسالی مانند prices_accepted، قیمت/ارز/total ارسالی یا token تب قدیمی، جای محاسبه سرور را نمی‌گیرند.
- Product حذف‌شده، draft، زمان انتشار آینده یا unavailable، سفارش را متوقف می‌کند؛ GET Checkout کاربر را با خطای قابل فهم به سبد می‌برد. اقلام حفظ و قابل حذف‌اند؛ quantity نامعتبر را می‌توان اصلاح یا قلم را حذف کرد.

برای سازگاری با POSTهای مستقیم قبلی که GET Checkout نداشتند، فقط **پیش از صدور یا الزام هر بازبینی**، snapshot قیمت add سمت سرور می‌تواند مبنای مقایسه باشد؛ آن هم تنها وقتی با DB فعلی تطبیق کامل دارد. پس از اولین بازبینی یا تشخیص تغییر، حذف token این کنترل را دور نمی‌زند. هیچ مسیر، قیمت Session را به‌عنوان مبلغ نهایی Order مصرف نمی‌کند.

### Quantity

تعداد مجاز همان ۱ تا ۹۹ است. add تجمعی بیش از ۹۹ بدون تغییر Session رد می‌شود؛ update و POST Checkout نیز همین دامنه را کنترل می‌کنند. صفر در update اکنون صریحاً رد می‌شود، مطابق درخواست P1؛ حذف از endpoint/دکمه حذف موجود انجام می‌شود. عدد منفی، کسری، float، overflow، متن و array پذیرفته نمی‌شوند. هیچ موجودی عددی، reservation یا محدودیت service=1 اضافه نشده است.

## E. Backward Compatibility

رفتارهای زیر با سورس و تست‌های مرتبط بررسی شدند:

- محصولات فیزیکی و publication/availability فعلی، Guest و account-owned Checkout؛
- customer.user_id از guard معتبر سمت سرور، نه request؛
- signed confirmation و محدودیت صاحب سفارش؛
- همان ثبت manual/unpaid، همان پیام‌ها و درگاه placeholder؛
- همان فیلدهای snapshot و nullOnDelete مرجع Product؛
- Product context، Template Runtime، نمایش/SEO و Header drawer؛
- حذف سبد، AJAX remove، count/badge و subtotal_formatted؛
- Order administration و Order.markCompleted عمداً دست‌نخورده‌اند.

تفاوت‌های عمدی و محدود: یکسان‌شدن سیاست sale نامعتبر؛ مبلغ رشته‌ای exact در Cart؛ نمایش اعشار معتبر به‌جای گردکردن در صفحات تراکنش؛ رد quantity صفر؛ و اجبار بازبینی فرم قدیمی پس از تغییر مبلغ/اقلام. هیچ Order قدیمی backfill یا اصلاح داده نشده است. قالب‌های سفارشی و مصرف‌کنندگان خارج از repository در محیط واقعی بررسی نشده‌اند.

## F. Test Results

**تست‌های جدید P1: ۳۱ تست، ۲۸۷ assertion، همگی موفق.**

**اجرای نهایی مرتبط: ۱۱۶ تست، ۷۹۰ assertion؛ ۱۱۴ موفق، ۲ شکست موجود، بدون error یا skip.** این مجموعه کاملاً سبز نیست. تفکیک:

| فایل تست | تعداد تست | assertion | شکست |
| --- | ---: | ---: | ---: |
| CheckoutTransactionIntegrityTest | 31 | 287 | 0 |
| ShopTest | 19 | 83 | 1 |
| ProductDomainServicesTest | 5 | 39 | 0 |
| ProductFoundationDatabaseTest | 4 | 17 | 0 |
| ShopAccountIntegrationTest | 12 | 45 | 0 |
| ZarinpalPaymentGatewayTest | 5 | 15 | 0 |
| ProductTemplateRuntimeTest | 8 | 31 | 0 |
| ProductMediaSeoContractTest | 4 | 16 | 0 |
| OrderOperationsTest | 6 | 22 | 1 |
| SiteHeaderTemplateTest | 22 | 235 | 0 |

دو شکست:

1. `ShopTest::test_product_preview_is_auth_protected_and_noindexed`: انتظار redirect برای مهمان، پاسخ واقعی 404 مسیر ادمین.
2. `OrderOperationsTest::test_guest_cannot_access_printable_order_page`: همان اختلاف انتظار redirect با پاسخ 404 مسیر ادمین.

هر دو با نسخه اولیه شش کلاس PHP تغییرکرده P1 از `git show HEAD:<path>` و Composer classmap در پوشه موقت دوباره اجرا شدند: **۲ تست، ۲ assertion، همان ۲ شکست**. سورس workspace برای baseline بازنویسی نشد. قاعده موجود middleware برای مسیر ادمین، در P1 تغییر نکرده است؛ تست یا مجوزها برای سبزکردن این دو مورد تغییر نکردند.

پوشش تست‌های جدید: حذف/پیش‌نویس/انتشار آینده/غیرفعال‌شدن Product؛ قیمت و sale تغییرکرده؛ بازبینی دوباره و replay فرم تب قدیمی؛ حفظ customer input؛ قیمت و metadata تزریقی در HTTP و snapshot جعلی Session؛ مبلغ دقیق و جمع اقلام؛ sale نامعتبر Legacy؛ ورودی پولی خراب/منفی/precision اضافه/خارج از ظرفیت؛ تعداد صفر/منفی/کسری/متن/array/float/overflow؛ افزایش تجمعی؛ سقف DECIMAL و جمع خارج از آن؛ حفظ snapshot پس از تغییر/حذف Product و حضور read محصول در transaction ثبت سفارش.

دستور اجرای نهایی:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:DB_URL=''
$env:CACHE_STORE='array'
php -d memory_limit=512M vendor/bin/phpunit `
  tests/Feature/CheckoutTransactionIntegrityTest.php `
  tests/Feature/ShopTest.php `
  tests/Feature/ProductDomainServicesTest.php `
  tests/Feature/ProductFoundationDatabaseTest.php `
  tests/Feature/ShopAccountIntegrationTest.php `
  tests/Feature/ZarinpalPaymentGatewayTest.php `
  tests/Feature/ProductTemplateRuntimeTest.php `
  tests/Feature/ProductMediaSeoContractTest.php `
  tests/Feature/OrderOperationsTest.php `
  tests/Feature/SiteHeaderTemplateTest.php --no-progress
```

بررسی‌های کیفیت اجراشده: `php -l` هر هشت فایل PHP تغییرکرده، `pint --test` روی همان هشت فایل و `git diff --check` موفق بودند. فایل‌های تغییرنکردنی مالی و migrationها نیز diff نداشتند.

کل suite پروژه، مرورگر/E2E، build دارایی‌ها، موتور MySQL/InnoDB، تست همزمانی چند process، درگاه بانکی واقعی و Production اجرا/بررسی نشدند. JS/CSS تغییر نکرده و build جدیدی برای این اصلاح Blade/PHP انجام نشد. DB تست SQLite `:memory:` بود؛ HTTP واقعی در تست جدید منع و Mail fake شد. تست‌های Gateway موجود نیز مسیر placeholder/fake را بررسی می‌کنند؛ اثبات پرداخت بانکی نیستند.

## G. Remaining Risks

- **G01:** زرین‌پال همچنان placeholder است؛ شروع و verification واقعی پرداخت پیاده نشده‌اند. P1 پرداخت آنلاین را production-ready نمی‌کند.
- **G03:** PaymentAttempt/authority/reference/verification evidence پایدار و financial idempotency وجود ندارند؛ در P1 اضافه نشده‌اند.
- **G04:** markCompleted و ویرایش مالی ادمین هنوز می‌توانند وضعیت paid را دستی تغییر دهند؛ اینها proof بانکی نیستند و مطابق محدوده P1 تغییر نکردند.
- **G05:** correlation/replay protection پرداخت واقعی هنوز متعلق به P2/P3 است. callback فعلی stub و بدون مسیر موفقیت بانکی جدید باقی مانده است.
- **G16:** دو Checkout همزمان هنوز ممکن است دو Order بسازند. review token، جلوگیری از قیمت stale است و جای idempotency خرید/پرداخت یا قید یکتای آن را نمی‌گیرد.
- row locking روی MySQL/InnoDB و تداخل واقعی admin update با Checkout در موتور Production اجرا نشده است. SQLite transaction boundary را بررسی می‌کند، نه قفل ردیفی MySQL را.
- APIهای float قدیمی Product برای presentation حفظ شده‌اند؛ محاسبه مالی از آنها استفاده نمی‌کند. فرمت‌های catalog قدیمی که عدد صحیح نمایش می‌دادند، در این P1 بازطراحی نشده‌اند؛ Checkout مبلغ دقیق مورد تأیید را نشان می‌دهد.
- پاک‌شدن Session یا خروج token از ۱۰ بازبینی اخیر، به بازبینی مجدد منجر می‌شود؛ مسیر bypass مبلغ ندارد.
- این تغییر malformed data قبل از ورود به DB در تمام مسیرهای import/admin را بازنویسی نمی‌کند؛ مبلغ مشاهده‌شده در مرز تراکنش رد/اعتبارسنجی می‌شود. رفتار DECIMAL و قفل موتور استقرار باید جدا تأیید شود.

## H. Phase P2 Readiness

مسیر فعلی، مرز مشخصی برای دریافت Productهای معتبر، قیمت دقیق، بازبینی مشتری و commit snapshot سفارش دارد و برای افزودن سابقه تلاش پرداخت و انتقال‌های مالی صحیح در P2 آماده‌تر است. Order pending و مبلغ معتبر می‌توانند ورودی درگاه آینده باشند؛ قیمت سبد/کارت نمایشی یا Client دیگر مرجع آن نیست.

بااین‌حال، عرضه پرداخت واقعی هنوز مجاز/تأییدشده نیست. P2 باید وضعیت مالی، تأیید دستی audited، persistence تلاش پرداخت، correlation و idempotency را روی همین Order تکمیل کند؛ P3 verification رسمی درگاه را اضافه کند. هیچ‌یک در این مرحله آغاز نشده‌اند و هیچ عملکردی در Production تأیید نشده است.
