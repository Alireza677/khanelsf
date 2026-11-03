# Footer Composition — فاز ۳: مدیریت در ویرایشگر فعلی

به‌روزرسانی فاز ۴: [ظاهر Responsive و بررسی اجرایی مدیریت](footer-composition-phase-4.md) اضافه شد. دو خطای Action Container و انتقال عنصر اصلاح و Save/Reload و عملیات اصلی Editor در Livewire با دیتابیس حافظه‌ای بررسی شدند. عبارت‌های «تست اجرا نشد» در این سند سابقه پایان فاز ۳ هستند؛ تأیید مرورگر همچنان انجام نشده است.

تاریخ: 2026-11-03. مبنا: تصمیم قطعی A.1 در [گزارش معماری](KING-FOOTER-SYSTEM-SOURCE-AUDIT-AND-IMPLEMENTATION-PLAN.md)، [Schema فازهای ۰ و ۱](footer-composition-phase-0-1.md) و [Runtime فاز ۲](footer-composition-phase-2.md).

وضعیت: کد مدیریت Composition در همان Schema فرم بلوک `site_footer` پیاده‌سازی شد. به درخواست صریح این مرحله، هیچ تست خودکار یا تست مرورگر اجرا نشده است؛ این سند ادعای تأیید اجرایی Save/Reload، تعامل Livewire یا تأیید بصری ندارد. بررسی‌های این مرحله فقط خواندن سورس، نحو PHP، قالب‌بندی فایل‌های PHP تغییرکرده و whitespace بوده‌اند.

## فایل‌های این فاز

| فایل | تغییر |
|---|---|
| `app/CMS/Blocks/SiteFooter/SiteFooterBlock.php` | اتصال به Schema مدیریت Composition؛ نگهداری فرم Legacy مستقل؛ برداشتن حداقل سه منوی اجباری و padding آن، با حفظ حداکثر سه منوی Legacy. |
| `app/CMS/Blocks/SiteFooter/SiteFooterCompositionEditor.php` | helper محلی فرم Filament 3: ستون‌ها، عناصر، Content/Design/Advanced، انتخاب منابع مشترک، انتقال، هویت، تبدیل صریح و وضعیت ذخیره. Builder یا Registry مستقلی نیست. |
| `app/CMS/Blocks/SiteFooter/SiteFooterEditorState.php` | عملیات صریح Add/Clone/Convert و ساخت شناسه در مرز Editor؛ Mapping محتوای قدیمی؛ داده ارث‌بری و تشخیص خروجی با Runtime موجود. |
| `resources/views/filament/forms/components/site-footer-editor-status.blade.php` | توضیح Preview ذخیره‌شده، dirty notice، خلاصه خطا با Focus و لینک به فیلد، بازکردن Repeaterهای بسته و Focus پس از انتقال. فقط View مدیریتی است. |
| `app/Filament/Resources/TemplateResource/Pages/EditTemplate.php` | برای `site_footer`، عنوان و توضیح Preview به «آخرین نسخه ذخیره‌شده» تغییر کرد؛ endpoint، احراز هویت، Publication و Runtime همان مسیر قبلی هستند. |
| همین سند و سه سند پیشین Footer | ثبت وضعیت اجرا و ارجاع به گزارش فاز ۳؛ نتایج تست فازهای پیشین صرفاً سابقه همان مراحل باقی می‌مانند. |

Schema v2، Normalizerها، Hydrator، Save Manager، Registry، Resolver، Runtime، Renderer، Seeder، Migration، CSS و assets فرانت‌اند در این مرحله تغییر نکردند. داده سایت و تنظیم Default نیز از ابزارها دست‌کاری نشدند. تغییرات دیگر Working Tree متعلق به این فاز نیستند.

## امکانات مدیر

