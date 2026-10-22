# کتابخانه ویدئویی King — گزارش پیاده‌سازی فاز اول

تاریخ: ۲۰۲۶-۱۰-۲۱

## نتیجه بررسی معماری

- الگوی انتشار از `Project::scopePublished()` گرفته شد: وضعیت `published` و تاریخ خالی یا رسیده؛ وضعیت پیش‌نویس و انتشار آینده عمومی نیستند.
- تصویر جایگزین بر پایه `HasFeaturedImage`، `HasSharedMediaUsages` و `UsesMediaLibraryImages` است. شناسه انتخاب‌شده در `media_usages` با collection برابر `featured_image` نگهداری می‌شود؛ ستون موازی `thumbnail_media_id` اضافه نشد. حذف ویدئو فقط usage را حذف می‌کند و فایل مشترک باقی می‌ماند.
- ثبت مقصد آرشیو مانند `ServiceServiceProvider` در Provider خود ماژول انجام شد؛ قرارداد `NavigationSourceRegistry`، `MenuItem` و resolverها تغییر نکرد.
- تنظیمات در `ManageSiteSettings` و `SettingsService` فعلی ثبت شد؛ نیازی به جدول یا سیستم تنظیمات جداگانه نیست.
- از policyهای قابل کشف Laravel و الگوی مدیر فعال در `User`/policyهای فعلی استفاده شد.
- توضیحات با همان `RichText::render()` پروژه پاکسازی می‌شوند. لینک مرتبط از `ActionDestinationValidator` فعلی با نوع `custom_url` اعتبارسنجی می‌شود.
- `CollectionPresentation` و adapterهای فعلی بررسی شدند. فاز اول فقط fallback متنی ساده دارد؛ هیچ داده مخصوص ویدئو به DTO عمومی اضافه نشد و طراحی کارت/adapter نمایشی به فاز دوم واگذار شد. `VideoQueryService::archiveQuery()` برای مصرف‌های عمومی بعدی قابل استفاده است.
- مدل‌های `Gallery`، `ProjectVideo` و داده‌های قبلی آن‌ها تغییر نکردند؛ migration یا backfill تبدیل محتوا وجود ندارد. Block/Template/Recipe/Action/Navigation Core تغییر نکردند.

## قابلیت‌های آماده

- دامنه مستقل `Video` و `VideoCategory`، رابطه چندبه‌چند، عنوان، نامک یکتا، خلاصه، توضیحات، provider، لینک canonical، external ID، تصویر cached، مدت، لینک مرتبط، وضعیت، زمان انتشار و ترتیب.
- شناسه آپارات از لینک معتبر استخراج می‌شود؛ `(provider, external_id)` در دیتابیس قید یکتا دارد. عنوان جدید نامک رکورد موجود را بازتولید نمی‌کند. نامک خالی هنگام ایجاد خودکار ساخته می‌شود.
- Resource ویدئو در گروه «محتوای وب‌سایت» با ترتیب ۵، بلافاصله پس از رسانه با ترتیب ۴، و Resource دسته‌های ویدئو با ترتیب ۶؛ مدیریت مستقل از toggle عمومی باقی می‌ماند.
- فرم فارسی شامل دسته‌های چندانتخابی، مدت `mm:ss` یا `hh:mm:ss` با پشتیبانی از ارقام فارسی/عربی، تصویر مشترک جایگزین، پیش‌نمایش تصویر خودکار، انتشار و زمان‌بندی است.
- جدول شامل تصویر، عنوان، دسته، مدت، وضعیت و تاریخ‌ها، جست‌وجوی عنوان، فیلتر دسته/وضعیت/زمان‌بندی و ویرایش/حذف تکی و گروهی است.
- policyهای ایجاد/مشاهده/ویرایش/حذف فقط مدیر فعال را مجاز می‌دانند؛ refresh دستی نیز مجوز ویرایش می‌خواهد. ورودی تصویر جایگزین سمت سرور با رسانه قابل استفاده تطبیق داده می‌شود.
- Provider آپارات فقط لینک HTTPS با host دقیق `aparat.com` یا `www.aparat.com` و مسیر `/v/{hash}` را می‌پذیرد. query/fragment ورودی در canonical باقی نمی‌ماند. iframe، HTML و hostهای دیگر پذیرفته نمی‌شوند.
- embed URL فقط از provider و شناسه معتبر ساخته می‌شود و از HTML یا iframe ارسالی مدیر گرفته نمی‌شود.
- در ایجاد یا تغییر منبع، دریافت best-effort تصویر/مدت از metadata صفحه عمومی canonical انجام و نتیجه در همان رکورد cache می‌شود. درخواست در render عمومی انجام نمی‌شود؛ فایل ویدئو دانلود نمی‌شود.
- transport به cURL محدود است؛ مقصد ثابت، DNS عمومی بررسی‌شده و IP پین‌شده، TLS verification، redirect خاموش، proxy خاموش، محدودیت اتصال ۲ ثانیه، درخواست ۵ ثانیه و پاسخ حدود ۱ MiB اعمال می‌شود. فشرده‌سازی پاسخ غیرفعال است. نبود cURL/DNS عمومی یا خطای شبکه باعث استفاده از fallback می‌شود.
- thumbnail دریافتی فقط HTTPS روی دامنه آپارات یا زیردامنه‌های آن است. اولویت نمایش: تصویر مشترک انتخاب‌شده، URL تصویر cached آپارات، `video-placeholder.svg` محلی.
- refresh دستی عنوان، خلاصه، توضیحات و مدت واردشده مدیر را بازنویسی نمی‌کند؛ شکست refresh اطلاعات قبلی را نگه می‌دارد. تغییر منبع تصویر cached قبلی را پاک می‌کند.

