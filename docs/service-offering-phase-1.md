# بسته خدمات و قرارداد مشتری — فاز ۱

این سند وضعیت پایان فاز ۱ را ثبت می‌کند. فعال‌سازی چرخه‌های وظیفه‌ای/ترکیبی و محدودیت‌های جدید اصلاحیه در [سند فاز ۲](service-delivery-phase-2.md) آمده است؛ غیرفعال‌بودن Cycle در توضیحات زیر مربوط به فاز ۱ است.

این فاز کاتالوگ و قرارداد را اضافه می‌کند. عملیات وظیفه‌محور، WorkItem، سفارش عمومی، پرداخت، تأیید مشتری، فایل خصوصی و محاسبات صورتحساب جدید پیاده نشده‌اند.

## مسیر مدیریت

۱. در «انواع تحویل»، نوع، کد، واحد و توضیح تعریف کنید.
۲. از بخش بسته‌های هر Service یا «بسته‌های خدمات»، بسته بسازید. وضعیت «فعال» اجازه انتخاب نسخه منتشرشده در پروژه را می‌دهد؛ انتشار عمومی Service شرط استفاده داخلی نیست.
۳. در جزئیات بسته، «نسخه پیش‌نویس جدید» را انتخاب کنید. پیش‌نویس جدید شرایط و تعهدات آخرین نسخه را کپی می‌کند.
۴. پیش‌نویس را ویرایش و ذخیره کنید؛ از صفحه مشاهده نسخه، آن را منتشر کنید. نسخه منتشرشده و اقلام آن دیگر قابل ویرایش یا حذف نیستند.
۵. هنگام ایجاد ClientProject، یک نسخه منتشرشده انتخاب کنید یا برای رفتار قدیمی ساعتی، انتخاب را خالی بگذارید.
۶. زمان‌بندی و سهمیه از پیش‌فرض‌های تعریف‌شده پیشنهاد می‌شوند. تغییر شرایط یا پیش‌فرض زمان‌بندی به دلیل ثبت‌شده نیاز دارد.
۷. در جزئیات پروژه، snapshot بسته، قیمت، شرایط و تعهدات را ببینید. «ثبت نسخه جدید قرارداد» اصلاحیه با تاریخ اثر و دلیل می‌سازد.
۸. تغییر زمان‌بندی پروژه دارای قرارداد، از فرم ویرایش با دلیل انجام می‌شود و نسخه قرارداد تازه ثبت می‌کند. تغییر متناقض با سابقه فاکتورشده توسط reconciler موجود رد می‌شود.

## قرارداد داده و پایداری

- پنج جدول جدید: service_offerings، deliverable_types، service_offering_versions، service_offering_deliverables و client_project_plan_versions.
- تنها تغییر جدول موجود: client_projects.delivery_mode با مقدار پیش‌فرض time. قیمت عملیاتی Service و جدول‌های Activity، Cycle، Invoice و InvoiceItem تغییر نمی‌کنند.
- کد بسته و نوع تحویل یکتا است؛ شماره نسخه داخل هر بسته و هر پروژه یکتا است.
- نسخه منتشرشده catalog_snapshot نسخه ۱ دارد؛ نام و کد بسته، نام Service، قیمت، شرایط، تعهدات و نام/کد نوع تحویل را مستقل نگه می‌دارد.
- قرارداد، catalog_snapshot اصلی و agreement_snapshot مشتری را جدا ثبت می‌کند. override شامل قیمت، ارز، شرایط، تعهدات، مبنا/دوره مبلغ و پیش‌فرض‌هاست؛ نوع تحویل و نوع زمان‌بندی باید با پروژه سازگار باشند.
- مقدار و مبلغ با رشته اعشاری، چهار رقم اعشار و BCMath normalize می‌شوند. ورودی float رد می‌شود. روی MySQL/PostgreSQL ستون DECIMAL(18,4) و روی SQLite ستون متنی برای جلوگیری از تبدیل NUMERIC به float استفاده می‌شود.
- قواعد زمان‌بندی فعلی دادهٔ پیش‌فرض هستند: روز ماهانه، سهمیه دقیقه و تاریخ شروع/پایان. موعد تعهد می‌تواند due_day_offset داشته باشد؛ هیچ کاری از آن تولید نمی‌شود.
- نوع تحویل جدید در override مشتری نیازمند نسخه جدید بسته است. مقدار، شرح، واحد، نیاز به تأیید و تعداد اصلاح اقلام موجود قابل override هستند.
- اصلاحیه، نسخه قبلی را بازنویسی نمی‌کند؛ تاریخ اثر آن پیش از امروز یا پیش از آخرین نسخه نیست. برای چند اصلاحیه در یک روز، نسخه بالاتر مؤثر است.
- currentPlan آخرین نسخه مؤثر تا امروز را می‌خواند؛ نسخه‌های آینده در تاریخچه می‌مانند.
- بسته بایگانی‌شده برای قرارداد جدید قابل انتخاب نیست. اصلاح شرایط قرارداد موجود از همان نسخه محفوظ همچنان ممکن است.
- publication، ویرایش پیش‌نویس و اقلام، قفل Offering مشترک دارند. شماره‌گذاری قرارداد با قفل پروژه انجام می‌شود.
- استفاده از bulk query یا SQL مستقیم برای تغییر تاریخچه مجاز نیست؛ مانند قفل‌های فعلی Invoice، محافظت تغییرناپذیری در مسیر Eloquent و سرویس دامنه اعمال می‌شود.
- FKهای restrict از حذف کاتالوگ و قرارداد مورد استفاده جلوگیری می‌کنند؛ بایگانی جایگزین حذف است.

