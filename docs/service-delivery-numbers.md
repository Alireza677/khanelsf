# اعتبارسنجی مقدار و نمایش اعداد خدمات

این تغییر جدول یا migration ندارد. ذخیره‌سازی DECIMAL(…, 4)، دقت مبلغ، محاسبات مالی و Invoice، فعالیت‌های ساعتی و ساختار Commitment/WorkItem حفظ شده‌اند. ستون مقدار می‌تواند همچنان `3.0000` داشته باشد؛ مقدار از نظر ریاضی صحیح است و رابط آن را `3` نمایش می‌دهد.

## قواعد ورودی

- `ServiceAgreementData::quantity` مقدار مثبت را با BCMath و رشته بررسی می‌کند. واحدهای `count`، `counts`، `session`، `sessions`، «عدد»، «جلسه» و «نوبت» صحیح‌اند. `3.5` رد و `3` یا `3.0000` پذیرفته می‌شود. واحدهای قابل تقسیم مانند متر و مترمربع، اعشار تا چهار رقم را حفظ می‌کنند. سایر واحدهای سفارشی رفتار اعشاری قبلی را دارند.
- این قواعد برای تعریف/انتشار تعهد، قرارداد جدید، تغییر مقدار قرارداد و ایجاد/تغییر مقدار کار اضافی در مدل‌ها و سرویس‌های سمت سرور اجرا می‌شوند؛ وابسته به فرم Filament نیستند.
- ویژگی عددی، مستقل از واحد نمایشی، کلید اختیاری `numeric_format: integer | decimal` دارد. برای ثانیه، ساعت یا هر واحد دیگری می‌توان صحیح یا اعشاری انتخاب کرد. صحیح بودن مقدار به نام ویژگی یا صنعت وابسته نیست.
- برای `numeric_format=integer`، مقدار `60.5` رد می‌شود؛ در حالت اعشاری `2.5` معتبر است. انتخاب نوع عدد، مقدار موجود را خودکار گرد یا قطع نمی‌کند. مقدار ناسازگار باید توسط مدیر اصلاح شود.
- کلید نوع عدد فقط برای `type=number` مجاز است. نبود کلید در JSON قدیمی معادل decimal است؛ نرمال‌سازی سمت سرور خودکار این کلید را به JSON قدیمی اضافه نمی‌کند. فرم هنگام ذخیره پیش‌نویس انتخاب اعشاری خود را صریح ثبت می‌کند. توسعه، اختیاری و سازگار با قرارداد JSON نسخه ۲ است.
- فرم مشترک ویژگی‌ها در مدیریت نسخه، ویزارد، قرارداد مشتری و اصلاحیه استفاده می‌شود. ورودی عدد صحیح با `step=1` و `inputMode=numeric` و ورودی اعشاری با `step=0.0001` و `inputMode=decimal` تنظیم شده است. واحد تعهد در تغییر قواعد مقدار نقش دارد؛ واحد نمایشی ویژگی چنین نقشی ندارد.

## نمایش

`App\Support\ServiceDeliveryNumber::format` بدون float و بدون گردکردن، صفرهای انتهایی اعشار را حذف می‌کند: `3.0000 → 3`، `2.5000 → 2.5`، `12.7500 → 12.75` و `0.0000 → 0`. اعداد بزرگ و اعشار واقعی حفظ می‌شوند.

این formatter در مقدار و ویژگی عددی فرم/جزئیات نسخه، خلاصه ویزارد، تاریخچه قرارداد، تعهدات دوره، پیشرفت، WorkItem، داشبورد و صفحات تحویل و گزارش تاریخی پرتال استفاده شده است. متن و Boolean قالب‌بندی عددی نمی‌شوند. Presenterها، محاسبات پیشرفت و JSON گزارش، مقدار خام تاریخی را نگه می‌دارند؛ قالب‌بندی هنگام نمایش انجام می‌شود. نمایش و محاسبات قیمت و Invoice تغییر نکرده‌اند.

## سوابق و Snapshot