- افزودن، حذف با تأیید، کپی کامل و مرتب‌کردن ستون‌ها. عنوان ستون اختیاری و در خروجی قابل نمایش است. کپی، شناسه ستون و تمام عناصر داخل آن را تازه می‌کند؛ محتوای اصلی و Referenceها حفظ می‌شوند.
- افزودن عناصر Branding، Navigation، Contact، Social Links، Rich Text، Image/Trust Badge و Static Map در بخش اصلی؛ Copyright و Legal Links در نوار پایین. این محدودیت ناحیه از قرارداد موجود می‌آید؛ Type با تغییر Select روی محتوای پر جایگزین نمی‌شود و داده نوع قبلی بی‌صدا پاک نمی‌شود.
- ویرایش محتوا، حذف با تأیید، فعال/غیرفعال‌سازی و جابه‌جایی دکمه‌ای یا Drag؛ انتقال عنصر اصلی به انتهای ستون مقصد از Action هر عنصر. ستون فعلی و ستون پر در مقصدها ارائه نمی‌شوند؛ هنگام اجرای Action نیز ظرفیت دوباره بررسی می‌شود. عناصر نوار پایین در همان ناحیه مرتب می‌شوند.
- محدودیت‌های موجود ۸ ستون، ۱۲ عنصر در ستون، ۴۸ عنصر کل و ۲۰ آیتم Social/Legal رعایت می‌شوند. ستون و Composition خالی معتبر باقی می‌مانند؛ هیچ defaultItems مثبت یا refill خودکار برای Composition وجود ندارد.
- منبع `site/manual/override` با توضیح فارسی و نمایش فقط‌خواندنی مقدارهای فعلی Settings. سایت مقادیر دستی را مصرف نمی‌کند؛ دستی fallback ندارد؛ اختصاصی مقدار null/خالی را ارث می‌برد. مقدارهای Settings هنگام Save در JSON کپی نمی‌شوند. فیلدهای دستی در حالت سایت مخفی ولی قابل Dehydrate باقی می‌مانند تا جابه‌جایی منبع، مقدار قبلی را حذف نکند.
- برای Contact، انتخاب و ترتیب فیلدها از لیست چندانتخابی حفظ می‌شود؛ حذف و انتخاب دوباره، فیلد را به انتهای ترتیب می‌برد. Override هر فیلد مستقل است. Social override مطابق Runtime، جایگزینی کل لیست با items غیرخالی است؛ لیست خالی ارث می‌برد، Merge حدسی شبکه‌ها وجود ندارد.
- Media Picker شناسه‌محور، Iconsax Picker، RichEditor و ActionPicker همان کامپوننت‌های مشترک موجود King هستند. منو از مدل Navigation فعلی انتخاب می‌شود؛ مرجع منوی غیرفعال/ناموجود با هشدار حفظ می‌شود. RichEditor دکمه Upload/Embed ندارد؛ رسانه و نشان با عنصر Image انتخاب می‌شوند.
- Design فقط `container`، `spacing`، `grid.desktop/tablet/mobile` و `column.settings.span` واقعی Schema را ارائه می‌کند. Advanced هویت و محدودیت قرارداد را توضیح می‌دهد؛ کنترل ساختگی CSS/Embed/Animation افزوده نشده است. اعمال نهایی این تنظیمات در ظاهر فرانت‌اند مربوط به فاز ۴ است.

## هویت، Save و حالت‌های پنهان

شناسه دائمی Column/Element با ULID فقط هنگام Add، Clone یا تبدیل صریح تولید می‌شود. کلیدهای داخلی UUID متعلق به Repeater هستند و هویت داده محسوب نمی‌شوند. Reorder و Move شناسه دائمی را حفظ می‌کنند؛ Move داده کامل عنصر را منتقل می‌کند. Normalizer قبلی همچنان ترتیب iteration را canonical و کلیدهای Repeater را array_values می‌کند.

برای هر حالت فقط Schema فعال mount می‌شود: در Legacy، Composition یک Hidden با مقدار null است؛ در Composition، فرم nested واقعی فعال است و فیلدهای Legacy به‌صورت Hidden حفظ می‌شوند. Hidden موازی روی کل Composition یا کل settings که بتواند نتیجه Dehydration فرزندان را بازنویسی کند وجود ندارد. ذخیره از Save Manager و Normalizer فعلی می‌گذرد؛ نسخه، فیلد یا نوع ناشناخته همچنان به‌جای حذف خاموش خطا می‌دهد.

فرم موجود در Schema ولی خارج از محدوده این فاز، به فهرست افزودن وارد نشده است. عنصر `form` موجود به‌صورت داده پنهان و هشدار حفظ می‌شود و امکان غیرفعال‌سازی/حذف صریح دارد. Form Action موجود نیز فقط‌خواندنی حفظ می‌شود و تنها با اقدام صریح حذف مقصد کنار گذاشته می‌شود؛ هیچ Renderer یا Form Runtime تازه‌ای اضافه نشده است.

## Legacy و تبدیل اختیاری

`composition=null` همچنان Legacy است؛ بازکردن فرم، Save معمولی یا Preview آن را به Composition تبدیل نمی‌کند. تمام کنترل‌های قبلی در این حالت موجودند؛ فوتر عمداً بدون منو نیز دیگر مجبور به ساخت سه ردیف برای Save نیست.

Action «تبدیل اختیاری به Composition» دو انتخاب دارد: انتقال محتوای فعلی یا شروع خالی. این Action فقط State فرم را عوض می‌کند و Save/Publish نمی‌کند. Mapping، ترتیب منوها، عنوان‌ها، تماس پویا، متن About دستی یا ارثی، Badgeها با enabled و ترتیب sort_order و دو لینک حقوقی را حفظ می‌کند. عنوان قدیمی در عنوان ستون قرار می‌گیرد تا با عنوان عنصر تکرار نشود. Badgeهای زیاد در ستون‌های حداکثر ۱۲تایی تقسیم می‌شوند؛ اگر تبدیل از حدود کل قرارداد عبور کند یا Action قدیمی نامعتبر باشد، تبدیل متوقف و State قبلی حفظ می‌شود، نه اینکه بخشی از داده بریده شود.

