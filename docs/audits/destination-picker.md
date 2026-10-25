# ممیزی و توسعه انتخابگر مقصد — ۲۰۲۶/۱۰/۲۵

این ممیزی قبل از تغییر کد انجام شد. دامنه: کل `app/CMS`، فرم‌های `app/Filament`، Providerها، Blade و JavaScript مدیریت و تست‌های موجود. جست‌وجوی نام فیلدهای action/link/url/href/target/reference_id/form_id و فیلدهای مرجع مکمل، علاوه بر بررسی Registry انجام شد. مسیرهای جدول نسبت به ریشه مخزن هستند؛ مسیر state هر بلوک نسبت به `blocks.{block-key}.data` است. `*` کلید مستقل آیتم Repeater است.

## ماتریس مقصدهای قابل انتخاب

در جدول، «همه» یعنی page/project/product/service/form/custom_url/anchor/email/phone؛ گزینه‌های مجاز هر مصرف‌کننده همچنان از allowedTypes آن تعیین می‌شوند. روش قبلی ActionPicker جست‌وجوی حداقل دو حرف، حداکثر ۲۰ نتیجه و بدون صفحه‌بندی بود. تمام ردیف‌های ActionPicker با تغییر مشترک ارتقا می‌یابند.

| مصرف‌کننده | تعریف سورس | مسیر دقیق | مقصد / انتخاب قبلی | تصمیم |
|---|---|---|---|---|
| Hero: default/hero_1/hero_2/hero_3 | app/CMS/Blocks/Hero/HeroV2EditorSchema.php | content.primary_cta.action.reference_id؛ content.secondary_cta.action.reference_id | همه، ActionPicker | مودال مشترک؛ visibility هر Variant حفظ می‌شود |
| Hero گزینه‌ها | همان | content.selector.items.*.action.reference_id | همه، ActionPicker | ارتقا؛ state هر آیتم مستقل |
| Hero لینک‌های پایین | همان | content.social_links.*.action.reference_id | همه، ActionPicker | ارتقا |
| CTA اصلی و ثانویه | app/CMS/Blocks/CTA/CTABlock.php | content.primary_cta.action.reference_id؛ content.secondary_cta.action.reference_id | همه، ActionPicker | ارتقا |
| Text & Image | app/CMS/Blocks/TextImage/TextImageBlock.php | content.button.action.reference_id | همه، ActionPicker | ارتقا |
| Feature Grid کارت‌های static، تمام قالب‌ها | app/CMS/Blocks/FeatureGrid/FeatureGridBlock.php | content.items.*.action.reference_id | همه، ActionPicker | ارتقا؛ کارت ویدئو همچنان پخش‌کننده مستقل دارد |
| Pricing Cards | app/CMS/Blocks/Pricing/PricingCardsBlock.php | content.cards.*.action.reference_id | همه، ActionPicker | ارتقا |
| Service Header | app/CMS/Blocks/Service/ServiceHeaderBlock.php | settings.primary_action.action.reference_id؛ settings.secondary_action.action.reference_id | همه، ActionPicker | ارتقا |
| Project Header | app/CMS/Blocks/Project/ProjectHeaderBlock.php | settings.primary_action.action.reference_id؛ settings.secondary_action.action.reference_id | همه، ActionPicker | ارتقا؛ فیلدهای marketing/legacy حفظ می‌شوند |
| Business Network Map | app/CMS/Blocks/BusinessNetwork/BusinessNetworkMapBlock.php | settings.empty_state_action.reference_id | همه، ActionPicker | ارتقا |
| Site Header | app/CMS/Blocks/SiteHeader/SiteHeaderBlock.php | content.top_actions.0.action.reference_id؛ content.top_actions.1.action.reference_id؛ content.primary_action.action.reference_id؛ content.secondary_action.action.reference_id | ActionPicker، allowedTypes فعلی | ارتقا |
| Site Footer | app/CMS/Blocks/SiteFooter/SiteFooterBlock.php | content.privacy_action.reference_id؛ content.terms_action.reference_id | ActionPicker، allowedTypes فعلی | ارتقا |
| Testimonials برگه / قالب | app/Filament/Resources/PageResource.php؛ app/Filament/Resources/TemplateResource.php | cta_action.reference_id | همه، ActionPicker | ارتقا در هر دو ویرایشگر |
| Form embed | app/CMS/Blocks/Form/FormBlock.php | content.form_id | فرم منتشرشده، Select با بارگذاری همه | همان Field مشترک با target=form؛ قرارداد form_id حفظ می‌شود |
| ویرایشگر Rich Text | resources/views/filament/theme.blade.php؛ app/Http/Controllers/Admin/InternalLinkSearchController.php | href داخل HTML/Trix | page/project/product/service، endpoint جست‌وجوی مستقل | فهرست اولیه و صفحه‌بندی با همان منبع؛ HTML همچنان URL ذخیره می‌کند |
| Menu Editor | app/Filament/Resources/MenuResource/Pages/EditMenu.php؛ resources/views/filament/resources/menu-resource/pages/edit-menu.blade.php | selectedPageIds؛ selectedServiceIds؛ selectedSourceKeys → MenuItem.reference_id/reference_type/source_key | فهرست برگه؛ درخت خدمت با lazy pagination؛ system source list | از قبل بدون تایپ قابل مرور است؛ قرارداد چندانتخابی و درخت مستقل حفظ می‌شود |