- نسخه منتشرشده، قرارداد، دوره و گزارش موجود بازنویسی نمی‌شوند. کلید نوع عددِ انتخاب‌شده در انتشار و Snapshot قرارداد جدید حفظ می‌شود؛ اصلاح آن در قرارداد فقط در دوره آینده طبق قواعد موجود انجام می‌شود.
- مقایسه ویژگی‌های قرارداد، نبود نوع عدد را معادل اعشاری می‌داند تا ثبت صریح پیش‌فرض اعشاری به اشتباه تغییر واقعی شرایط تلقی نشود.
- اگر تعهد شمارشی اعشاری در سوابق قبل از این اعتبارسنجی وجود داشته باشد، خواندن، گزارش و ادامه مقدار تاریخیِ بدون تغییر حفظ می‌شود. معافیت فقط از داده معتبر ذخیره‌شده و با تطبیق جایگاه، نوع تحویل، واحد و مقدار گرفته می‌شود؛ داده جدید یا مقدار تغییر‌یافته معاف نیست.
- ساخت کار از باقی‌مانده اعشاری چنین تعهد تاریخی حفظ شده است. تعهد صحیح جدید نمی‌تواند کار شمارشی اعشاری بگیرد. تغییر عنوان/وضعیت کار تاریخی، مقدار آن را تغییر نمی‌دهد.
- ویزارد می‌تواند نسخه تاریخی را بخواند و مدیر مقدار شمارشی را برای نسخه جدید اصلاح کند؛ ناشر تعریف معتبر جدید را مستقیماً ایجاد می‌کند. کپی پیشرفته یک تعهد شمارشی اعشاری قدیمی نمی‌تواند بدون اصلاح مقدار، تعهد نامعتبر جدید ثبت کند.

## فایل‌ها

جدید:

- `app/Support/ServiceDeliveryNumber.php`
- `tests/Feature/ServiceDeliveryNumberTest.php`
- `docs/service-delivery-numbers.md`

اصلاح‌شده:

- `app/Services/ServiceAgreementData.php`
- `app/Services/ServiceOfferingPublisher.php`
- `app/Services/ServiceOfferingWizardManager.php`
- `app/Services/ClientProjectAgreementManager.php`
- `app/Services/ClientProjectWorkItemManager.php`
- `app/Models/ServiceOfferingVersion.php`
- `app/Models/ServiceOfferingDeliverable.php`
- `app/Models/ClientProjectPlanVersion.php`
- `app/Models/ClientProjectWorkItem.php`
- `app/Filament/Resources/ServiceOfferingVersionResource.php`
- `app/Filament/Resources/DeliverableTypeResource.php`
- `app/Filament/Resources/ClientProjectWorkItemResource.php`
- `app/Filament/Resources/ClientProjectCycleResource.php`
- `app/Filament/Resources/ClientProjectResource/ContractFields.php`
- `resources/views/filament/service-offerings/summary.blade.php`
- `resources/views/client/dashboard.blade.php`
- `resources/views/client/projects/delivery-cycles.blade.php`
- `resources/views/client/projects/work-item.blade.php`
- `tests/Feature/ServiceOfferingFeaturesTest.php`

## بررسی انجام‌شده

۶ بررسی هدفمند با ۸۱ assertion موفق بود: اعتبارسنجی صحیح/اعشاری و واحد، قالب‌بندی دقیق و صفر، مدل و سرویس WorkItem و ورودی ویزارد، Snapshot نوع عدد و اصلاحیه آینده، سوابق شمارشی اعشاری قدیمی و گزارش، فرم موجود ویژگی‌ها و تغییرناپذیری انتشار، و سازگاری اصلاحیه ویژگی در پروژه زمانی. SQLite حافظه‌ای استفاده شد؛ fixtureهای سوابق فقط در دیتابیس تست ساخته شدند. Pint، syntax فایل‌های PHP تغییرکرده و `git diff --check` بررسی شدند. تست کامل و migration روی دیتابیس کاری اجرا نشده‌اند.

```powershell
C:\php84\php.exe artisan test --compact tests/Feature/ServiceDeliveryNumberTest.php tests/Feature/ServiceOfferingFeaturesTest.php --filter="ServiceDeliveryNumberTest|filament_edits_typed_features|time_feature_amendments"
```