## سازگاری و محدوده اجرا

- پروژه‌های موجود time باقی می‌مانند و هیچ backfill بسته یا قرارداد انجام نمی‌شود.
- ایجاد پروژه بدون بسته، سهمیه ساعت، recurring/fixed_period، مازاد، snapshot فعالیت و صورتحساب فعلی را حفظ می‌کند.
- پروژه time دارای بسته نیز از همان reconciler قبلی استفاده می‌کند؛ قیمت قرارداد در فاز ۱ وارد محاسبه فاکتور نمی‌شود.
- برای tasks و hybrid، resolver، reconciliation و نگهداری خودکار Cycle غیرفعال‌اند؛ حتی با سهمیه دقیقه مثبت Cycle ساخته نمی‌شود.
- تغییر delivery_mode پروژه موجود فعلاً پشتیبانی نمی‌شود.
- hierarchy، URL عمومی، Pricing Cards، Block Registry، Template Runtime، Action Foundation و مجوزهای Portal تغییر نکرده‌اند.
- سرویس ایجاد/اصلاح قرارداد از ClientProjectPolicy موجود استفاده می‌کند. کاتالوگ فقط برای admin فعال قابل مدیریت است.
- migration روی دیتابیس عادی اجرا نشده است؛ اجرای forward migration برای فعال‌شدن این صفحات در محیط استقرار لازم است.

## وابستگی‌های فاز ۲

ایجاد Cycle مستقل از ساعت، snapshot تعهد هر دوره، WorkItem و برنامه‌ریزی اجرایی هنوز لازم‌اند. فعال‌سازی چرخه‌های tasks/hybrid باید پس از پیاده‌سازی و بررسی آن قراردادها انجام شود. داده‌های فاز ۱، مجوز ایجاد تحویل یا صورتحساب جدید نیستند.

## اصلاح اجرای MySQL و بازیابی migration نیمه‌کاره

اجرای MySQL خطای 1059 داد: نام خودکار FK نسخه در `service_offering_deliverables` از سقف ۶۴ نویسه بلندتر بود. همان نام به `offering_deliverable_version_fk` و index تاریخ اثر قرارداد به `project_plan_effective_from_index` تغییر کرد. SQL تولیدشده هر دو migration فاز ۱ و ۲ با grammar واقعی MySQL بررسی شده و نام بلندتر از ۶۴ نویسه ندارد.

MySQL عملیات DDL قبل از خطا را نگه می‌دارد؛ در بررسی فقط‌خواندنی، جدول deliverables ساخته شده بود ولی هیچ FK نداشت و ستون delivery_mode پروژه و جدول plan هنوز ساخته نشده بودند. migration فاز ۱ اکنون جدول‌های موجود را حفظ می‌کند، وجود ستون‌های مورد انتظار را بررسی می‌کند و فقط FK/index جاافتاده را اضافه می‌کند. FK معادل با نام قبلی پذیرفته می‌شود؛ قید متعارض یا شکل ناقص ستون‌ها با خطای روشن متوقف می‌شود. بازسازی جدول SQLite دارای ارجاع ورودی برای ترمیم FK ممنوع است تا delete actionهای قدیمی اجرا نشوند.

برای ادامه همان اجرای ناموفق، فقط `php artisan migrate` را دوباره اجرا کنید. `migrate:fresh`، rollback، حذف جدول یا پاک‌کردن داده لازم نیست. migration اصلاح‌شده روی دیتابیس عادی در این بررسی اجرا نشده است.