## تمام بلوک‌های دیگر Registry

مرجع کامل ثبت: `app/Providers/BlockServiceProvider.php` (۳۱ بلوک). علاوه بر ۱۲ خانواده بالا، بلوک‌های زیر با سورس‌های هم‌نام در `app/CMS/Blocks/{Project,Product,Service,ProjectDiscovery}` بررسی شدند:

| بلوک | لینک / مرجع | نیاز به ارتقا |
|---|---|---|
| project_overview، project_metrics، project_story | محتوای پروژه جاری؛ RichEditor در صورت وجود از مسیر Trix عمومی | مقصد اکشن جدا ندارد |
| project_services | خدمت‌های مرتبط از Domain | مقصد دکمه از داده Domain ساخته می‌شود |
| project_gallery | رسانه مرتبط | انتخاب مقصد داخلی ندارد |
| related_projects | پروژه‌های مرتبط با زمینه جاری | لینک Runtime، بدون انتخاب مقصد دستی |
| project_discovery_grid | مجموعه پروژه و فیلترهای کشف | لینک Runtime؛ تنظیمات نمایش، بدون destination picker |
| product_header | محصول جاری و عمل سبد خرید | اکشن تجاری، بدون انتخاب مقصد |
| product_overview، product_specifications | اطلاعات محصول جاری | مقصد دستی ندارد |
| product_gallery، product_documents | رسانه / فایل محصول | URL دارایی است |
| product_related | محصولات مرتبط | لینک Runtime |
| service_overview، service_benefits، service_process، service_deliverables | محتوای خدمت جاری | مقصد دستی ندارد |
| service_projects، related_services | روابط Domain | لینک Runtime |
| service_gallery | رسانه خدمت | مقصد داخلی ندارد |

## مسیرهای مستقل و موارد بدون تغییر قرارداد

