# King CMS — Authenticated Checkout & Account Integration

تاریخ: 2026-11-09

## نتیجه

Required Extension روی Public Account و Commerce فعلی پیاده شد. مهمان می‌تواند محصول انتخاب کند و سبد بسازد؛ ادامه خرید نیازمند client فعال و غیرمدیر است. مودال از endpointها و فیلدهای مشترک ورود/ثبت‌نام استفاده می‌کند. ورود، سفارش ایجاد نمی‌کند؛ کاربر به GET تازه Checkout برمی‌گردد و ثبت نهایی همچنان اقدام مستقل کاربر است.

Identity، guard، Order، جدول سفارش یا رابطه Customer جدیدی اضافه نشده است. `orders.user_id`، `CartService`، `CheckoutIdempotency`، `PublicOrderAccess`، `PaymentGateway` و `OrderPaymentService` موجود حفظ شده‌اند. migration جدید وجود ندارد و روی دیتابیس اصلی پروژه migration یا اصلاح داده اجرا نشده است. migrationهای تست صرفاً روی دیتابیس‌های آزمایشی ایزوله اجرا شدند.

## رفتار Backend و Frontend

- هر دو مسیر GET و POST `/checkout` با `auth:client` و `client` محافظت می‌شوند؛ guard مدیریتی `web` به‌تنهایی کافی نیست.
- middleware وضعیت و نقش فعلی حساب را دوباره از دیتابیس می‌خواند. مرز ایجاد سفارش نیز مالک فعال و غیرمدیر را الزام می‌کند و شناسه ارسالی کاربر را مصرف نمی‌کند.
- حالت مهمان، client غیرفعال و مدیر روی client guard نمی‌توانند Order یا PaymentAttempt بسازند.
- مودال یک مصرف‌کننده از Auth فعلی است؛ به Form Builder، Lead Submission یا Identity دیگری متصل نیست.
- فرم‌های صفحه‌ای و مودال فیلدهای یکسان دارند. endpointهای POST فعلی درخواست تکراری از یک tab احراز‌شده را بدون ایجاد کاربر یا تعویض Identity پاسخ می‌دهند؛ `guest:client` روی صفحات GET باقی است.
- مقصد ورودی فقط enum ثابت `continue=checkout` است. این مقدار روی سرور به route مشخص تبدیل می‌شود؛ `return_to` و `redirect_url` ورودی مصرف نمی‌شوند. intended URL نیز به مسیرهای حساب/پرتال/Checkout روی همان origin محدود می‌شود.
- موفقیت Auth، session را regenerate می‌کند و `shop_cart` را نگه می‌دارد. اشاره‌گر checkout intent و reviewهای نشست قبلی پاک می‌شوند؛ شواهد durable در دیتابیس حذف نمی‌شوند. سبد غیرخالی نیازمند بازبینی تازه می‌شود.
- GET تازه Checkout محصول و قیمت را از منبع معتبر بررسی می‌کند و review/intent متناسب با مالک فعلی می‌سازد. هیچ POST سفارش به‌طور خودکار replay نمی‌شود.
- نام، موبایل و ایمیل حساب prefill می‌شوند؛ مقدار `old()` اولویت دارد و اطلاعات گیرنده قابل ویرایش می‌مانند. تغییر اطلاعات تماس، مالکیت سفارش را عوض نمی‌کند.
- سفارش از همان مسیر موجود در `/account/orders` و صفحه جزئیات قابل مشاهده است. حساب مالک در جدول و جزئیات مدیریتی نمایش داده می‌شود؛ انتقال آزاد مالکیت اضافه نشده است.

### قرارداد پاسخ مودال

| پاسخ | قرارداد |
| --- | --- |
| موفقیت | HTTP 200 و JSON شامل `authenticated: true` و `redirect_url` معتبر؛ سپس navigation به GET Checkout |
| اعتبارسنجی/ورود ناموفق | HTTP 422 و `errors` استاندارد Laravel؛ خطای فارسی کنار فیلد و summary قابل focus |
| نشست/CSRF منقضی | HTTP 419، `code=session_expired`؛ ارسال مجدد خودکار انجام نمی‌شود و لینک صفحه ورود در دسترس است |
| محدودیت تلاش | HTTP 429، `code=rate_limited` و حفظ `Retry-After`؛ رابط تا پایان مهلت دوباره ارسال نمی‌کند |
| خطای ارتباط یا پاسخ نامعتبر | پیام عمومی، بدون تلقی redirect یا HTML 200 به‌عنوان ورود موفق |