فیلدهای Legacy در همان content باقی می‌مانند. Action تأییدشونده «بازگشت به محتوای قدیمی» Composition فعلی را از فرم کنار می‌گذارد و snapshot قدیمی را فعال می‌کند؛ اعمال در سایت به Save صریح نیاز دارد. این snapshot محتوای قبلی است، نه نسخه پشتیبان از آخرین ویرایش‌های Composition؛ لغو تغییر ذخیره‌نشده با Reload امکان‌پذیر است. تفاوت ظاهری تبدیل در Modal اعلام می‌شود.

## هشدار، Preview و UX

Skill سراسری `C:/Users/Marco/.agents/skills/ui-ux-pro-max/SKILL.md` خوانده شد. جست‌وجوی محلی `dragging movements keyboard alternative` در domain=ux، نتیجه مشخص WCAG Dragging Movements داد؛ دکمه‌های بالا/پایین و انتخاب مقصد به‌عنوان جایگزین Drag در همین Repeaterها اعمال شدند. فرم فارسی و تلفن/ایمیل LTR هستند، کنترل‌های تخریبی تأیید دارند و برای انتقال Notification و بازگردانی Focus به عنصر مقصد اضافه شده است. کتابخانه UI، Icon یا Design System موازی اضافه نشده است.

هر عنصر وضعیت محتوای قابل نمایش/خالی/غیرفعال دارد. این تشخیص به همان Normalizer و `SiteFooterCompositionRuntime` متصل است و Preview Engine دیگری نیست؛ وضعیت Form-only یا داده ناقص نیز هشدار می‌دهد. بررسی‌ها در مدیریت Read-only هستند و Query برای Resolve منابع ممکن است رخ دهد؛ این helper ادعای Query-free بودن ندارد و وارد Normalizer یا Blade فرانت‌اند نشده است.

Media ناموجود، منوی حذف‌شده/غیرفعال، لینک بدون عنوان و Action نامعتبر/ناموجود/منتشرنشده هشدار مجاور دارند. داده مرجع صرفاً به دلیل غیرفعال یا ناموجودبودن خودکار پاک نمی‌شود؛ Validation فعلی ActionPicker ممکن است برای مقصد حذف‌شده، اصلاح یا حذف صریح را پیش از Save لازم کند. هشدار کلی عنصر به معنی قابل نمایش بودن تمام زیرمحتواها نیست؛ متن UI این تفاوت را اعلام می‌کند.

Preview همان رکورد ذخیره‌شده را با endpoint و Runtime قبلی باز می‌کند. عنوان دکمه، توضیح Modal و راهنمای داخل فرم این موضوع را روشن می‌کنند. `wire:dirty` تغییرات ذخیره‌نشده را یادآوری می‌کند؛ Save خودکار، انتشار خودکار یا ارسال payload ذخیره‌نشده برای Preview اضافه نشده است.

خلاصه خطاهای Footer، با Focus و لینک به فیلد خطادار در فرم قرار دارد و Repeaterهای بسته را برای دسترسی به فیلد باز می‌کند. خطاهای ساختاری Normalizer نیز در Summary دیده می‌شوند؛ چون Normalizer pathهای canonical می‌دهد، همه این خطاهای ساختاری الزاماً یک input قابل Focus ندارند. رفتار واقعی Focus و Livewire در مرورگر بررسی نشده است. ایرادهای قبلی Canvas عمومی و Media Dialog مشترک در گزارش معماری همچنان کار مستقل‌اند؛ در این فاز برای Footer نسخه جایگزین آن‌ها ساخته نشده است.

## بررسی انجام‌شده و کار باقی‌مانده

طبق درخواست «تست نمی‌خوام»، PHPUnit، تست Livewire، تست دیتابیس و تست مرورگر اجرا نشدند؛ تست تازه نیز نوشته نشد. Syntax چهار فایل PHP این فاز و قالب‌بندی محدود آن‌ها بررسی شد و diff whitespace بررسی شد. اجرای Laravel، Build، Seed، Migration، Commit و Deploy انجام نشد. نتایج ۱۶۱ تست فاز ۲، نتیجه آزمون این UI جدید محسوب نمی‌شوند؛ سه شکست تاریخی BlockFoundationTest نیز در این مرحله بررسی مجدد نشدند.

کد فاز ۳ برای ادامه فاز ۴ آماده است: همان Composition و Runtime قابل مصرف‌اند و مدیریت همه عناصر پایه وجود دارد. تأیید اجرایی Add/Clone/Move/Save/Reload، تغییر source و بازگشت Legacy و بررسی دسترس‌پذیری واقعی هنوز انجام نشده است. فاز ۴ باید ظاهر، Tokens، Grid متغیر، span، فاصله‌ها و Responsive را با حفظ CSS و خروجی Legacy تکمیل کند. Footer استاندارد نصب تازه هنوز تا تکمیل فازهای بعد و Bootstrap غیرمخرب، Requirement کامل‌شده محسوب نمی‌شود.