## مسیر، منو و تنظیمات

- مسیر عمومی: `GET /videos`، نام `videos.index`، ثبت‌شده پیش از catch-all برگه‌ها. مسیر جزئیات `videos/{slug}` ساخته نشده است.
- آرشیو فقط query عمومی منتشرشده را با ترتیب پایدار و pagination اجرا می‌کند؛ تعداد در هر صفحه بین ۱ و ۴۸ محدود است. پارامتر صفحه نیز عدد مثبت محدودشده است.
- صفحه فعلی فهرست ساده عنوان، خلاصه، مدت، پیوند مشاهده در آپارات و pagination دارد. کارت نهایی، iframe عمومی، modal، فیلتر و جست‌وجوی زنده ساخته نشده‌اند.
- منبع منو: `videos.archive`، module برابر `video_library`، عنوان «کتابخانه ویدئویی». URL هنگام resolve تولید می‌شود و در MenuItem ذخیره نمی‌شود. مدیر می‌تواند مقصد را از ویرایشگر فعلی منو اضافه کند. هیچ آیتم منویی هنگام فعال‌سازی یا boot خودکار ایجاد نمی‌شود؛ حذف عمدی کاربر حفظ می‌شود.
- فعال بودن ماژول فقط URL آرشیو را وارد سایت‌مپ می‌کند؛ URL مجزا برای ویدئوهای منتشرشده یا پیش‌نویس وجود ندارد.
- خاموش بودن ماژول باعث 404 آرشیو، حذف آرشیو از سایت‌مپ، unavailable شدن منبع منو و خالی بودن query عمومی می‌شود؛ داده‌ها و مدیریت باقی می‌مانند.

| کلید تنظیمات | پیش‌فرض |
| --- | --- |
| `video_library_enabled` | `false` برای فعال‌سازی آگاهانه و سازگاری سایت‌های فعلی |
| `video_library_archive_title` | کتابخانه ویدئویی |
| `video_library_archive_description` | خالی |
| `video_library_per_page` | ۱۲؛ محدوده ۱ تا ۴۸ |

کلیدها در تب «کتابخانه ویدئویی» کنار تب‌های ماژول‌های فعلی هستند و با ذخیره تنظیمات در جدول موجود ثبت می‌شوند.

## migration و راه‌اندازی

`database/migrations/2026_10_21_000000_create_video_library_tables.php` فقط سه جدول جدید می‌سازد:

- `videos`
- `video_categories`
- `video_video_category`

migration روی دیتابیس کاری اجرا نشده است؛ در بررسی هدفمند فقط SQLite در حافظه استفاده شد. برای اعمال فقط migration این قابلیت در محیط موردنظر:

