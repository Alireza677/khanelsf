# بازطراحی نمایشی شاخص‌های پروژه

تاریخ: ۲۰۲۶-۱۱-۰۳

## یافته‌های سورس پیش از تغییر

| موضوع | مسیر و قرارداد تأییدشده |
| --- | --- |
| Block | `app/CMS/Blocks/Project/ProjectMetricsBlock.php`، کلید `project_metrics` |
| ثبت Block | `app/Providers/BlockServiceProvider.php` |
| Blade | `resources/views/partials/blocks/project_metrics.blade.php` |
| استایل | `resources/css/app.css`؛ قواعد مشترک `stats-grid` و `stats-item`، سپس قواعد اختصاصی `project-achievements` و overrideهای Responsive |
| مسیر رندر | `ProjectController::show()` ← `ProjectTemplateContextBuilder::build()` ← `TemplateService::viewOrFallback()` ← `templates/render.blade.php` ← `partials/page-blocks.blade.php` ← View ثبت‌شدهٔ Block |
| Context | `templateContext.model` همان `Project` است؛ Builder رابطهٔ `metrics` را بارگیری می‌کند. Blade از `$context['model']->metrics` استفاده می‌کند. |
| مدل داده | `app/Models/ProjectMetric.php`: فیلدهای `label`، `value`، `prefix`، `suffix`، `description`، `icon` و `sort_order` |
| ترتیب | رابطهٔ `Project::metrics()` در `app/Models/Project.php`: ابتدا `sort_order` و سپس `id` |
| مدیریت داده | Repeater رابطهٔ `metrics` در `app/Filament/Resources/ProjectResource.php`؛ مقدار و عنوان اجباری‌اند، آیکن و پیشوند/پسوند اختیاری‌اند. |
| Template Editor | `content.title` و تنظیم مشترک `settings.heading_tag`؛ `AbstractProjectBlock`، نسخهٔ Schema برابر ۱ و Template برابر `default` را نگه می‌دارد. تنظیم ستون، رنگ یا اندازهٔ آیکن در این Block وجود ندارد. |
| Recipe | `app/CMS/Templates/Recipes/ProjectCaseStudyRecipe.php` و `database/seeders/StandardProjectTemplateSeeder.php` عنوان «دستاوردها و مزایا» را تعیین می‌کنند. |

علت عدم توازن: Grid اختصاصی چهار ستون ثابت داشت؛ دو شاخص فقط دو ستون آن را اشغال می‌کردند. آیتم‌ها با خطوط مشترک جدا می‌شدند و آیکن‌ها قاب و اندازهٔ اختصاصی نداشتند. مقدار، پیشوند و پسوند هم در یک `strong` به هم چسبیده بودند.

## اعمال راهنمای ui-ux-pro-max

اسکیل سراسری از `C:/Users/Marco/.agents/skills/ui-ux-pro-max/SKILL.md` خوانده شد. جست‌وجوهای متمرکز `ux` و `html-tailwind` دربارهٔ سلسله‌مراتب فونت، ساختار و فاصلهٔ کارت، Reflow و طراحی Responsive بررسی شدند. توصیه‌های عمومی سازگار با CSS/Tailwind فعلی پروژه استفاده شدند؛ کلاس‌ها یا APIهای مختص Tailwind 4 وارد پروژه نشدند.

- **Typography / Visual Hierarchy:** مقدار با وزن ۸۰۰ و اندازهٔ سیال ۳۶ تا ۴۸ پیکسل؛ پیشوند/پسوند ۱۶ پیکسل؛ عنوان شاخص ۱۶ پیکسل با رنگ ثانویهٔ مشتق از متن Theme. عنوان سکشن از اندازهٔ Heading فعلی Theme پیروی می‌کند.
- **Spacing / Card Layout:** فاصلهٔ افقی ۲۴ پیکسل، Padding داخلی ۲۴ پیکسل، Radius برابر ۱۸ پیکسل و ارتفاع وابسته به محتوا با حداقل ۲۰۰ پیکسل؛ Grid کارت‌های هر ردیف را هم‌ارتفاع می‌کند. ارتفاع ثابت، حذف متن یا Line Clamp اضافه نشده است.
- **Responsive Design:** `auto-fit` با حداقل عرض هدف ۲۵۶ پیکسل و سقف حداقل برابر عرض Container؛ تعداد کمتر کارت، فضای ردیف را پر می‌کند و Container باریک به یک ستون می‌رسد.
- **Floating Icon:** قاب Squircle به اندازهٔ ۶۴ پیکسل و از عرض ۷۰۰ پیکسل به بالا ۷۲ پیکسل؛ Glyph برابر ۳۲/۳۶ پیکسل. موقعیت absolute در بالای کارت با `translateY(-50%)` بیرون‌زدگی واقعی می‌سازد. فضای نصف قاب در Padding بالای Grid و فاصلهٔ ردیف‌ها رزرو شده است؛ محتوا نیز ۲۴ پیکسل بعد از پایین قاب شروع می‌شود.
- **Theme:** رنگ‌ها از `--theme-background`، `--theme-text`، `--theme-heading` و `--theme-accent` می‌آیند. Border، سایه و رنگ ثانویه با `color-mix` از همان توکن‌ها مشتق می‌شوند. رنگ ثابت یا استایل اختصاصی خانه LSF اضافه نشده است.
- **RTL:** جست‌وجوی RTL در دیتاست اسکیل نتیجهٔ مرتبط نداشت؛ این قسمت با قواعد عمومی CSS و قرارداد فعلی RTL پروژه پیاده شد. موقعیت افقی آیکن و فاصله‌ها از Logical Properties استفاده می‌کنند. مقدار و Affixها با `bdi dir="auto"` جدا می‌شوند؛ ترتیب DOM همچنان پیشوند، مقدار، پسوند است. گروه مقدار/واحد با Flex در حالت معمول یکپارچه می‌ماند؛ فقط محتوای طولانی در عرض ناکافی Reflow می‌شود. عنوان و توضیح محدودیت `nowrap` ندارند.
- **Motion / Accessibility:** آیکن تزئینی از Accessibility Tree پنهان است. Hover فقط Border و Shadow را عوض می‌کند و آیکن یا کارت جابه‌جا نمی‌شود. Transition فقط در `prefers-reduced-motion: no-preference` فعال است.

