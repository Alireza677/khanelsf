# King CMS — Product Standard Template

تاریخ: ۲۰۲۶-۱۱-۱۰. پیاده‌سازی بر اساس موکاپ پیوست و Audit قبلی Product Detail؛ محدوده محصول ساده.

## A. Implementation Summary

قالب `product-standard-v1` برای Product Template Runtime موجود ساخته شد: گالری سمت راست، اطلاعات و خرید سمت چپ در Desktop؛ گالری بالای خرید در Mobile. Breadcrumb سلسله‌مراتبی، برند، SKU، ویژگی‌های برجسته انتخابی، قیمت و Freshness، تعداد صحیح، Cart، علاقه‌مندی Session و Share اضافه شدند. اطلاعات تفصیلی در تب‌های دارای محتوا، محصولات مرتبط تصویری، مزیت‌های اختیاری و CTA اختیاری ارائه می‌شوند.

Header و Footer همان Layout موجود King هستند. از لوگو، تصاویر محصول و متن‌های تجاری نمونه موکاپ استفاده نشده است. رنگ آبی ثابتی برای محصول تعریف نشده؛ رنگ‌ها و Typography از Theme ارث می‌برند. استایل‌های محصول به کلاس‌های جدید و wrapper قالب استاندارد محدودند. Sticky Purchase Bar اضافه نشد.

## B. Files Changed

فایل‌های جدید:

- `app/CMS/Blocks/Product/ProductSummaryBlock.php`
- `app/CMS/Blocks/Product/ProductDetailsBlock.php`
- `app/CMS/Templates/Recipes/ProductStandardV1Recipe.php`
- `database/seeders/StandardProductTemplateSeeder.php`
- `resources/views/partials/blocks/product_summary.blade.php`
- `resources/views/partials/blocks/product_details.blade.php`
- `resources/views/shop/partials/related-compact.blade.php`
- `resources/css/components/product-standard.css`
- `resources/js/components/product-standard.js`
- `resources/js/components/gallery-lightbox.js`
- `tests/Feature/ProductStandardTemplateTest.php`
- `tests/Feature/StandardProductTemplateBootstrapTest.php`
- `tests/js/product-standard.test.mjs`
- همین سند.

فایل‌های موجود ویرایش‌شده:

- `app/CMS/Blocks/Product/ProductRelatedBlock.php`: حالت opt-in با تنظیم `settings.variant=compact_visual`؛ نبود آن، نمایش قبلی را حفظ می‌کند.
- `app/Providers/BlockServiceProvider.php` و `TemplateRecipeServiceProvider.php`: ثبت بلوک‌ها و Recipe.
- `app/Services/ProductTemplateContextBuilder.php`: داده‌های نمایشی افزایشی و eager loading.
- `app/Services/ProductMediaService.php`: کلید افزایشی `alt` از metadata رسانه.
- `database/seeders/DatabaseSeeder.php`: ثبت intent مستقل Product در همان بررسی اولیه دیتابیس واقعاً خالی و فراخوانی Seeder محافظت‌شده.
- `resources/views/templates/render.blade.php`: wrapper و context flag فقط برای Product Standard.
- `resources/views/partials/blocks/feature_grid.blade.php`: حذف خروجی خالی فقط در قالب استاندارد محصول.
- `resources/views/partials/blocks/cta.blade.php`: الزام محتوا و مقصد resolveشده فقط در قالب استاندارد محصول.
- `resources/views/partials/blocks/product_related.blade.php`: dispatch به نمایش جدید فقط با انتخاب صریح variant.
- `resources/views/shop/partials/price.blade.php`: ارقام فارسی به‌صورت opt-in؛ قرارداد و محاسبه قیمت حفظ شده است.
- `resources/css/app.css` و `resources/js/app.js`: اتصال assetها؛ Lightbox موجود به ماژول مشترک منتقل و تکمیل شد.
- `tests/Feature/ProductDomainServicesTest.php`: همسوسازی انتظار کلیدهای Context با توسعه افزایشی.