```powershell
php artisan migrate --path=database/migrations/2026_10_21_000000_create_video_library_tables.php
```

پس از اعمال migration، مدیر می‌تواند ویدئو ثبت کند، ماژول را در تنظیمات فعال کند و مقصد سیستمی آن را در منوی دلخواه اضافه کند.

## فایل‌های این تغییر

فایل‌های موجود ویرایش‌شده:

- `app/Filament/Pages/ManageSiteSettings.php`
- `app/Services/SitemapService.php`
- `bootstrap/providers.php`
- `routes/web.php`

فایل‌های جدید:

- `app/Contracts/VideoProviderAdapter.php`
- `app/Models/Video.php`
- `app/Models/VideoCategory.php`
- `app/Policies/VideoPolicy.php`
- `app/Policies/VideoCategoryPolicy.php`
- `app/Providers/VideoLibraryServiceProvider.php`
- `app/Rules/VideoRelatedUrl.php`
- `app/Services/Video/AparatVideoProvider.php`
- `app/Services/Video/VideoDuration.php`
- `app/Services/VideoLibrarySettings.php`
- `app/Services/VideoQueryService.php`
- `app/Filament/Resources/VideoResource.php`
- `app/Filament/Resources/VideoResource/Pages/ListVideos.php`
- `app/Filament/Resources/VideoResource/Pages/CreateVideo.php`
- `app/Filament/Resources/VideoResource/Pages/EditVideo.php`
- `app/Filament/Resources/VideoCategoryResource.php`
- `app/Filament/Resources/VideoCategoryResource/Pages/ManageVideoCategories.php`
- `app/Http/Controllers/VideoController.php`
- `database/migrations/2026_10_21_000000_create_video_library_tables.php`
- `resources/views/videos/index.blade.php`
- `resources/views/filament/forms/components/video-provider-thumbnail.blade.php`
- `public/images/video-placeholder.svg`
- `tests/Unit/AparatVideoProviderTest.php`
- `tests/Feature/VideoLibraryTest.php`
- `docs/video-library-phase1-implementation-2026-10-21.md`

تغییرات موجود پیش از شروع در `PageResource.php`، `TemplateResource.php`، `resources/css/app.css`، `resources/js/app.js` و `partials/blocks/faq.blade.php` متعلق به این پیاده‌سازی نیستند و دست‌نخورده باقی ماندند.

## بررسی‌ها و مرز اطمینان

- syntax فایل‌های PHP این قابلیت، `route:list` در محیط testing و `git diff --check` بررسی شدند.
- Pint روی فایل‌های PHP جدید و `bootstrap/providers.php` موفق بود. بررسی سبک فایل قدیمی `ManageSiteSettings.php` موارد قبلی مانند فاصله بعد از عملگر نفی را گزارش کرد؛ برای این ماژول فرمت کل فایل تغییر نکرد.
- ۹ تست هدفمند در دو فایل بالا، با ۸۴ assertion موفق شدند؛ شامل URL و HTML نامعتبر، thumbnail نامطمئن، تبدیل مدت، عدم تکرار، پایداری نامک، sanitizer، وضعیت انتشار، toggle، سایت‌مپ، policy، ایجاد/ویرایش Livewire، رابطه دسته، اولویت رسانه مشترک و حفظ رسانه پس از حذف ویدئو.
- در تست‌ها provider خارجی mock است و فقط migrationهای کوچک موردنیاز در SQLite حافظه اجرا می‌شوند. full regression suite، build فرانت‌اند، تست مرورگر و migration دیتابیس کاری اجرا نشدند.
- اتصال زنده آپارات، کیفیت داده‌های metadata و رفتار واقعی iframe در این مرحله تأیید نشده‌اند. استخراج HTML best-effort است و قرارداد API تضمین‌شده محسوب نمی‌شود؛ اتصال به API رسمی پایدار در صورت تأیید قرارداد آن Pending است. ثبت دستی اطلاعات و تصویر جایگزین مستقل از این دریافت کار می‌کنند.
- طراحی نهایی کارت، Click-to-Play، modal، فیلتر/جست‌وجوی زنده، اتصال ویدئو به Project/Product/Service، بلوک‌های جدید و سیاست VideoObject/SEO طبق دامنه درخواست به فاز بعد موکول شده‌اند.