قواعد جدید فقط زیر `project-achievements` اعمال می‌شوند؛ Block عمومی `stats_section` ظاهر و رفتار قبلی خود را نگه می‌دارد. آیکن ذخیره‌شده با Partial مشترک `_icon` نمایش داده می‌شود؛ برای مقدار خالی، آیکن عمومی نمودار با همان سازوکار موجود نمایش داده می‌شود و داده‌ای ذخیره نمی‌شود.

## محدوده و سازگاری

تغییر اجرایی فقط در Blade موجود و CSS آن است. منبع داده، Query، Context Builder، Template Runtime، Block Registry، Schema Version، Recipe، تنظیمات ذخیره‌شده و داده‌های پروژه تغییر نکرده‌اند. Migration، Block، وابستگی یا JavaScript جدید ایجاد نشده است. بخش بدون شاخص همچنان رندر نمی‌شود و توضیحات اختیاری شاخص‌ها حفظ شده‌اند. مقدار و واحد از دادهٔ پروژه خوانده می‌شوند؛ استخراج یا حدس واحد از رشتهٔ آزاد `value` انجام نمی‌شود.

## شواهد اعتبارسنجی

- `npm run build`: موفق؛ هشدار غیرمسدودکنندهٔ اندازهٔ Chunk مربوط به فایل `hero-dotted-surface` است.
- تست‌های موجود `ProjectProductionBlockBehaviorTest`، `HeadingContractTest` و `TemplateRecipeInfrastructureTest`: مجموع ۱۹ تست و ۲۲۸ Assertion موفق.
- `git diff --check`: موفق.
- بررسی موقت در Chrome Headless با خروجی واقعی همین Blade، CSS ساخته‌شده، آیکن‌های موجود و فونت پیش‌فرض بسته‌بندی‌شدهٔ CMS؛ داده‌های آزمایشی فقط در حافظه ساخته شدند و هیچ Seed یا Migration اجرا نشد.
- عرض‌های ۳۲۰، ۳۷۵، ۶۶۷، ۷۶۸، ۱۰۲۴ و ۱۲۸۰؛ تعداد ۱، ۲، ۳، ۴ و ۷ شاخص؛ دادهٔ فارسی، مقدار لاتین منفی، واحد بلند، مقدار/عنوان بدون فاصله و توضیح چندخطی بررسی شدند. اسکرول افقی یا برخورد آیکن با متن و ردیف قبلی مشاهده نشد؛ کارت‌های هم‌ردیف هم‌ارتفاع بودند. دو کارت در دسکتاپ دو ستون برابر را پر کردند.
- بیرون‌زدگی اندازه‌گیری‌شده ۳۱ پیکسل از قاب ۶۴ پیکسلی و ۳۵ پیکسل از قاب ۷۲ پیکسلی بود؛ اختلاف یک پیکسل مربوط به Border کارت است. مرکز افقی قاب با کارت منطبق بود. حالت والد `overflow: hidden` نیز بررسی شد؛ قاب‌ها داخل فضای رزروشدهٔ سکشن باقی ماندند.
- تصاویر روشن و تیره با توکن‌های آزمایشی بازبینی شدند؛ در Reduced Motion مقدار Transition برابر `0s` بود. حالت LTR نیز اندازه‌گیری شد.

این شواهد، بررسی محلیِ رندر با Fixture هستند. صفحهٔ واقعی Frontend یا Template Preview با داده‌ها، فونت سفارشی و تنظیمات ذخیره‌شدهٔ سایت باز نشده است؛ تأیید آن محیط‌ها جداگانه باقی می‌ماند.
