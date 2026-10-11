# چرخه‌های تحویل و کار اجرایی — فاز ۲

این فاز مدیریت داخلی تحویل وظیفه‌ای و ترکیبی را فعال می‌کند. کاتالوگ، نسخه منتشرشده و ساختار snapshot قرارداد فاز ۱ حفظ شده‌اند. سفارش عمومی، تأیید مشتری، فایل تحویل، پرداخت و محاسبات صورتحساب جدید اضافه نشده‌اند.

## وضعیت migration و استقرار

پیش از تغییر کد، `artisan migrate:status` روی اتصال عادی خوانده شد: migration ترمیم جدول‌های عملیاتی `2026_10_10_000000_repair_missing_operational_tables` و migration فاز ۱ `2026_10_11_100000_create_service_offering_contract_foundation` در انتظار بودند. migration جدید `2026_10_11_200000_add_task_delivery_cycles_and_work_items` هم اجرا نشده است. هیچ migration روی دیتابیس عادی اجرا نشد؛ بررسی migration فقط در SQLite حافظه‌ای تست انجام می‌شود. برای استقرار باید forward migrationها به ترتیب اجرا شوند.

Migration جدید پنج جدول اضافه می‌کند: `client_project_cycle_commitments`، `client_project_work_items`، `client_project_work_item_user`، `client_project_work_item_activity` و `client_project_work_item_events`، به‌همراه چهار ستون Cycle: `client_project_plan_version_id`، `delivery_mode`، `delivery_status` و `agreement_snapshot`. جدول‌ها و ستون‌های قدیمی بازنویسی نمی‌شوند. FKها از حذف سابقه تحویل جلوگیری می‌کنند. نام constraintهای طولانی کوتاه شده تا محدودیت MySQL رعایت شود؛ اجرای واقعی migration در MySQL/PostgreSQL انجام نشده است.

در SQLite ستون nullable با FK به قرارداد با `ALTER TABLE ADD COLUMN` مستقیم اضافه می‌شود. این کار از بازسازی Cycle و اجرای ناخواستهٔ `SET NULL` یا `CASCADE` روی فعالیت، فاکتور و claim قدیمی جلوگیری می‌کند. مقدارهای اعشاری روی SQLite رشته و روی سایر درایورها `DECIMAL(18,4)` هستند؛ محاسبات مقدار با BCMath انجام می‌شوند.

## چرخه و قرارداد مؤثر

- `time`: مسیر قدیمی سهم ساعت، تکمیل سهمیه، مصرف، مازاد و فاکتور حفظ می‌شود؛ هیچ تعهد کاری خودکار برای پروژه صرفاً ساعتی ساخته نمی‌شود.
- `tasks`: سهم ساعت لازم نیست؛ برای حفظ قرارداد ستون قدیمی غیرnullable، `allocated_minutes=0` مقدار فنیِ نبود سهم زمانی است و معیار تکمیل نیست. Activity ساختگی تولید نمی‌شود.
- `hybrid`: سهم زمانی و تعهدات مستقل‌اند؛ تکمیل ساعت، تعهد وظیفه‌ای را تکمیل نمی‌کند. بدون سهم زمانی هم تعهد کاری قابل مدیریت است؛ مصرف زمان در این حالت مازاد محسوب نمی‌شود. guard جدید Usage/Overage فقط برای حالت‌های جدید اعمال می‌شود و محاسبه time قبلی تغییر نمی‌کند.
- recurring همچنان مرز ماه شمسی و clamp روز ماه را از resolver موجود می‌گیرد. fixed_period یک دوره از شروع تا پایان کل پروژه است. بازه `[starts_at, ends_at)` باقی می‌ماند.
- ساخت Cycle با قفل پروژه، کلید یکتای قدیمی و snapshot تعهدات در یک transaction انجام می‌شود. اجرای مجدد همان دوره چیزی را بازنویسی نمی‌کند.
- قرارداد مؤثر در شروع دوره انتخاب می‌شود. قرارداد اولیه می‌تواند در میانه اولین ماه شمسی آغاز شده باشد؛ کار پیش از تاریخ اثر قرارداد ساخته نمی‌شود.
- برای tasks/hybrid، اصلاحیه جدید باید در مرز شروع یک دوره آینده، بعد از آخرین اصلاحیه و بعد از تمام دوره‌های دارای snapshot ثبت‌شده اثر کند. تداخل، تاریخ یکسان و اصلاح میان‌دوره رد می‌شود. قراردادهای هم‌روز قبلی فاز ۱ با نسخه بالاتر خوانده می‌شوند؛ داده آنها بازنویسی نمی‌شود. اصلاحیه‌های قدیمی میان‌دوره که انتخاب یک قرارداد واحد را مبهم می‌کنند خطای اعتبارسنجی می‌دهند.
- قرارداد پروژه fixed_period دارای تعهد، تا پایان همان دوره ثابت می‌ماند؛ برای قرارداد بعدی پروژه تازه لازم است. تغییر زمان‌بندی یا سهم دوره دارای تعهد رد می‌شود. این محدودیت از بازسازی یا حذف تاریخچه جلوگیری می‌کند.
- Cycle دارای snapshot، تعهد یا WorkItem در reconciliation محافظت می‌شود؛ محافظت مالی قبلی نیز برقرار است. snapshotهای published offering و plan قبلی تغییر نمی‌کنند.