درخواست‌های صفحه‌ای پاسخ redirect فعلی را حفظ می‌کنند. فرم‌ها CSRF، autocomplete مناسب password manager و امکان paste دارند. رمزها هنگام بستن یا تغییر حالت مودال پاک می‌شوند.

کنترلر overlay موجود برای Escape، Tab، Shift+Tab، inert پس‌زمینه، قفل اسکرول و بازگرداندن focus استفاده شده است. کنترل‌های مخفی و `tabindex=-1` از چرخه Tab حذف می‌شوند. هنگام حرکت از cart drawer به Auth، focus پس از بستن به trigger قابل مشاهده سبد برمی‌گردد. ارتفاع مودال با visual viewport، از جمله تغییر ارتفاع در حضور کیبورد موبایل، تنظیم می‌شود.

## امنیت رسید و پرداخت

- رسید سفارش دارای مالک همچنان امضای معتبر می‌خواهد و علاوه بر آن فقط برای همان client فعال و غیرمدیر باز می‌شود. Signed URL به‌تنهایی مجوز مشاهده اطلاعات خصوصی نیست.
- تمام سفارش‌های `user_id=null`، شامل سفارش مهمان قدیمی و سفارش مالک حذف‌شده، view محدود `shop.legacy-receipt` را می‌گیرند. snapshot، تلفن، ایمیل، نشانی، توضیحات، اقلام یا اطلاعات پرداخت به این view داده نمی‌شوند؛ مسیر پشتیبانی ارائه می‌شود.
- سفارش Legacy با تطبیق شماره/ایمیل یا Customer membership به حساب جدید منتقل نمی‌شود.
- retry/reconcile سفارش دارای مالک، مالک فعال را کنترل می‌کنند. عملیات Legacy علاوه بر signed URL به capability نشست اصلی `shop_order_receipts` نیاز دارند؛ لینک افشاشده به‌تنهایی کافی نیست. کاربر فاقد نشست اصلی باید از پشتیبانی پیگیری کند.
- پاسخ‌های خصوصی حساب و رسید `Cache-Control: no-store, private` دارند.
- `public_order_code` شناسه نمایشی باقی مانده و credential یا route key جدید نشده است.
- callback زرین‌پال پشت Auth قرار نگرفته و منطق شناسایی با Authority/ctx، verify، settlement و uncertain تغییر نکرده است. کنترل مالی و مجوز نمایش نتیجه دو مسئولیت جدا هستند.

## فایل‌های تغییرکرده

### Backend — جدید

- `app/Services/ClientAuthResponse.php`: continuation امن و transport مشترک پاسخ Auth.

### Backend — اصلاح‌شده

- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Middleware/EnsureClientUser.php`
- `app/Http/Controllers/Client/AuthenticatedSessionController.php`
- `app/Http/Controllers/Client/RegisteredUserController.php`
- `app/Http/Controllers/CheckoutController.php`
- `app/Services/OrderConfirmationUrl.php`
- `app/Filament/Resources/OrderResource.php`

### Frontend — جدید

- `resources/views/client/auth/partials/fields.blade.php`
- `resources/views/partials/checkout-auth-modal.blade.php`
- `resources/views/shop/legacy-receipt.blade.php`
- `resources/js/components/checkout-auth.js`
- `resources/css/components/checkout-auth.css`

### Frontend — اصلاح‌شده

- `resources/views/client/auth/login.blade.php`
- `resources/views/client/auth/register.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/shop/checkout.blade.php`
- `resources/views/shop/payment-callback.blade.php`
- `resources/js/components/header-overlays.js`
- `resources/js/app.js`
- `resources/css/app.css`

### تست‌ها — جدید

- `tests/Feature/AuthenticatedCheckoutTest.php`
- `tests/js/checkout-auth.test.js`

### تست‌ها — اصلاح‌شده

- `tests/Feature/ShopAccountIntegrationTest.php`
- `tests/Feature/CheckoutIdempotencyTest.php`
- `tests/Feature/CheckoutTransactionIntegrityTest.php`
- `tests/Feature/CommerceConcurrencyTest.php`
- `tests/Support/commerce-payment-worker.php`
- `tests/Feature/ShopTest.php`
- `tests/Feature/ZarinpalIntegrationTest.php`
- `tests/Feature/ZarinpalPaymentGatewayTest.php`
- `tests/Feature/ProductPriceFreshnessTest.php`
- `tests/Feature/ModuleToggleTest.php`
- `tests/Feature/PublicAccountServiceConvergenceTest.php`
- `tests/Feature/CustomerManagementFoundationTest.php`
- `tests/Feature/ClientServiceDeliveryPortalTest.php`

تست‌های مالی و قیمت‌گذاری با client معتبر اجرا می‌شوند؛ پوشش سناریوهای رد قیمت/اقلام، payload جعلی، rollback، replay، ارسال موازی و پرداخت حفظ شده است. fixture هم‌زمانی، migration موجود `public_order_code` را نیز اجرا می‌کند؛ این تغییر Schema جدیدی برای محصول نیست.

سه انتظار قدیمی UI پرتال با بارگذاری نسخه HEAD از `EnsureClientUser` نیز شکست می‌خوردند. سورس صفحات پرتال تغییر نکرد؛ assertionهای آن تست‌ها با داده‌های customer context، payment/timeline و عنوان فعلی خروجی‌ها هماهنگ شدند. کنترل‌های منفی عدم افشای مشتری و کارهای داخلی حفظ شده‌اند.

## اعتبارسنجی

| بررسی | نتیجه |
| --- | --- |
| مجموعه PHP مرتبط | 339 تست؛ 333 موفق، 6 skipped؛ 2146 assertion؛ بدون شکست یا خطا |
| تست‌های JavaScript Auth و overlay | 16 از 16 موفق |
| `npm run build` | موفق؛ هشدار اندازه chunk موجود Hero مانع build نیست |
| PHP syntax / Pint | موفق برای فایل‌های بررسی‌شده و تغییرکرده |
| `git diff --check` | موفق |
| Chrome headless با SQLite و session آزمایشی جدا | 16 بررسی موفق، با assetهای buildشده و درخواست واقعی مرورگر |

مجموعه PHP شامل Auth، ثبت‌نام، Public Account، Client Portal، Customer membership، Checkout، مالکیت، قیمت‌گذاری، Payment Foundation، زرین‌پال، Order Operations، هم‌زمانی SQLite، هدر و مدیریت کاربران است.

شش تست InnoDB به دلیل نبود instance محلی و صراحتاً ایزوله MySQL skip شدند. موفقیت harness موازی SQLite اثبات رفتار قفل و رقابت روی InnoDB نیست.

بررسی headless این مسیرها را پوشش داد:

1. انتخاب محصول مهمان و رسیدن به سبد.
2. نمایش مودال، focus موبایل، inert پس‌زمینه، Tab/Shift+Tab، Escape و بازگشت focus.
3. فرم ثبت‌نام RTL در viewport به عرض 375px، بدون سرریز افقی و با ارتفاع داخل viewport.
4. ورود ناموفق و focus روی summary خطا.
5. ثبت‌نام موفق، حفظ سبد، prefill و بازگشت به Checkout بدون ایجاد خودکار سفارش.
6. ثبت صریح سفارش با شماره گیرنده متفاوت و حفظ مالک حساب.
7. نمایش سفارش در تاریخچه حساب.
8. ورود صفحه‌ای و بازگشت به Checkout با JavaScript خاموش و intent تازه.

آثار محلی و ignored:

- `storage/framework/testing/authenticated-checkout-final.xml`
- `storage/framework/testing/authenticated-checkout-js.txt`
- `storage/framework/testing/checkout-browser-90f11f4bd8b8_testing/registration-mobile.png`

fixture مرورگر از دیتابیس SQLite تازه با نام `_testing`، session اختصاصی، mailer آرایه‌ای و سرور loopback موقت استفاده کرد. مرورگر headless و سرور موقت پس از بررسی بسته شدند؛ هیچ پرداخت واقعی انجام نشد.

## محدودیت‌ها و QA محیط مقصد

- callback و پرداخت آنلاین با HTTP fake تست شدند؛ merchant واقعی، تماس درگاه، پرداخت پول واقعی، callback عمومی و تحویل ایمیل در Production تأیید نشده‌اند.
- اجرای شش تست رقابت InnoDB نیازمند محیط MySQL ایزوله است.
- QA روی Safari/Firefox، دستگاه واقعی، کیبورد نرم‌افزاری و screen reader واقعی باقی است؛ Chrome headless جای این بررسی‌ها را نمی‌گیرد.
- هنگام استقرار باید assetهای buildشده و تنظیمات cookie/session/HTTPS محیط مقصد بررسی شوند. استقرار یا پاک‌سازی cache محیط مقصد در این کار انجام نشده است.
- سبد همچنان session-based است؛ بازیابی بعد از نابودی کامل نشست یا همگام‌سازی بین دستگاه‌ها اضافه نشده است.
- claim یا انتقال مالکیت سفارش Legacy عمداً اضافه نشده؛ مسیر فعلی آن پشتیبانی است.