| محل | فیلد | رفتار و دلیل |
|---|---|---|
| HeroBlock.php متدهای private قدیمی | primary_button_url؛ secondary_button_url؛ selector_items.*.url؛ hero_1_social_links.*.url | schema فعال از HeroV2EditorSchema می‌آید؛ متدهای قدیمی فراخوانی نمی‌شوند. Normalizer/Legacy adapter نگهداری می‌شود |
| ProjectHeaderBlock.php | settings.cta_target؛ settings.secondary_cta_target | متن URL/anchor قدیمی؛ ActionPicker مرجع‌دار در همان بلوک موجود است |
| SiteFooterBlock.php | content.badges.*.url | لینک اختیاری نمادها و مجوزها، قرارداد URL |
| PageResource.php: featured_projects/products/galleries | button_url | URL دلخواه بخش؛ کارت‌ها لینک Domain دارند؛ تبدیل آن به reference بازطراحی قرارداد خواهد بود |
| FeatureGridBlock.php، FeatureGridVideoPicker.php | content.dynamic_button_overrides.*.record_id؛ content.items.*.video_id؛ dynamic filters | record_id از ابتدا فهرست دارد؛ video_id جست‌وجوی اولیه اجباری داشت و به Field مشترک ارتقا یافت؛ قراردادهای Post/Project/Video مستقل می‌مانند |
| SiteHeaderBlock.php | settings.menu_id | انتخاب منو، از ابتدا فهرست‌دار؛ navigation registry ادغام نمی‌شود |
| PageResource.php / TemplateResource.php | gallery.images.*.url؛ avatar؛ media.url | دارایی تصویر، نه مقصد لینک |
| TemplateResource.php | conditions.item_id/category_id؛ template_content_grid تنظیمات منبع؛ template_single_*؛ template_archive_header؛ template_shop_complete؛ template_add_to_cart | شرط/زمینه قالب یا لینک خودکار Domain؛ مقصد Action نیست |
| PageResource.php | stats_section؛ faq؛ gallery؛ custom_html | مقصد مرجع‌دار ندارند؛ HTML سفارشی آزاد حفظ می‌شود |
| TemplateResource.php | faq؛ gallery؛ custom_html | مقصد مرجع‌دار ندارند |
| MenuItemResource.php | url؛ target | URL سفارشی / _self و _blank؛ مقصدهای مرجع‌دار از EditMenu مدیریت می‌شوند |
| ManageSiteSettings.php | header_cta_url؛ social_*_url؛ target_url؛ zarinpal_callback_url | URLهای تنظیمات/redirect/payment؛ بدون مرجع داخلی |
| RedirectResource.php | target_url | مقصد ریدایرکت، قرارداد URL |
| ProductResource.php / ProjectResource.php | external_url؛ gallery.*.url / thumbnail_url | URL خارجی / رسانه |
| VideoResource.php / GalleryResource.php | source_url؛ related_url؛ video_url؛ thumbnail_url | رسانه / URL دلخواه |
| LeadResource.php / FormSubmissionResource.php | form_id و فیلتر form_id | رابطه CRM / فیلتر گزارش، نه مقصد محتوا؛ از ابتدا فهرست دارد |

## معماری موجود و تصمیم پیاده‌سازی

`ActionTargetRegistry` و Providerهای Page/Project/Shop/Service/Form، Resolverها و `ActionDestination` بدون تغییر می‌مانند. `InternalLinkSearchRegistry` مستقل است. قابلیت browse به‌صورت interface اختیاری در همان چهار Source اضافه می‌شود؛ API قدیمی search و خروجی Rich Text حفظ می‌شوند. فرم در ActionPickerService مانند قبل با قواعد انتشار خودش مدیریت می‌شود. هیچ عنوان یا URL جایگزین reference_id در Action JSON نمی‌شود.

PageResource و TemplateResource هر دو schema را از BlockRegistry می‌گیرند. AbstractBlock/BlockInspectorTabs و BlockBuilder مالک scope و lifecycle هستند. Field جدید به مسیر مطلق همان فیلد متصل می‌شود؛ query/page/results فقط state موقت UI هستند و وارد JSON، Snapshot یا History نمی‌شوند. Duplicate، reorder، undo/redo و بازیابی نسخه همچنان از مکانیزم‌های فعلی عبور می‌کنند.

## نتیجه پیاده‌سازی

تمام ActionPickerهای جدول اول از یک `ReferenceTargetPicker` و یک Blade/Alpine browser استفاده می‌کنند. فیلدهای قدیمی `content.form_id` و `content.items.*.video_id` نیز همان UI را با کلید ذخیره‌سازی قبلی دارند. برای اتصال تک‌تک بلوک‌ها schema موازی ساخته نشد. تمام چهار Variant فعال Hero از HeroV2EditorSchema مشترک عبور می‌کنند.