## کار، مقدار و پیشرفت

تعهد هر دوره شماره تعریف، نوع، نام، شرح، مقدار، واحد، نیاز به تأیید، اصلاح مجاز، قواعد زمان‌بندی و تعریف کامل محفوظ را نگه می‌دارد و تغییرناپذیر است. WorkItem به پروژه و دوره اصلی و در صورت قراردادی‌بودن به تعهد وصل است. عنوان، شرح، مقدار، واحد، دسترسی، زمان برنامه، موعد، شروع واقعی، پایان اجرا، پذیرش و پایان نهایی، کلید تولید و `lock_version` دارد. چند مدیر فعال و چند Activity همان دوره ترکیبی قابل ارتباط‌اند؛ اطلاعات یا قیمت Activity تغییر نمی‌کند.

Generator هر واحد کامل را یک کار و کسر باقی‌مانده را یک کار جدا می‌سازد؛ پنج واحد، پنج کار دارد. کلید `commitment:{id}:{ordinal}` یکتا است. تولید تکراری، کار لغوشده را جایگزین نمی‌کند. سقف تولید خودکار ۱۰۰۰ کار برای هر تعهد است؛ مقدار قرارداد محدود یا کم نمی‌شود. کار دستی با کلید UUID و بدون تعهد ساخته می‌شود و در پیشرفت قرارداد محاسبه نمی‌شود. مقدار و واحد کار قراردادی قابل ویرایش نیست. لغو، تعهد را کاهش نمی‌دهد و کار معوق جابه‌جا نمی‌شود.

انتقال‌های مدیریتی: planned به ready یا cancelled؛ ready به in_progress، planned، blocked یا cancelled؛ in_progress به submitted، blocked یا cancelled و برای کار بدون تأیید، completed؛ blocked به ready، in_progress یا cancelled. کار submitted بدون تأیید می‌تواند completed شود یا به اجرا برگردد. cancelled و completed نهایی هستند. دلیل لغو و مسدودشدن الزامی است. accepted و revision_requested برای بررسی مشتری در فاز ۳ رزرو شده‌اند؛ مدیر در این فاز تأیید مشتری را شبیه‌سازی نمی‌کند.

Manager با قفل دوره/کار و مقایسه `lock_version`، تغییر هم‌زمان را رد می‌کند. ویرایش مستقیم Eloquent و حذف کار رد می‌شود. رویدادهای ایجاد/ویرایش/انتقال با مدیر، زمان، دلیل و شماره ویرایش به صورت append-only ثبت می‌شوند.

پیشرفت برای هر تعریف و نوع/واحد مستقل شامل committed، operational_completed، completed و remaining است؛ درصد کلی وجود ندارد. cancelled و submitted در تکمیل تعهد حساب نمی‌شوند. کار نیازمند تأیید در submitted پایان اجرا دارد ولی completed تعهد آن صفر می‌ماند تا فاز ۳. تکمیل مازاد به مقدار تعهد سقف می‌خورد. `delivery_status` مشتق از تعهدات، مستقل از status مالی/زمانی است: planned، active، awaiting_approval، fulfilled یا overdue. پایان دوره موفقیت تلقی نمی‌شود. نگهداری زمان‌بندی موجود، وضعیت تحویل دوره‌های ترکیبیِ تکمیل سهمیه یا فاکتورشده را نیز تازه می‌کند.

## مدیریت فارسی

