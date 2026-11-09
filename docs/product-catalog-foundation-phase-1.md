# King CMS — Universal Product Catalog Foundation — Phase 1

تاریخ: ۲۰۲۶-۱۱-۰۹. این فاز فقط مدیریت اطلاعات کاتالوگ را توسعه می‌دهد؛ موتور فیلتر و ارائه عمومی ویژگی‌ها وارد این تغییر نشده‌اند.

## ساختار و قرارداد

- ProductCategory همان موجودیت قبلی است؛ `parent_id` اختیاری و روابط `parent/children` اضافه شده‌اند. هر Product همچنان فقط یک `product_category_id` دارد. URL، انتشار، SEO و اعتبار قیمت قبلی تغییر نمی‌کنند.
- تغییر والد در ذخیره عادی مدل، داخل تراکنش و با قفل مرتب دسته‌ها بررسی می‌شود تا چرخه، از جمله تغییر هم‌زمان دو والد، به اعتبارسنجی صرفاً رابط وابسته نباشد. این قفل فقط برای تغییر صریح والد اجرا می‌شود. هم‌زمانی واقعی MySQL در این فاز آزمایش نشده است.
- ProductBrand دارای نام، نامک، توضیح، وضعیت، ترتیب و نشان اختیاری است. نشان از همان Media Library و `media_usages` با collection موجود `featured_image` استفاده می‌کند؛ مصرف‌کننده در MediaConsumerRegistry ثبت شده است. برند هیچ فرضی درباره تولیدکننده یا فروشنده ندارد.
- ProductTag نام و نامک یکتا دارد؛ رابطه چندبه‌چند با کلید مرکب از تکرار اتصال جلوگیری می‌کند. حذف برچسب فقط رابطه را حذف می‌کند؛ حذف برند FK محصول را null می‌کند.
- ProductAttribute تعریف مشترک با شناسه پایدار، نام، وضعیت، ترتیب، واحد اختیاری، `is_multiple` و `is_filterable` است. `data_type=choice` قرارداد فعلی انتخاب از مقادیر کنترل‌شده است؛ نوع‌های آینده باید صریح اضافه شوند و نباید متن‌های قدیمی را خودکار به عدد یا نوع دیگری تبدیل کنند.
- ProductAttributeValue به یک ویژگی تعلق دارد. `name_key` داخلی برای یکتایی نام پس از عادی‌سازی حروف فارسی/عربی، فاصله، بزرگی حروف و ارقام است؛ `۰٫۵` و `0.5` یک مقدار محسوب می‌شوند. مقدار choice به‌طور حدسی عدد تفسیر نمی‌شود؛ کدهای `001` و `1` مستقل می‌مانند و عنوان نمایشی بازنویسی معنایی نمی‌شود. نامک و نام استاندارد در محدوده همان ویژگی یکتا هستند.
- ProductAttributeAssignment برای هر مقدار انتخاب‌شده یک ردیف دارد؛ شامل Product، Attribute، Value و ترتیب است. قید مرکب DB تعلق Value به Attribute و قید Unique عدم تکرار اتصال را تضمین می‌کند. Index معکوس برای موتور فیلتر آینده وجود دارد.
- نوشتن انتساب‌ها از `ProductCatalogService::syncAttributes()` انجام می‌شود: ابتدا کل انتخاب بررسی، سپس تغییرات داخل تراکنش اعمال می‌شوند. مدل Assignment نیز در ذخیره عادی تعلق مقدار، فعال‌بودن انتخاب تازه و تک/چندمقداری را کنترل می‌کند. SQL مستقیم و bulk update مسیر مجاز اعتبارسنجی دامنه نیستند.
- حذف Attribute/Value استفاده‌شده مسدود است؛ غیرفعال‌سازی ارتباط‌های قبلی را نگه می‌دارد، اما انتخاب تازه آن‌ها مجاز نیست. حذف انتخاب توسط مدیر مجاز است. تبدیل چندمقداری به تک‌مقداری تا وجود محصول چندمقداری رد می‌شود. تغییر واحد ویژگی استفاده‌شده و تغییر شناسه ویژگی نیز رد می‌شود.
- اتصال Attribute–Category صرفاً پیشنهاد ورود اطلاعات است. همه ویژگی‌ها سراسری‌اند و انتخاب سایر ویژگی‌های فعال مجاز است. پیشنهاد دسته مستقیم است؛ ارث‌بری خودکار از والد ایجاد نشده است. تغییر دسته محصول هیچ انتسابی را حذف نمی‌کند.

## مدیریت