هر بار بازکردن، صفحه اول بدون query دریافت می‌شود. اندازه صفحه ۲۰ است؛ total/last_page از سرور می‌آید و هیچ سقف کلی برای تعداد مقصدهای قابل پیمایش اعمال نشده است. جست‌وجو نام/عنوان، slug و ID را پوشش می‌دهد؛ محصول SKU نیز دارد. کاراکترهای wildcard به‌صورت literal جست‌وجو می‌شوند. Project فیلتر دسته فعال واقعی Domain دارد. Service زنجیره والدها را فقط برای آیتم‌های صفحه جاری، با واکشی گروهی و محافظت چرخه، نمایش می‌دهد. فرم‌های منتشرنشده دیده می‌شوند ولی غیرفعال‌اند. سایر Domainها از همان published scope و availability فعلی Source استفاده می‌کنند؛ مقصد فاقد URL معتبر غیرفعال نمایش داده می‌شود.

حالت خطا با حالت خالی متفاوت است و retry دارد. درخواست‌های قبلی لغو می‌شوند؛ پاسخ دیررس نمی‌تواند فهرست یا عنوان جدید را جایگزین کند. انتخاب/پاک‌کردن فقط state فیلد مالک را تغییر می‌دهد. نتایج با `x-text` نمایش داده می‌شوند. مودال native dialog در top layer باز می‌شود؛ محدودیت overflow و transform ظرف Inspector آن را نمی‌بُرد. Escape، محصوربودن Tab، قفل اسکرول، بازگشت Focus و ابعاد موبایل/دسکتاپ پوشش داده شده‌اند.

Trix همان Blade و Alpine component را در پنجره لینک خود استفاده می‌کند. API قدیمی `?q=` همچنان خروجی قبلی دارد؛ API جدید `?target=...&page=...&q=...` پاسخ صفحه‌بندی‌شده می‌دهد. انتخاب در Trix طبق قرارداد قبلی URL را در HTML قرار می‌دهد، ولی ActionPicker فقط reference_id را در Action JSON ذخیره می‌کند. Trix به چهار نوع Registry فعلی محدود می‌ماند؛ Post/Form/Video به Registry لینک داخلی آن اضافه نشده‌اند. ویدئو فقط در فیلد مستقل Feature Grid قابل مرور است؛ نوع جدیدی به Action Foundation اضافه نشده است.

endpoint جدید هم مدیر بودن و هم فعال بودن حساب را بررسی می‌کند. خروجی مقصد و عنوان انتخاب‌شده `private, no-store` است. Validation سرور، انتخاب مقصد نامعتبر، حذف‌شده یا غیرقابل‌انتشار را رد می‌کند؛ غیرفعال‌کردن دکمه در مرورگر تنها کنترل دسترسی نیست.

## فایل‌های تغییرکرده و جدید

```text
app/CMS/Actions/Filament/ActionPicker.php
app/CMS/Actions/Filament/ActionPickerService.php
app/CMS/Actions/Filament/ReferenceTargetPicker.php
app/CMS/Blocks/Form/FormBlock.php
app/CMS/Blocks/FeatureGrid/FeatureGridVideoPicker.php
app/CMS/InternalLinks/Contracts/BrowsesInternalLinkTargets.php
app/CMS/InternalLinks/Sources/PageInternalLinkSource.php
app/CMS/InternalLinks/Sources/ProjectInternalLinkSource.php
app/CMS/InternalLinks/Sources/ProductInternalLinkSource.php
app/CMS/InternalLinks/Sources/ServiceInternalLinkSource.php
app/CMS/InternalLinks/Support/InternalLinkSourceSupport.php
app/Http/Controllers/Admin/InternalLinkSearchController.php
app/Providers/Filament/AdminPanelProvider.php
resources/views/filament/forms/components/reference-target-picker.blade.php
resources/views/filament/forms/components/destination-browser.blade.php
resources/views/filament/theme.blade.php
resources/js/filament/destination-picker.js
public/js/app/components/destination-target-picker.js
tests/Browser/DestinationPickerBrowserTest.php
tests/Feature/DestinationBrowserTest.php
tests/Feature/FormBlockTest.php
tests/Feature/FeatureGridVideoTest.php
tests/Fixtures/DestinationPickerRepeaterComponent.php
tests/js/destination-picker.test.js
docs/audits/destination-picker.md
```

