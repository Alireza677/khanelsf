# ویژگی‌ها و شرایط پکیج

## تصمیم و قرارداد داده

`ServiceOfferingVersion` تعریف قابل ویرایش پیش‌نویس را از `catalog_snapshot` منتشرشده جدا نگه می‌دارد. `scheduling_defaults` نیز قرارداد محدود و مختص زمان‌بندی دارد. برای حفظ این مرزها، یک ستون JSON nullable به نام `features` به همان جدول `service_offering_versions` اضافه شده است. مدل یا جدول جدیدی برای ویژگی ساخته نشده است.

ویژگی‌ها فهرست مرتب و مستقل از `deliverables` هستند:

```json
[
  {"title": "پشتیبانی", "type": "boolean", "value": false, "unit": null},
  {"title": "بازه حضور", "type": "text", "value": "۳ تا ۴ ساعت", "unit": null},
  {"title": "مدت پاسخ", "type": "number", "value": "60", "unit": "دقیقه"}
]
```

Boolean به boolean واقعی تبدیل می‌شود و «ندارد» مقدار معتبر است. Text رشته حداکثر ۲۰۰۰ نویسه است. Number ورودی رشته اعشاری یا integer می‌پذیرد و به رشته اعشاری دقیق تبدیل می‌شود؛ صفر و اعداد منفی مجازند، بدون محاسبه floating-point. محدودیت عدد، ۱۴ رقم صحیح و ۴ رقم اعشار است. واحد نمایشی فقط برای Number مجاز و اختیاری است. هر نسخه حداکثر ۱۰۰ ویژگی با عنوان حداکثر ۲۵۵ نویسه دارد. فیلدهای ناشناخته و نوع/مقدار ناسازگار در سمت سرور رد می‌شوند.

قواعد quantity، واحد الزامی، approval، revision و scheduling تعهدات مقداری تغییر نکرده‌اند. ویژگی‌ها وارد جدول تعهد، محاسبه پیشرفت یا تولید WorkItem نمی‌شوند. پکیج وظیفه‌ای همچنان برای انتشار به تعهد تحویل واقعی نیاز دارد.

## نسخه‌ها و سوابق

- پیش‌نویس از فرم نسخه یا مسیر مدل قابل ویرایش است و ویژگی‌ها در ساخت پیش‌نویس بعدی کپی می‌شوند.
- قفل موجود نسخه منتشرشده تمام فیلدها، از جمله `features`، را محافظت می‌کند؛ draft قدیمی نیز نمی‌تواند قفل انتشار را دور بزند.
- snapshot کاتالوگ و قراردادهای جدید با `schema_version: 2` ویژگی‌ها را نگه می‌دارند.
- قراردادهای قبلی، نسخه‌های منتشرشده و snapshotهای schema 1 بازنویسی یا backfill نمی‌شوند. نبود ویژگی در خواندن/تعریف استاندارد، فهرست خالی محسوب می‌شود؛ snapshot خام کاتالوگ قبلی عیناً حفظ می‌شود.
- ایجاد قرارداد مشتری، ویژگی‌های پکیج یا override اختصاصیِ همراه دلیل را در `agreement_snapshot.features` ثبت می‌کند.
- اصلاحیه همان پکیج که ویژگی را صریحاً تغییر نداده باشد، ویژگی اختصاصی مشتری را حفظ می‌کند.
- تغییر ویژگی در اصلاحیه باید در مرز شروع دوره آینده مؤثر شود؛ این قاعده برای پروژه ساعتی نیز اعمال می‌شود. رفتار اصلاحیه‌های ساعتیِ غیرمرتبط با ویژگی‌ها حفظ شده است.
- دوره‌های وظیفه‌ای/ترکیبی، snapshot کامل قرارداد مؤثر شامل ویژگی‌ها را دریافت می‌کنند؛ دوره دارای تعهد محفوظ و سوابق قبلی ثابت می‌مانند. پروژه fixed-period همچنان یک دوره ثابت دارد و اصلاح ویژگی آن در میانه همان دوره مجاز نیست.

## مدیریت و مصرف آینده

Repeater فارسی و RTL «ویژگی‌ها و شرایط پکیج» در فرم موجود شرایط نسخه اضافه شده است. عنوان، انتخاب نوع، ورودی متناسب و واحد اختیاری دارد و همان فرم در override ایجاد پروژه و ثبت اصلاحیه استفاده می‌شود. جزئیات نسخه و تاریخچه قرارداد نیز ویژگی‌ها را نمایش می‌دهند؛ Boolean با «دارد / ندارد» ارائه می‌شود.

ویژگی‌ها برای مصرف آینده از `catalog_snapshot.features` و `agreement_snapshot.features` قابل خواندن‌اند. projection امن قرارداد دوره نیز این فیلد را نگه می‌دارد تا گزارش‌های منتشرشده جدید، شرایط تاریخی کامل را حفظ کنند. نمایش جدید ویژگی‌ها در Portal یا Pricing Cards در این تغییر پیاده‌سازی نشده است؛ Pricing Cards و Action Foundation تغییری ندارند.

## فایل‌ها

ایجادشده:

- `database/migrations/2026_10_11_400000_add_features_to_service_offering_versions.php`
- `tests/Feature/ServiceOfferingFeaturesTest.php`
- `docs/service-offering-features.md`

تغییرکرده:

- `app/Models/ServiceOfferingVersion.php`
- `app/Models/ClientProjectPlanVersion.php`
- `app/Services/ServiceAgreementData.php`
- `app/Services/ServiceOfferingPublisher.php`
- `app/Services/ClientProjectAgreementManager.php`
- `app/Services/ClientProjectCycleDelivery.php`
- `app/Services/ClientDeliveryPresenter.php`
- `app/Filament/Resources/ServiceOfferingVersionResource.php`
- `app/Filament/Resources/ClientProjectResource/ContractFields.php`
- `app/Filament/Resources/ClientProjectResource/Pages/ViewClientProject.php`

## بررسی و استقرار

فقط فایل آزمون جدید اجرا شد: **۵ آزمون هدفمند، ۵۸ assertion موفق**. نوع‌های مقدار و صفر/false، quantity متنی نامعتبر، فرم واقعی Filament، انتشار و clone، snapshot و override، اصلاحیه آینده و حفظ تاریخچه/WorkItem، رفتار پروژه ساعتی و snapshotهای قدیمی بررسی شدند. migration در SQLite حافظه‌ای، از جمله رفت‌وبرگشت و عدم تغییر سوابق، بررسی شد. مجموعه آزمون کامل اجرا نشده است.

PHP lint و Pint روی ۱۲ فایل PHP مربوط به این تغییر موفق بودند. migrationهای قبلی تا فاز ۳ اجرا شده‌اند؛ migration جدید `2026_10_11_400000_add_features_to_service_offering_versions` **Pending** است و پایگاه اصلی migration نشده است. برای استقرار، اجرای معمول `php artisan migrate` لازم است؛ جدول یا backfill دیگری وجود ندارد.