## C. New Blocks & Components

`product_summary` و `product_details` از `AbstractProductBlock` ارث می‌برند؛ Schema نسخه ۱، `block_id` پایدار، `content/settings`، Normalizer، Registry و سازگاری فقط با `product_single` دارند. ذخیره از `BlockEditorSaveManager` فعلی عبور می‌کند؛ مسیر Persistence یا Nested Builder تازه‌ای ساخته نشده است.

تنظیمات Summary: نمایش Share، نمایش Favorites و فهرست صریح Attributeهای برجسته. عنوان اصلی محصول H1 است. تنظیم Details: تب ترجیحی اولیه و Rich Text اختیاری راهنمای خرید. نبود محتوای تب ترجیحی، اولین تب موجود را فعال می‌کند.

Variant مرتبط‌ها در `settings.variant` ذخیره می‌شود؛ قرارداد renderer قدیمی `default` و defaults قبلی حفظ شده‌اند. Recipe صنعتی بدون تغییر مانده است.

## D. Product Context

همه کلیدهای قبلی حفظ شدند. کلیدهای افزوده‌شده:

| کلید | رفتار |
|---|---|
| `breadcrumbs` | خانه، فروشگاه، والدهای قابل نمایش و محصول؛ از سیاست `ShopCategoryTree` استفاده می‌کند |
| `displayCategory` | فقط دسته‌ای که در درخت عمومی قابل نمایش است |
| `brand` | نام برند فعال؛ بدون لینک یا برند فرضی |
| `globalAttributes` | Assignmentهای دارای Attribute و Value فعال، با مقادیر مرتب و واحد نمایشی |
| `galleryPresentation` | تصویر شاخص سپس گالری، با ترتیب واقعی و حذف URL تکراری |
| `isFavorite` | وضعیت همان Session مرورگر؛ بدون نوشتن Session |
| `relatedCards` | تصویر و URL و Freshness آماده برای کارت مرتبط |

روابط برند و Attribute/Value در Builder eager-load می‌شوند. وضعیت غیرفعال فقط از ارائه جدید حذف می‌شود؛ داده و انتساب تاریخی پاک نمی‌شود. `is_filterable` شرط نمایش اطلاعات محصول نیست. تصویر رسانه، alt اختصاصی و عنوان واقعی آن مصرف می‌شوند.

## E. Gallery & Purchase UX

گالری شامل تصویر اصلی با `object-fit: contain`، Thumbnail فعال، انتخاب تصویر، لینک اصلی قابل استفاده بدون JS، Lightbox و پیام شکست تصویر است. محصول فاقد تصویر Placeholder خنثی دارد و عنوان در Placeholder تکرار نمی‌شود.

Lightbox همان trigger مشترک `data-gallery-lightbox-src` را نگه می‌دارد. گروه تصاویر محصول، قبلی/بعدی، Keyboard، Escape، Focus trap، inert کردن پس‌زمینه و بازگرداندن Focus اضافه شده‌اند. triggerهای تک‌تصویر Service و Project حفظ شده‌اند. این قابلیت Zoom اپتیکی یا Browser Fullscreen API نیست.

قیمت از partial و Pricing فعلی می‌آید؛ فقط نمایش ارقام فارسی opt-in است. موجودی از قابلیت خرید و Freshness جداست. حالت منقضی، تأییدنشده یا ناموجود مطابق قرارداد فعلی خرید را محدود می‌کند و استعلام تنها از مقصد واقعی موجود می‌آید. در کنترل Freshness فعال، قیمت صفر مطابق سرویس فعلی `missing` است؛ این رفتار مالی تغییر داده نشده است. Sale صفر در حالت مجاز فعلی Pricing/Cart حفظ شده است.

تعداد، عدد صحیح ۱ تا ۹۹ است؛ کنترل‌های +/-، اعتبارسنجی HTML و سرور، POST و CSRF فعلی حفظ شده‌اند. بدون JS، input و submit اصلی کار می‌کنند. واحد متر/کیلو/بسته اضافه نشده است.