فایل JavaScript منتشرشده با سورس SHA256 یکسان دارد. URL کامپوننت hash محتوا دارد تا cache نسخه قبلی باقی نماند. پس از Deploy مانند سایر Assetهای Filament، `php artisan filament:assets` لازم است؛ خروجی منتشرشده نیز در تغییرات حاضر قرار دارد. Migration، تغییر schema، تغییر Resolver یا بازنویسی داده‌های ذخیره‌شده انجام نشده است.

## نتایج آزمون‌ها

| بررسی | نتیجه |
|---|---|
| مجموعه نهایی انتخابگر: DestinationPickerBrowserTest، DestinationBrowserTest، ActionPickerIntegrationTest، FormBlockTest، FeatureGridVideoTest، InternalLinkSearchProviderTest | **۴۴ تست، ۴۹۰ assertion، همگی موفق** |
| کل تست‌های JavaScript در tests/js | **۳۸ تست موفق**؛ شامل ۶ تست جدید انتخابگر |
| رگرسیون وسیع‌تر PHP/Livewire/Runtime/Legacy | **۲۷۸ موفق از ۲۸۲**؛ چهار شکست پایه زیر؛ یک تست قدیمی دیگر که شکست آن قبلاً تأیید شده بود از این اجرای وسیع کنار گذاشته شد |
| Build | `npm run build` موفق؛ هشدار اندازه chunk مربوط به hero-dotted-surface حدود ۵۰۸KB، غیرمسدودکننده |
| انتشار Asset مدیریت | `php artisan filament:assets` موفق |
| قالب‌بندی و whitespace | Pint و `git diff --check` در بررسی نهایی |

اجرای وسیع با محدودیت پیش‌فرض ۱۲۸MB به سقف حافظه رسید؛ اجرای تکمیل‌شده با `php -d memory_limit=512M` انجام شد و تنظیم دائمی محیط تغییر نکرد.

پنج شکست زیر روی فایل‌های اصلی **HEAD قبل از تغییر** نیز مستقلاً بازتولید شدند. پس از بررسی پایه، تمام فایل‌های پیاده‌سازی برگردانده شدند. هیچ‌یک برای سبزکردن تست‌ها دست‌کاری نشد:

| تست پایه | اختلاف مورد انتظار قدیمی با سورس فعلی |
|---|---|
| BlockBuilderEditorTest::test_registered_static_and_dynamic_blocks_render_as_canvas_cards_with_inspectors | انتظار `<small>hero</small>` در نمای Canvas |
| CTAV2Test::test_image_cta_uses_a_viewport_surface_with_a_bounded_inner_container | انتظار ساختار قدیمی container در layouts/app |
| FeatureGridActionMigrationTest::test_item_rich_text_and_legacy_text_use_shared_safe_renderer | انتظار نبود هر `<script>`، درحالی‌که اسکریپت قانونی پخش ویدئو در خروجی موجود است |
| HeroV2EditorTest::test_hero_one_appearance_transitions_show_only_the_active_mode_and_preserve_effect_state | انتظار متن راهنمای قدیمی Hero 1 |
| HeroV2EditorTest::test_hero_two_hides_independent_primary_destination_in_page_and_template_editors | انتظار برچسب انگلیسی Destination در ویرایشگر فعلی |

### پوشش معیارهای پذیرش