در گروه «خدمات و فروش»، منابع برند، برچسب و ویژگی اضافه شده‌اند. مقادیر هر ویژگی از جدول رابطه در صفحه ویرایش همان ویژگی مدیریت می‌شوند. دسترسی منابع جدید به مدیر فعال و فروشگاه فعال محدود است.

ProductResource تب‌ها و Specifications قبلی را نگه می‌دارد. انتخاب برند/برچسب به محتوا و تب «ویژگی‌های عمومی» به فرم اضافه شده است. انتخاب وابسته ویژگی/مقدار، راهنمای تک/چندانتخاب، نشان‌دادن مقادیر قبلی غیرفعال، نامک در بخش پیشرفته و ستون‌بندی تک‌ستونی موبایل/دوستونی دسکتاپ بر اساس اسکیل ui-ux-pro-max و اجزای Filament پیاده شده‌اند. هیچ CSS یا JS جدیدی لازم نشده است.

ذخیره فرم Create/Edit محصول تراکنشی است تا خطای انتساب ویژگی باعث ذخیره جزئی محصول، قیمت یا روابط فرم نشود. فیلد مفقود در فراخواننده قدیمی به معنی پاک‌کردن انتساب‌ها نیست؛ آرایه خالی صریح، حذف انتخاب‌های قبلی است.

## Migration و استقرار

Migration جدید: `database/migrations/2026_11_09_000001_add_universal_product_catalog.php`.

تغییرها: دو ستون nullable (`product_categories.parent_id` و `products.product_brand_id`) و هفت جدول جدید: `product_brands`، `product_tags`، `product_product_tag`، `product_attributes`، `product_attribute_values`، `product_attribute_category`، `product_attribute_assignments`.

هیچ داده، برند، ویژگی، دسته یا مقدار حدسی Backfill نمی‌شود. هیچ Seeder جدیدی وجود ندارد. Migration روی دیتابیس برنامه اجرا نشده است؛ اجرای آن فقط در تست SQLite حافظه‌ای انجام شده است. پیش از استفاده از پنل جدید باید Migration با روال استقرار پروژه روی محیط مقصد اعمال شود. سازگاری DDL، قفل‌ها و هم‌زمانی روی MySQL/MariaDB مقصد هنوز نیازمند پذیرش است.

## سازگاری و موارد بعدی

- کد Cart، Checkout، OrderItem، Pricing، PriceFreshness، Template Runtime، Blocks، Recipe، Preview، SEO و Sitemap تغییر نکرده است. اعتبار قیمت همچنان از محصول، دسته مستقیم و سپس فروشگاه خوانده می‌شود؛ والد دسته در این محاسبه وارد نشده است.
- ProductSpecification مستقل باقی می‌ماند. هیچ مشخصه‌ای به Attribute منتقل یا حذف نمی‌شود.
- Import فعلی همان Headerها و Preview/Confirmation را دارد. آزمون به‌روزرسانی موجود، حفظ برند، برچسب و انتساب ویژگی را نیز بررسی می‌کند. Import این روابط را نمی‌نویسد؛ افزودن Headerها یا شیت‌های جدید در آینده باید در همان Mapper/Template/Preview/تصمیم تعارض پیاده شود، با حفظ blankها و اجرای مجدد امن. Import موازی ایجاد نشده است.
- Fingerprint فعلی Import شامل تمام ستون‌های Product است. پیش‌نمایش به‌روزرسانی ثبت‌شده پیش از این Migration ممکن است پس از اضافه‌شدن ستون برند، تعارض اعلام کند؛ پیش از تأیید اجرا باید از مسیر بررسی مجدد/پیش‌نمایش تازه همان Import استفاده شود. Hash قبلی بازنویسی یا تعارض به‌طور خودکار دور زده نشده است.
- صفحات عمومی برند/برچسب و SEO آن‌ها اضافه نشده‌اند. چون برند در این فاز ارائه عمومی ندارد، Metadata اختصاصی SEO برند نیز به Schema اضافه نشده است.
- فاز Filter Query Engine باید AND بین ویژگی‌ها، OR بین مقادیر همان ویژگی، وضعیت فعال، `is_filterable`، قیمت مؤثر، شمارش دقیق، Pagination پایدار و سیاست URL/SEO را به Query موجود اضافه کند. Attributes و Media موردنیاز باید گروهی بارگذاری شوند.
- هیچ Variant، موجودی عددی، واحد فروش، فروش اعشاری، Bundle یا قیمت‌گذاری تازه‌ای در این فاز ایجاد نشده است.

## شواهد آزمون