Favorites از Route فعلی و Session استفاده می‌کند و Preview فرم تغییر علاقه‌مندی یا خرید ندارد. Share از مرورگر، سپس Clipboard و در صورت خطا از ورودی انتخاب‌پذیر URL استفاده می‌کند؛ لغو Share خطا محسوب نمی‌شود.

## F. Product Details

تب‌های دارای محتوای واقعی: توضیحات، Specifications، Global Attributes، اسناد و راهنمای خرید. Rendererهای موجود Overview، Specifications و Documents بازاستفاده شده‌اند. داده Specification و Attribute در دیتابیس و Context مستقل باقی مانده است.

تمام پنل‌ها در HTML اولیه موجود و بدون JS قابل مشاهده‌اند. JS به‌صورت progressive، نقش‌های tablist/tab/tabpanel، `aria-selected/controls/labelledby`، roving tabindex و کلیدهای جهت متناسب با RTL، Home و End را اضافه می‌کند. تب‌ها روی موبایل در نوار داخلی پیمایش می‌شوند. محتوای بلند و مشخصات در عرض کوچک بازچینی می‌شوند. هیچ تب نظر یا Rating ساخته نشده است.

## G. Recipe & Default Template

Recipe `product-standard-v1` شامل Summary، Feature Grid خالی قابل تنظیم، Details، Related با `compact_visual` و CTA خالی قابل تنظیم است. عنوان خنثی «مزیت‌های خرید» برای اعتبارسنجی Editor وجود دارد؛ تا زمانی که مدیر آیتمی وارد نکند کل بخش نمایش داده نمی‌شود. CTA نیز بدون متن و مقصد معتبر نمایش داده نمی‌شود.

`DatabaseSeeder` فقط هنگامی که همه جدول‌های غیر Migration واقعاً خالی‌اند، در کنار intent فعلی Footer، کلید مستقل `king_product_bootstrap=pending` ثبت می‌کند. `StandardProductTemplateSeeder` فقط همین intent را مصرف می‌کند. ساخت قالب با Publication Validator و تکمیل intent در یک transaction انجام می‌شود.

وجود هر قالب `product_single` یا اشغال slug پیشنهادی، حتی در نصب نیمه‌کاره، باعث حفظ انتخاب قبلی و وضعیت `preserved` می‌شود. پس از `completed`، حذف Template نیز سبب نصب مجدد خودکار نمی‌شود. نبود Template یا Default در سایت موجود، مجوز نصب تازه نیست. Migration جدید وجود ندارد.

## H. Compatibility

Product Entity، Specifications، Global Attributes، Pricing، Freshness، Cart، Checkout، Order Snapshot، Media Library، Runtime، Template Resolution، Recipe صنعتی و SEO تغییر تجاری نکرده‌اند. Template فعلی سایت ساخته، منتشر یا پیش‌فرض نشده است.

مصرف‌کنندگان مشترک تحت تأثیر محدود: Lightbox Service/Project از نسخه تکمیل‌شده استفاده می‌کنند؛ partial قیمت گزینه اختیاری ارقام فارسی دارد؛ خروجی Feature Grid و CTA فقط با context قالب استاندارد محصول محدود می‌شود. سایر قالب‌ها رفتار پیشین خود را حفظ می‌کنند.

## I. Tests & Verification

| بررسی | نتیجه واقعی |
|---|---|
| اجرای نهایی Product Standard، Bootstrap و Recipe Infrastructure | **۳۱ تست، ۲۳۰ assertion؛ همگی پاس** |
| اجرای گسترده در ۲۶ فایل Product/Commerce/CTA/Feature Grid/Footer/Recipe | **۲۷۱ تست، ۲۰۲۰ assertion؛ ۲۶۴ پاس، ۴ Error و ۳ Failure** |
| بازتولید شکست‌های گسترده با نسخه اصلی فایل‌های درگیر در `HEAD` | هر هفت مورد زیر بازتولید شدند؛ شکست تازه‌ای از فاز محصول در اجرای گسترده باقی نماند |
| تست JavaScript محصول و Feature Grid | **۷ تست پاس**؛ Event/State با DOM double |
| Build نهایی Vite | موفق؛ هشدار chunk موجود `hero-dotted-surface` بیش از ۵۰۰KB باقی است |
| PHP lint | ۱۴ فایل PHP موفق |
| Pint و `git diff --check` | موفق |