| سناریو | شواهد |
|---|---|
| بازکردن بدون تایپ / تمام صفحات | تست API با ۴۳ رکورد برای هر پنج نوع اصلی؛ سه صفحه و بررسی عدم تکرار/حذف؛ تست مرورگر درخواست q خالی |
| جست‌وجو، انتخاب، تغییر و پاک‌کردن | DestinationBrowserTest و destination-picker.test.js؛ title/slug/ID/SKU و wildcard literal |
| ذخیره، بارگذاری مجدد و نوع مقصد | ActionPickerIntegrationTest؛ DestinationBrowserTest؛ canonical JSON بدون snapshot URL/title |
| فرم display=modal / display=page | هر دو حالت ذخیره و reload؛ فرم draft قابل ارسال نیست |
| چند دکمه و Repeater | دکمه‌های مستقل؛ clone کارت و reorder واقعی Filament؛ تغییر/پاک‌کردن یک آیتم بدون تغییر دیگری |
| Page Builder / Template Editor | ذخیره و reload و clone بلوک CTA واقعی در EditPage و EditTemplate |
| Undo/Redo و نسخه‌های برگه | تغییر reference در EditPage، Undo/Redo، انتخاب نسخه پایه، بازیابی reference و ذخیره مجدد |
| Legacy / Template / Recipe | تست‌های موجود Normalizer/Adapter و Runtime در مجموعه رگرسیون؛ ساختار schema/identity/Recipe تغییر نکرده است |
| Authorization / Publication / Availability | مهمان، غیرمدیر و مدیر غیرفعال رد می‌شوند؛ draft/scheduled و ماژول خاموش و مقصد فاقد URL بررسی شده‌اند |
| Focus / Scroll / Layering / RTL | سه تست Chrome headless با Blade واقعی، Alpine/Filament واقعی و HTTP mock؛ موبایل ۳۹۰ و دسکتاپ ۱۴۴۰؛ native :modal، Tab، Escape و قفل اسکرول |
| Rich Text | Trix واقعی در harness مرورگر؛ انتخاب مقصد و اعمال URL به متن انتخاب‌شده و بازگشت Focus |
| CTA/لینک عمومی | تست‌های Runtime و Entity/Form Resolver؛ **Production مستقر بررسی نشده است** |
| بازبینی مصرف‌کنندگان | جدول‌های ممیزی بالا؛ تمام مصرف‌کنندگان ActionPicker با تغییر مشترک پوشش داده شده‌اند |

برای اجرای مجدد مجموعه نهایی:

```powershell
php -d memory_limit=512M vendor/bin/phpunit tests/Browser/DestinationPickerBrowserTest.php tests/Feature/DestinationBrowserTest.php tests/Feature/ActionPickerIntegrationTest.php tests/Feature/FormBlockTest.php tests/Feature/FeatureGridVideoTest.php tests/Feature/InternalLinkSearchProviderTest.php
```

## سازگاری و محدودیت‌ها

- `ActionDestination`، reference_id، schema_version، block_id، normalizerهای Legacy، Resolverها و نمایش فرم در صفحه/مودال حفظ شده‌اند. browse یک قابلیت اختیاری روی Source فعلی است؛ قرارداد search و Registryهای جدا ادغام نشده‌اند.
- Menu Editor از قبل بدون تایپ فهرست دارد. انتخاب چندتایی برگه و درخت صفحه‌بندی‌شده خدمت دست‌نخورده است؛ قوانین منو اجازه مدیریت بعضی رکوردهای غیرعمومی را می‌دهند و جایگزینی با Source عمومی ActionPicker این قرارداد را تغییر می‌داد. فهرست برگه‌های منو همچنان روش بارگذاری قبلی خود را دارد.
- انتخاب‌های مربوط به محتوای Domain، شرایط قالب، منو و رسانه که از ابتدا فهرست داشتند، صرفاً برای یکسان‌سازی ظاهری به Action تبدیل نشده‌اند. URLهای دلخواه همچنان ورودی متنی دارند.
- آزمون مرورگر harness مستقل با Assetهای واقعی است؛ ورود به پنل واقعی با داده‌های محیط کاربر و بررسی Production انجام نشده است. Publication، کنترل دسترسی و صفحه‌بندی واقعی backend جداگانه در PHP/Livewire آزمایش شده‌اند.
- اجرای مرورگر به Chrome نصب‌شده نیاز دارد؛ در محیط دیگر `CHROME_PATH` را تنظیم کنید. Safari/Firefox و صفحه‌کلید نرم موبایل در این اجرا بررسی نشده‌اند.
- پنج شکست تست پایه بالا خارج از دامنه انتخابگر باقی مانده‌اند.