مجموعه ۱۸ فایل مرتبط: **۲۱۰ تست و ۲۲۷۳ assertion موفق**؛ شامل تست‌های جدید دامنه، فرم و خرید، Import قبلی (با بررسی حفظ روابط تازه)، Price Freshness، جدول مدیریت، Product Runtime/Recipe/Preview/Blocks، Media، صحت تراکنش، Checkout Idempotency، Authenticated Checkout و Payment Foundation. مجموعه MediaDependencyManagement نیز **۲۲ تست و ۱۳۳ assertion موفق** داشت. پس از بازبینی نهایی عادی‌سازی نام و انتخاب مقدار غیرفعال، مجموعه تست‌های جدید دوباره اجرا شد: **۲۶ تست و ۱۵۳ assertion موفق**. اعداد بازاجرا با مجموعه اصلی هم‌پوشانی دارند. PHP lint تمام ۳۸ فایل PHP، Pint و `git diff --check` نیز موفق بودند. همه اجراها از PHPUnit با تنظیم SQLite `:memory:` استفاده کردند؛ تست خرید جدید نیز Mail fake و جلوگیری از درخواست پرداخت واقعی دارد. تست مرورگر واقعی، Production، Migration دیتابیس برنامه و آزمون هم‌زمانی MySQL انجام نشده است.

تست قدیمی `ProductFilamentFoundationTest::test_admin_can_save_a_product_specification_from_filament` انتظار `sort_order=2` برای اولین ردیف Repeater دارد؛ Filament مقدار ۱ ذخیره می‌کند. همان شکست با بارگذاری ProductResource و CreateProduct نسخه HEAD از Snapshot موقت نیز بازتولید شد. برای حفظ قرارداد Specifications، رفتار یا انتظار این تست در این فاز تغییر داده نشد.

## فایل‌ها

فایل‌های موجود تغییرکرده:

- `app/Models/Product.php`
- `app/Models/ProductCategory.php`
- `app/Filament/Resources/ProductResource.php`
- `app/Filament/Resources/ProductResource/Pages/CreateProduct.php`
- `app/Filament/Resources/ProductResource/Pages/EditProduct.php`
- `app/Filament/Resources/ProductCategoryResource.php`
- `app/Filament/Resources/Concerns/UsesPersianResourceLabels.php`
- `app/Providers/ShopServiceProvider.php`
- `app/Media/MediaConsumerRegistry.php`
- `tests/Feature/ProductImportTest.php`

فایل‌های جدید:

- `database/migrations/2026_11_09_000001_add_universal_product_catalog.php`
- `app/Models/ProductBrand.php`
- `app/Models/ProductTag.php`
- `app/Models/ProductAttribute.php`
- `app/Models/ProductAttributeValue.php`
- `app/Models/ProductAttributeAssignment.php`
- `app/Models/Concerns/HasCatalogIdentity.php`
- `app/Services/ProductCatalogService.php`
- `app/Policies/ProductCatalogPolicy.php`
- `app/Filament/Support/ProductCatalogFields.php`
- `app/Filament/Support/ProductCatalogEditor.php`
- `app/Filament/Resources/Concerns/HandlesProductCatalogRecords.php`
- `app/Filament/Resources/ProductBrandResource.php`
- `app/Filament/Resources/ProductBrandResource/Pages/ListProductBrands.php`
- `app/Filament/Resources/ProductBrandResource/Pages/CreateProductBrand.php`
- `app/Filament/Resources/ProductBrandResource/Pages/EditProductBrand.php`
- `app/Filament/Resources/ProductTagResource.php`
- `app/Filament/Resources/ProductTagResource/Pages/ListProductTags.php`
- `app/Filament/Resources/ProductTagResource/Pages/CreateProductTag.php`
- `app/Filament/Resources/ProductTagResource/Pages/EditProductTag.php`
- `app/Filament/Resources/ProductAttributeResource.php`
- `app/Filament/Resources/ProductAttributeResource/Pages/ListProductAttributes.php`
- `app/Filament/Resources/ProductAttributeResource/Pages/CreateProductAttribute.php`
- `app/Filament/Resources/ProductAttributeResource/Pages/EditProductAttribute.php`
- `app/Filament/Resources/ProductAttributeResource/RelationManagers/ValuesRelationManager.php`
- `tests/Feature/ProductCatalogFoundationTest.php`
- `tests/Feature/ProductCatalogFilamentTest.php`
- `tests/Feature/ProductCatalogCommerceTest.php`
- `docs/product-catalog-foundation-phase-1.md`