اعداد اجرای نهایی و گسترده هم‌پوشانی دارند و نباید جمع شوند. اجرای گسترده پیش از افزودن آخرین تست محتوای Rich Text خالی بود؛ آن تغییر در اجرای نهایی ۳۱ تستی پوشش داده شد. مجموعه کامل مخزن اجرا یا سبز اعلام نشده است.

شکست‌های مبنا:

| تست | علت مشاهده‌شده |
|---|---|
| `SiteFooterTemplateTest::test_draft_preview_seed_and_scoped_responsive_contract_are_available` | نبود Template مورد انتظار در Fixture اجرای مستقیم Seeder؛ intent نصب تازه ندارد |
| `SiteFooterSchemaCompatibilityTest::test_v1_and_normalized_data_keep_the_same_runtime_and_html_without_writing_the_template` | رد مرجع رسانه مفقود توسط Guard موجود |
| `SiteFooterSchemaCompatibilityTest::test_composition_controls_preserve_identity_order_settings_and_out_of_scope_data_after_save_reload` | همان اعتبارسنجی رسانه Fixture |
| `SiteFooterSchemaCompatibilityTest::test_unknown_version_and_element_fail_save_without_changing_the_stored_document` | همان اعتبارسنجی رسانه Fixture |
| `FeatureGridActionMigrationTest::test_item_rich_text_and_legacy_text_use_shared_safe_renderer` | انتظار نبود هر `<script` با Script موجود full-bleed همخوان نیست |
| `CTAV2Test::test_image_cta_uses_a_viewport_surface_with_a_bounded_inner_container` | Regex قدیمی با شرط `full_width_content` در Layout فعلی همخوان نیست |
| `CTAV2Test::test_modal_form_action_is_lazy_and_fragment_contains_cta_attribution` | انتظار `name="email"` در HTML Fixture فعلی برقرار نیست |

برای بازتولید مبنا، نسخه اصلی کلاس‌ها و viewهای تغییرکرده مرتبط از `git show HEAD:...` در پوشه موقت بارگذاری شد و View Finder همان Snapshot را مقدم کرد؛ فایل‌های کاری overwrite نشدند. هفت تست حذف یا Skip دائمی نشدند و رفتار فوتر/فرم برای سبزکردن آن‌ها تغییر نکرد.

لاگ‌ها: `storage/logs/product-standard-regression.log/.xml`، `product-standard-baseline.log/.xml`، `product-standard-cta-baseline.log/.xml` و `product-standard-final-focused.xml`. دو انتظار دقیق قدیمی درباره کلیدهای Context و فهرست Recipe با توسعه‌های صریح جدید همسو شدند.

آزمون‌های جدید شامل نبود تصویر و داده تکمیلی، Shared Media عمودی/افقی و حذف تکرار، برند/ویژگی غیرفعال، مسیر دسته پنهان، راهنمای اختیاری، HTML اولیه همه تب‌ها، قیمت و Sale صفر طبق قرارداد موجود، Freshness، استعلام واقعی، Cart و Snapshot سفارش، Preview بدون خرید/Favorites، ذخیره Editor، حفظ قالب صنعتی، Bootstrap خالی و اجرای مجدد و نصب نیمه‌کاره هستند. Render سه بلوک جدید/opt-in با Freshness فعال هیچ Query اضافه‌ای نداشت؛ شمار Queryهای غیر Settings کارت‌های مرتبط در Fixture دو و شش محصول دارای رسانه مشترک ثابت ماند.

تمام آزمون‌های PHP از PHPUnit و SQLite حافظه‌ای با Guard ایمنی مخزن استفاده می‌کنند؛ Seederها فقط در Fixture تست اجرا شدند، نه دیتابیس سایت. Mail fake و جلوگیری از درخواست HTTP واقعی در سناریوی سفارش فعال است.