۸ تست منتخب migration و سازگاری موفق شدند؛ بازیابی جدول ناقص با حفظ ردیف‌ها و اجرای تکراری، ترمیم index جاافتاده، حفظ پروژه/فعالیت/فاکتور قدیمی و چرخه‌های ساعتی/وظیفه‌ای/ترکیبی بررسی شدند. تست SQL MySQL اتصال واقعی برقرار نمی‌کند؛ تست‌های داده روی SQLite حافظه‌ای هستند. lint و Pint فایل migration و تست جدید موفق بودند. تست جدید: `tests/Feature/ServiceDeliveryMigrationCompatibilityTest.php`.

## بررسی انجام‌شده

تست‌های هدفمند کاتالوگ، snapshot، immutability، override، اصلاحیه، Filament، مهاجرت افزایشی و چند مسیر حساس قدیمی اجرا شده‌اند. migration فقط در SQLite :memory: تحت RefreshDatabase بررسی شده؛ دادهٔ پروژه/فعالیت/فاکتور/آیتم قدیمی پس از migration بدون تغییر مقایسه شده است. کل مجموعه تست‌ها اجرا نشده است. lint PHP و Pint فقط روی فایل‌های این تغییر اجرا می‌شوند.

۲۷ تست منتخب بررسی شد. اجرای اولیه همه موفق بود؛ در تکرار نهایی، ۲۶ تست موفق و یک تست قدیمی چرخه فاکتور با خطای Undefined array key -1 در vendor/khaled.alshamaa/ar-php هنگام ساخت PDF مواجه شد. اجرای مستقل همان تست با ۳ assertion موفق شد. این خطای ناپایدار خارج از تغییرات فاز ۱ است؛ کد PDF یا billing برای آن تغییر نکرده است. lint هر ۳۹ فایل PHP و Pint موفق بودند.

## فایل‌های اصلاح‌شده

- `app/Filament/Resources/ClientProjectResource.php`
- `app/Filament/Resources/ClientProjectResource/Pages/CreateClientProject.php`
- `app/Filament/Resources/ClientProjectResource/Pages/EditClientProject.php`
- `app/Filament/Resources/ClientProjectResource/Pages/ViewClientProject.php`
- `app/Filament/Resources/Concerns/UsesPersianResourceLabels.php`
- `app/Filament/Resources/ServiceResource.php`
- `app/Models/ClientProject.php`
- `app/Models/Service.php`
- `app/Services/ClientProjectCycleReconciler.php`
- `app/Services/ClientProjectCycleResolver.php`

## فایل‌های جدید

- `app/Enums/ClientProjectDeliveryMode.php`
- `app/Filament/Resources/ClientProjectResource/ContractFields.php`
- `app/Filament/Resources/DeliverableTypeResource.php`
- `app/Filament/Resources/DeliverableTypeResource/Pages/ManageDeliverableTypes.php`
- `app/Filament/Resources/ServiceOfferingResource.php`
- `app/Filament/Resources/ServiceOfferingResource/Pages/CreateServiceOffering.php`
- `app/Filament/Resources/ServiceOfferingResource/Pages/EditServiceOffering.php`
- `app/Filament/Resources/ServiceOfferingResource/Pages/ListServiceOfferings.php`
- `app/Filament/Resources/ServiceOfferingResource/Pages/ViewServiceOffering.php`
- `app/Filament/Resources/ServiceOfferingResource/RelationManagers/VersionsRelationManager.php`
- `app/Filament/Resources/ServiceOfferingVersionResource.php`
- `app/Filament/Resources/ServiceOfferingVersionResource/Pages/EditServiceOfferingVersion.php`
- `app/Filament/Resources/ServiceOfferingVersionResource/Pages/ListServiceOfferingVersions.php`
- `app/Filament/Resources/ServiceOfferingVersionResource/Pages/ViewServiceOfferingVersion.php`
- `app/Filament/Resources/ServiceResource/RelationManagers/OfferingsRelationManager.php`
- `app/Models/ClientProjectPlanVersion.php`
- `app/Models/DeliverableType.php`
- `app/Models/ServiceOffering.php`
- `app/Models/ServiceOfferingDeliverable.php`
- `app/Models/ServiceOfferingVersion.php`
- `app/Policies/DeliverableTypePolicy.php`
- `app/Policies/ServiceOfferingPolicy.php`
- `app/Policies/ServiceOfferingVersionPolicy.php`
- `app/Services/ClientProjectAgreementManager.php`
- `app/Services/CreateClientProjectWithOffering.php`
- `app/Services/ServiceAgreementData.php`
- `app/Services/ServiceOfferingPublisher.php`
- `database/migrations/2026_10_11_100000_create_service_offering_contract_foundation.php`
- `tests/Feature/ServiceOfferingContractFoundationTest.php`
- `docs/service-offering-phase-1.md`