در جزئیات پروژه وظیفه‌ای/ترکیبی، بخش «دوره‌های تحویل و تعهدات» برای ثبت دوره تاریخ مشخص، همگام‌سازی امن، مشاهده تعهد و پیشرفت و تولید کارها اضافه شده است. «دوره‌های تحویل» و «کارهای اجرایی» در پنل مدیریت نیز در دسترس‌اند. کارها قابل ایجاد اضافی، ویرایش، انتساب چند مدیر، تعیین موعد و برنامه، تغییر وضعیت و مشاهده تاریخچه‌اند. فیلتر مشتری، پروژه، دوره، مسئول، وضعیت، معوق، مسدود و قراردادی/اضافی وجود دارد. فرم‌های تاریخ از Jalali و RTL پنل موجود استفاده می‌کنند. دسترسی داخلی از مجوز update پروژه موجود و مدیر فعال پیروی می‌کند؛ هیچ مسیر مشتری برای WorkItem اضافه نشده است. فیلد visibility صرفاً دادهٔ آمادهٔ نمایش آینده است.

## فایل‌های جدید

- `app/Enums/ClientProjectWorkItemStatus.php`
- `app/Models/ClientProjectCycleCommitment.php`
- `app/Models/ClientProjectWorkItem.php`
- `app/Models/ClientProjectWorkItemEvent.php`
- `app/Services/ClientProjectCycleDelivery.php`
- `app/Services/ClientProjectDeliveryProgress.php`
- `app/Services/ClientProjectWorkItemGenerator.php`
- `app/Services/ClientProjectWorkItemManager.php`
- `app/Policies/ClientProjectCyclePolicy.php`
- `app/Policies/ClientProjectWorkItemPolicy.php`
- `app/Filament/Resources/ClientProjectCycleResource.php`
- `app/Filament/Resources/ClientProjectCycleResource/Pages/ManageDeliveryCycles.php`
- `app/Filament/Resources/ClientProjectWorkItemResource.php`
- `app/Filament/Resources/ClientProjectWorkItemResource/Pages/ManageWorkItems.php`
- `app/Filament/Resources/ClientProjectResource/RelationManagers/DeliveryCyclesRelationManager.php`
- `database/migrations/2026_10_11_200000_add_task_delivery_cycles_and_work_items.php`
- `tests/Feature/ClientProjectTaskDeliveryTest.php`
- `docs/service-delivery-phase-2.md`

## فایل‌های اصلاح‌شده در این فاز

- `app/Models/ClientProject.php`
- `app/Models/ClientProjectCycle.php`
- `app/Models/ClientProjectPlanVersion.php`
- `app/Services/ClientProjectCycleResolver.php`
- `app/Services/ClientProjectCycleReconciler.php`
- `app/Services/RecalculateClientProjectCycle.php`
- `app/Services/ClientProjectCycleUsage.php`
- `app/Services/ClientProjectActivityOverage.php`
- `app/Filament/Resources/ClientProjectResource.php`
- `app/Filament/Resources/ClientProjectResource/ContractFields.php`
- `app/Filament/Resources/ClientProjectResource/Pages/ViewClientProject.php`
- `tests/Feature/ServiceOfferingContractFoundationTest.php`
- `docs/service-offering-phase-1.md`

## بررسی و ادامه

۳۹ تست منتخب موفق شدند: ۱۳ تست تحویل وظیفه‌ای/ترکیبی، ۱۶ تست قرارداد فاز ۱ و ده تست منتخب چرخه و billing قدیمی. پس از اصلاح حالت ترکیبی بدون سهم زمانی و جداسازی وضعیت معوق تحویل، مسیرهای متأثر دوباره بررسی و موفق شدند. کل suite اجرا نشد. تست migration، داده پروژه، Cycle، Activity، Invoice، InvoiceItem و claim قدیمی را قبل و بعد از down/up در SQLite حافظه‌ای مقایسه کرد. lint و Pint هر ۲۹ فایل PHP و `git diff --check` موفق بودند. تست‌های Filament ایجاد اضافی، تولید کار، ویرایش، انتساب و نمایش تعهد/تاریخچه را پوشش دادند؛ بررسی بصری مرورگر انجام نشد.

فاز ۳ هنوز لازم است: نمایش امن تحویل در Portal، تأیید و درخواست اصلاح مشتری با محدودیت اصلاح قرارداد، نسخه‌های تحویل و فایل‌های خصوصی و تاریخچه مشتری. اتصال Activity به کار صرفاً رابطه مدیریتی است؛ Phase 3 باید هنگام نمایش مشتری مالکیت فعلی رابطه را دوباره بررسی کند. هیچ کد فاز ۳ در این تغییر اجرا نشده است.