ابزار مرورگر، فهرست خالی apps/browsers برگرداند. بنابراین بررسی دیداری ۳۲۰، ۳۷۵، ۷۶۸، ۱۰۲۴ و ۱۴۴۰، اندازه‌گیری کنتراست Theme واقعی و تعامل روی دستگاه واقعی تأیید نشده است. تست‌های DOM با double، رفتار Event/State را بررسی می‌کنند و جایگزین مرورگر نیستند. Production، Migration مقصد یا Provider پرداخت بررسی نشده‌اند.

## J. Pending Features

Rating/Reviews، Compare، پیش‌فاکتور، واحد فروش، فروش اعشاری، Variant، Bundle، موجودی عددی و محاسبه متراژ ساخته نشده‌اند. مزیت تجاری، برند، ویژگی، تصویر یا مقصد CTA حدس زده نشده است. Zoom واقعی/Browser Fullscreen و نوار خرید ثابت افزوده نشده‌اند.

پذیرش بصری و دستگاهی قالب همچنان لازم است. داده‌های واقعی برند، ویژگی، اسناد، گالری، مزیت‌ها و CTA باید توسط مدیر همان سایت وارد شوند؛ خالی‌بودن داده با دمو یا Backfill حدسی پوشانده نشده است.

## K. Git Status

شروع کار: Git پاک. پایان: ۱۵ فایل tracked ویرایش‌شده و ۱۴ فایل جدید؛ بدون Stage و Commit. خروجی‌های Build و لاگ‌های آزمون مطابق ignoreهای موجود در این شمارش نیستند. هیچ Migration/Seeder روی دیتابیس سایت اجرا نشده و Default سایت موجود تغییر نکرده است.

## L. Activation Steps

برای **سایت موجود** از `db:seed` برای فعال‌سازی این قالب استفاده نکنید؛ مسیر مدیریت زیر کافی است:

1. کد و Build جدید را با روند استقرار فعلی در محیط بررسی قرار دهید؛ Schema کاتالوگ فاز اول باید از قبل مطابق نسخه کد باشد. این فاز Migration ندارد.
2. در «طراحی ← قالب‌ها»، عملیات ساخت از Recipe را انتخاب و `product-standard-v1` را انتخاب کنید.
3. برای پذیرش اولیه، یک محصول نمونه را به شرط `specific_item` متصل کنید؛ قالب همچنان Draft بماند. تیک Default عمومی را برای این بررسی فعال نکنید.
4. از Preview همان Template، محصول واقعی را انتخاب و تصویر، قیمت/Freshness، تعداد، Keyboard، Mobile، تب‌ها و موارد فاقد داده را بررسی کنید.
5. در Summary فقط ویژگی‌های برجسته واقعی را انتخاب کنید. Feature Grid و CTA خالی می‌توانند باقی بمانند؛ نمایش ندارند. برای CTA مقصد معتبر از ActionPicker انتخاب کنید.
6. پس از پذیرش، همان Template اختصاصی را آگاهانه منتشر کنید. قالب‌های دیگر را بازنویسی نکنید.
7. اگر پس از بررسی تصمیم به عرضه عمومی گرفتید، شرایط را به `all` و Default را با تصمیم مدیر تنظیم کنید؛ تعارض اولویت با defaultهای دیگر را در Debug قالب بررسی کنید. Template اختصاصی محصول/دسته همچنان مقدم است.
8. مسیر عمومی، POST سبد، علاقه‌مندی و استعلام را در محیط مقصد بررسی کنید. Preview به‌تنهایی شاهد موفقیت این عملیات عمومی نیست.

برای نصب واقعاً تازه، مسیر استاندارد نصب و `DatabaseSeeder`، قالب Product Standard منتشرشده و Default را ایجاد می‌کند. هیچ اقدام فعال‌سازی روی نصب فعلی در این نشست انجام نشده است.
