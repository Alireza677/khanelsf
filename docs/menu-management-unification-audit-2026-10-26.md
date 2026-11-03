# ممیزی معماری مدیریت منوها و جایگاه‌های نمایش در King CMS

تاریخ: ۲۰۲۶-۱۰-۲۶ — وضعیت: پیشنهاد معماری؛ پیاده‌سازی نشده است.

## ۱. محدوده و نتیجه ممیزی

این گزارش فقط از خواندن سورس checkout فعلی تهیه شده است. در این مرحله فقط همین فایل گزارش ایجاد شده؛ کد، تنظیمات، Schema، Seeder و داده‌های پروژه تغییر نکرده‌اند. تغییرات Homepage از مرحله قبلی در workspace وجود داشتند و دست‌نخورده باقی ماندند. هیچ تست، lint، دستور Artisan، migration، seed یا بررسی مرورگر اجرا نشد. فایل‌های تست موجود صرفاً به‌عنوان قراردادهای قابل بررسی خوانده شدند.

هیچ دسترسی به دیتابیس یا تنظیمات Shiften و سایر سایت‌های واقعی وجود ندارد. بنابراین «علت بالقوه و قابل اثبات در سورس» با «علت قطعی حادثه Production» یکسان فرض نشده است.

**نتیجه:** یکپارچه‌سازی با حفظ Schema فعلی و قراردادهای Navigation Core امکان‌پذیر است. جدول settings ظرفیت نگهداری انتساب جایگاه‌ها را دارد؛ ساخت جدول pivot، تغییر MenuItem یا بازنویسی Registry/Source Resolver لازم نیست. بااین‌حال، حذف صرف چند فیلد از فرم‌ها کافی نیست: مسیر تعیین منوی مؤثر، ذخیره تنظیمات، سازگاری Templateهای قبلی و تفاوت فوتر سه‌ستونه باید صریحاً مدیریت شوند.

یافته‌های اصلی:

1. هدر پیش‌فرض از `header_menu_id` و سپس `Menu.location=main` استفاده می‌کند؛ مقدار `location=header` در این مسیر مصرف نمی‌شود.
2. هدر صنعتی پس از اصلاح قبلی، ابتدا `header_menu_id`، سپس `settings.menu_id` داخل بلوک، سپس location=`main` را می‌خواند.
3. فوتر پیش‌فرض از `footer_menu_id` و سپس location=`footer` استفاده می‌کند؛ فوتر شرکتی از سه شناسه مستقل داخل بلوک استفاده می‌کند و تنظیم سراسری منوی فوتر را نمی‌خواند.
4. انتخاب Template، انتخاب طراحی است و با انتخاب Menu هم‌معنا نیست؛ رفتار fallback انتخاب Template هدر و فوتر نیز یکسان نیست.
5. نصب خالی همراه DatabaseSeeder اتصال اولیه هدر را می‌سازد؛ ساخت دستی منوی فعال از پنل، به‌تنهایی انتساب به جایگاه ایجاد نمی‌کند.

## ۲. فهرست شواهد سورس

شماره خطوط مربوط به checkout هنگام ممیزی است؛ نام متد نیز برای پیدا کردن محل پس از تغییرات بعدی آمده است.

| شناسه | محل | شاهد |
| --- | --- | --- |
| E01 | `database/migrations/2026_05_30_000000_create_cms_tables.php:59` | جدول menus، location اختیاری و غیر unique، status پیش‌فرض active |
| E02 | همان فایل، `:68` و `:80` | روابط menu_items؛ settings.key یکتا و value متنی nullable |
| E03 | `database/migrations/2026_07_24_000000_add_link_reference_to_menu_items_table.php:11`؛ `2026_07_28_000000_add_source_key_to_menu_items_table.php:12` | type/reference و source_key بدون FK مستقیم به مقصدهای polymorphic |
| E04 | `app/Models/Menu.php:17`؛ `app/Models/MenuItem.php:63` و `:83` | روابط و ترتیب resolvedUrl |
| E05 | `app/Services/MenuService.php:24`، `:37`، `:45` و `:52` | اولویت انتخاب سراسری، fallback و شرط active |
| E06 | `app/CMS/Blocks/SiteHeader/SiteHeaderRuntime.php:38` | اولویت سراسری ← بلوک ← main |
| E07 | `app/CMS/Blocks/SiteFooter/SiteFooterRuntime.php:29` و `:146` | دریافت مستقل منوی هر ستون و نمایش فقط rootItems |
| E08 | `app/CMS/Templates/SiteHeaderTemplateResolver.php:14`؛ `SiteFooterTemplateResolver.php:16` | تفاوت انتخاب Template هدر و فوتر |
| E09 | `app/Providers/BlockServiceProvider.php:224`؛ `resources/views/layouts/app.blade.php:750` و `:774` | اتصال Resolver به Layout، Preview و fallback به partial موجود |
| E10 | `app/Filament/Resources/MenuResource.php:29`، `:120`، `:135` و `:153` | فرم location، ساخت سریع منو و Routeهای مدیریتی |
| E11 | `app/Filament/Pages/ManageSiteSettings.php:82`، `:168`، `:318`، `:925` و `:1034` | metadata، فیلدهای انتخاب و حلقه ذخیره تنظیمات |
| E12 | `app/CMS/Blocks/SiteHeader/SiteHeaderBlock.php:89`؛ `SiteHeaderDataNormalizer.php:48` | selector داخل بلوک و حفظ menu_id در JSON نرمال‌شده |
| E13 | `app/CMS/Blocks/SiteFooter/SiteFooterBlock.php:88`؛ `SiteFooterDataNormalizer.php:27` | سه ستون، title/menu_id و حداکثر سه ورودی نرمال‌شده |
| E14 | `database/seeders/DatabaseSeeder.php:519`، `:563`، `:581` و `:622` | منوهای پایه و انتساب سراسری هدر/فوتر |
| E15 | `database/seeders/IndustrialHeaderTemplateSeeder.php:15`، `:69` و `:80` | snapshot منوی main-menu داخل بلوک و انتخاب Template هدر |
| E16 | `database/seeders/CorporateFooterTemplateSeeder.php:16`، `:52`، `:72` و `:84` | سه منوی اختصاصی فوتر، اتصال ستون‌ها و انتخاب Template |
| E17 | `app/View/Components/Navigation.php:15`؛ `resources/views/components/navigation-items.blade.php:1`؛ `resources/views/partials/navigation/items.blade.php:1` | rendererهای پیش‌فرض و بازگشتی صنعتی |
| E18 | `app/Services/MenuTreeService.php:12`، `:17`، `:112` و `:149` | درخت مدیریتی، کنترل مالکیت آیتم‌ها، ترتیب و عمق ۲۰ |
| E19 | `app/Services/SettingsService.php:15`، `:36`، `:78`، `:155`، `:205` و `:320` | خواندن/نوشتن تنظیمات، invalidation و تبدیل شناسه مثبت |
| E20 | `app/Services/NavigationSourceVisibility.php:31`؛ `app/CMS/Navigation/NavigationSourceRegistry.php` و `NavigationSource.php` | جداسازی availability مقصد از حل URL |
| E21 | `app/Services/TemplateService.php:20`، `:49` و `:98` | انتخاب Template پیش‌فرض فوتر بر اساس matching/priority |
| E22 | `resources/views/filament/resources/menu-resource/pages/edit-menu.blade.php:615` | فرم تنظیمات و ساختار منو در صفحه واحد فعلی |

## ۳. معماری ذخیره‌سازی و مقادیر پیش‌فرض

### ۳.۱ Menu و MenuItem

| داده | ذخیره و پیش‌فرض واقعی | خواندن و رابطه |
| --- | --- | --- |
| `Menu.id` | کلید اصلی menus؛ مقدار خودکار | انتخاب‌های سراسری و Template به این ID اشاره می‌کنند |
| `Menu.title` | string الزامی، بدون پیش‌فرض DB | عنوان مدیریتی؛ لزوماً عنوان ستون فوتر نیست |
| `Menu.slug` | string یکتا و الزامی | ساخت سریع از title و suffix تولید می‌کند؛ Seederها با slug منو را می‌یابند. MenuService فعلی بر اساس slug انتخاب نمی‌کند |
| `Menu.location` | string nullable، فقط index؛ بدون enum، uniqueness یا جدول ثبت جایگاه | `MenuService::byLocation()` با تطبیق دقیق مقدار؛ هدر fallback=`main`، فوتر پیش‌فرض fallback=`footer` |
| `Menu.status` | string؛ پیش‌فرض DB و فرم active | Runtime فقط active را می‌گیرد؛ ساخت سریع نیز active می‌نویسد |
| `Menu.items()` | رابطه hasMany؛ مرتب‌شده با sort_order | Menu Builder همه آیتم‌های همان منو را می‌خواند |
| `Menu.rootItems()` | items با parent_id خالی | نقطه شروع نمایش عمومی |
| `MenuItem.menu_id` | FK الزامی به menus؛ cascadeOnDelete | belongsTo(Menu)؛ حذف منو آیتم‌های آن را حذف می‌کند |
| `MenuItem.parent_id` | FK nullable به menu_items؛ nullOnDelete | parent/children؛ رابطه DB به‌تنهایی یکسان‌بودن menu_id والد و فرزند را تضمین نمی‌کند؛ MenuTreeService ورودی درخت را به آیتم‌های همان منو محدود می‌کند |
| `MenuItem.title/url` | title الزامی؛ url nullable | title برچسب لینک؛ URL فقط برای custom_url مالکیت مستقیم دارد |
| `MenuItem.type` | string؛ پیش‌فرض custom_url در مدل و migration | انواع page، custom_url، source، post، product، project، service |
| `MenuItem.source_key` | string nullable با index | کلید Registry؛ در صورت غیرخالی‌بودن، مقدم بر type/reference/url است |
| `reference_id/reference_type` | nullable؛ ID در مدل integer cast می‌شود؛ polymorphic و بدون FK مقصد | `reference()` → قرارداد `ResolvesNavigationUrl` |
| `target` | پیش‌فرض `_self` | Builder مقادیر `_self/_blank` را می‌پذیرد؛ renderer برای blank ویژگی rel اضافه می‌کند |
| `sort_order` | unsigned integer؛ پیش‌فرض صفر | Builder ترتیب هر سطح را از صفر بازنویسی می‌کند؛ ID و مرجع مقصد حفظ می‌شوند |
| `status` | پیش‌فرض active | آیتم inactive از خروجی عمومی حذف می‌شود |
| timestamps | timestamps استاندارد | خواندن/ویرایش؛ به‌معنی انتساب جایگاه نیستند |

هیچ رابطه Eloquent اختصاصی برای «این منو در کدام جایگاه‌ها/Templateها استفاده شده» وجود ندارد. شناسه‌های داخل settings و JSON قالب FK به menus ندارند؛ حذف منو، آن شناسه‌ها را خودکار پاک نمی‌کند. این وضعیت با fail-closed Runtime برای ID مثبتِ ناموجود مدیریت می‌شود. [E01–E05]

### ۳.۲ تنظیمات سایت و داده Template

| مورد | ذخیره | مقدار پیش‌فرض/نبود مقدار | مصرف و هم‌پوشانی |
| --- | --- | --- | --- |
| `header_menu_id` | یک رکورد settings؛ group=header، type=select؛ مقدار ID به صورت متنی | getter بدون مقدار یا با مقدار غیرمثبت/نامعتبر، null می‌دهد؛ default ثابت ID وجود ندارد | `SettingsService::headerMenuId()` → MenuService.header و SiteHeaderRuntime؛ هم‌پوشان با location و menu_id بلوک |
| `footer_menu_id` | settings، group=footer، type=select | همان تبدیل شناسه مثبت | فقط مسیر منوی فوتر پیش‌فرض؛ فوتر شرکتی این انتخاب را مصرف نمی‌کند |
| `header_template_id` | settings، group=header، type=select | بدون شناسه معتبر، Resolver هدر null برمی‌گرداند | Template منتشرشده از type=site_header؛ انتخاب طراحی، نه منو |
| `footer_template_id` | settings، group=footer، type=select | بدون شناسه معتبر، TemplateService برای site_footer فراخوانی می‌شود | با ID مثبت، همان Template منتشرشده و نوع درست لازم است؛ در نبود نتیجه، Layout partial پیش‌فرض را نشان می‌دهد |
| `Template.blocks` | JSON با cast array در مدل Template | نبود بلوک باعث `hasBlocks=false` است | داده ثبت‌شده Builder؛ Layout از partials.page-blocks استفاده می‌کند |
| `site_header.data.settings.menu_id` | داخل blocks همان Template، نه settings سراسری | normalizer آن را به integer مثبت یا null تبدیل می‌کند؛ selector گزینه‌های active دارد | فقط در مسیر صنعتی و زمانی که header_menu_id معتبر مثبت نیست؛ قابلیت override جدید بر انتخاب سراسری ندارد |
| `site_footer.data.content.menu_columns[*].menu_id` | داخل blocks، برای هر ستون همراه title | normalizer ورودی خالی را [] می‌کند و حداکثر سه ستون می‌پذیرد؛ فرم سه ردیف دارد، بدون ID منوی پیش‌فرض | SiteFooterRuntime هر منو را مستقل با byId می‌گیرد؛ جایگزینی با footer_menu_id انجام نمی‌شود |
| `Template.status/type/conditions/priority/is_default` | ستون‌های Template؛ blocks جداگانه | Template منتشرشده و نوع صحیح لازم است؛ seedهای صنعتی/شرکتی با is_default=false ساخته می‌شوند ولی صریح انتخاب می‌شوند | انتخاب صریح هدر از matching عمومی عبور نمی‌کند؛ فوتر در نبود selector، از matching پیش‌فرض استفاده می‌کند |

`SettingsService::positiveInteger()` برای کلیدهای منو مقدار `0`، منفی یا متن نامعتبر را null می‌کند. **برخلاف ID مثبتِ حذف‌شده، مقدار خراب متنی فعلاً ممکن است fallback پایین‌تر را فعال کند.** این تفاوت موجود باید در سازگاری Legacy ثبت شود؛ اصلاح رفتار Homepage به‌طور خودکار به منوها تعمیم نیافته است. [E06–E09، E11–E13، E19، E21]

## ۴. مسیرهای واقعی Runtime و ترتیب اولویت

### ۴.۱ تعیین طراحی و سپس منو

```text
layouts.app ← View composer در BlockServiceProvider
  ├─ SiteHeaderTemplateResolver
  │    ├─ header_template_id مثبت + published + type صحیح + blocks → بلوک‌های Template
  │    └─ نبود نتیجه یا blocks → partials.header → x-navigation(header)
  └─ SiteFooterTemplateResolver
       ├─ footer_template_id مثبت → فقط همان Template منتشرشده با type صحیح
       ├─ بدون ID معتبر → TemplateService.findTemplateFor(site_footer)
       └─ نبود نتیجه یا blocks → partials.footer → x-navigation(footer)
```

در matching عمومی فوتر، انتخاب پیش‌فرض به شرایط، is_default، priority و tie-breakهای TemplateService وابسته است. صرف وجود Template منتشرشده با conditions=all و is_default=false، آن را خودکار پیش‌فرض نمی‌کند. در هدر، حتی وجود Template با is_default=true هم به‌تنهایی جای header_template_id را نمی‌گیرد. Preview مجاز Template، همان Template پیش‌نمایش را به Layout می‌دهد؛ این رفتار نباید هنگام انتقال انتساب منو تغییر ناخواسته کند. [E08، E09، E21]

قالب سفارشی منتشرشده‌ای که بلوک‌های دیگری دارد ولی هیچ مصرف‌کننده Navigation ندارد، صرف انتخاب‌شدنش منو تولید نمی‌کند. hasBlocks حضور site_header/site_footer را تضمین نمی‌کند. UI جایگاه‌ها باید «انتساب دارد اما قالب فعال آن را رندر نمی‌کند» را از «منویی انتخاب نشده» جدا نشان دهد؛ تزریق خودکار Header/Footer جدید راه‌حل این ممیزی نیست.

### ۴.۲ چهار مسیر انتخاب منو

| renderer فعال | ترتیب واقعی | رفتار در خطا |
| --- | --- | --- |
| هدر پیش‌فرض | header_menu_id مثبت → در نبود آن، Menu.location=`main` | ID مثبت ناموجود/غیرفعال: null و بدون انتخاب منوی دیگر |
| هدر صنعتی | header_menu_id مثبت → در نبود آن، settings.menu_id بلوک → در نبود هر دو، MenuService.header/main | ID مثبت انتخاب‌شده در هر سطح ناموجود/غیرفعال باشد، به سطح پایین‌تر سقوط نمی‌کند |
| فوتر پیش‌فرض | footer_menu_id مثبت → در نبود آن، Menu.location=`footer` | ID مثبت ناموجود/غیرفعال: بدون منوی جایگزین |
| فوتر شرکتی | هر menu_columns[i].menu_id، مستقل با MenuService.byId | ستون بدون ID یا بدون آیتم قابل نمایش حذف می‌شود؛ نه location و نه footer_menu_id جایگزین نمی‌شوند |

متد `MenuService::main()` alias متد header است، نه lookup مستقل برای location=main. `byLocation()` عمومی وجود دارد، اما در مصرف‌کنندگان فعلی عمومی پروژه lookup خودکار header/sidebar وجود ندارد. [E05–E07]

### ۴.۳ حل آیتم، Source و درخت

```text
MenuService.byId / byLocation
  → menus.status=active
  → rootItems و children
  → NavigationSourceVisibility.menuItemIsVisible
  → MenuItem.resolvedUrl
      ├─ source_key غیرخالی → NavigationSourceRegistry → Source.resolve
      ├─ custom_url → url ذخیره‌شده
      └─ reference → مدل دارای ResolvesNavigationUrl
  → Component Navigation یا SiteHeader/SiteFooter Runtime → View موجود
```

`NavigationSourceRegistry`، **Registry مقصدها** است؛ محل نمایش منو را ثبت یا انتخاب نمی‌کند. کلیدی مانند services.archive یا blog.archive با جایگاهی مانند header فرق دارد. Registry.resolve به‌تنهایی availability را اعمال نمی‌کند؛ مسیر MenuService از NavigationSourceVisibility برای بررسی ثبت‌شدن و availability و URL غیرخالی استفاده می‌کند. Source نامعتبر با URL ذخیره‌شده دور زده نمی‌شود. Providerهای Navigation، Shop، Post و Service مقصدها را ثبت می‌کنند. [E04، E20]

انتشار مقصدها کاملاً یکسان نیست: Service.resolveNavigationUrl انتشار را بررسی می‌کند؛ Page/Post/Product/Project در متدهای بررسی‌شده عمدتاً URL را از slug می‌سازند. حتی getAvailablePages در EditMenu scope published ندارد. بنابراین نباید در مستندات یا تست جدید فرض کرد «همه مقصدهای منتشرنشده همواره از منو حذف می‌شوند». این تفاوت موجود، موضوع جداگانه‌ای است و در یکپارچه‌سازی جایگاه‌ها نباید بی‌تصمیم تغییر کند. کنترل انتشار مسیر عمومی مقصد نیز با کنترل وجود لینک در منو یکسان نیست.

درخت مدیریت با MenuTreeService ساخته و ذخیره می‌شود؛ Service hierarchy صرفاً ساختار picker است و جای parent_id واقعی MenuItem را نمی‌گیرد. ذخیره درخت با transaction، قفل آیتم‌های همان منو، تطبیق تمام IDها، رد duplicate/foreign/missing و عمق حداکثر ۲۰ انجام می‌شود. تغییر جایگاه نباید parent_id، sort_order، reference یا ID آیتم‌ها را بازنویسی کند. [E18]

### ۴.۴ عمق و Responsive موجود

| مسیر | عمق فعلی | پیامد برای طرح |
| --- | --- | --- |
| داده و Menu Builder | تا ۲۰ سطح | نیاز به Schema جدید برای چندسطحی نیست |
| هدر صنعتی | تا ۲۰ سطح در SiteHeaderRuntime؛ View بازگشتی | حفظ submenu toggle، aria-controls، flyout RTL و More overflow |
| هدر پیش‌فرض | maxDepth پیش‌فرض ۲ در Navigation component | نباید یکپارچه‌سازی انتساب، پشتیبانی ۲۰ سطح در همه rendererها را وعده بدهد |
| فوتر پیش‌فرض | maxDepth پیش‌فرض ۱ | فقط ریشه‌ها |
| فوتر شرکتی | فقط rootItems؛ children در داده نمایشی ستون کپی نمی‌شود | حذف selectorهای ستون بدون جایگزین، قابلیت سه منوی مستقل را از دست می‌دهد |

کدهای مرتبط Responsive در `resources/js/app.js`، `resources/js/components/industrial-sticky-header.js`، `resources/css/app.css` و fallback scriptهای `resources/views/layouts/app.blade.php` قرار دارند. این ممیزی عملکرد بصری آنها را آزمایش نکرده است. یکپارچه‌سازی انتساب باید این قراردادها را حفظ کند؛ افزایش عمق هدر پیش‌فرض یا فوتر، در صورت نیاز، تغییر نمایشی جداگانه با تأیید و تست مستقل است.

## ۵. جریان مدیریتی و فیلدهای قابل حذف از UI

### ۵.۱ وضعیت فعلی

MenuResource فقط Routeهای index و edit را ثبت می‌کند. صفحه کلاس CreateMenu وجود دارد ولی Route ایجاد مستقل در getPages ثبت نیست. ایجاد از Action صفحه ListMenus یا empty state با quickCreateForm انجام می‌شود: فقط title گرفته می‌شود؛ slug یکتا و status=active تولید می‌شود؛ location یا setting انتساب نوشته نمی‌شود. [E10]

صفحه EditMenu هم‌اکنون مکان مناسبی برای مرکز مدیریت است: فرم تنظیمات، انتخاب Page/Service/Source/custom URL، درخت ویرایش آیتم‌ها و ذخیره ترتیب داخل همان صفحه وجود دارند. MenuItemResource در navigation ثبت نمی‌شود و مدیریت اصلی آیتم‌ها در همین Builder است. بخش «تنظیمات منو» location آزاد را با توضیح «محل نمایش» نشان می‌دهد، ولی وضعیت انتساب مؤثر در header_menu_id یا Template را نشان نمی‌دهد. [E22]

ManageSiteSettings انتخاب Menu و Template را کنار یکدیگر در تب‌های هدر و فوتر قرار داده است. selector منو همه منوها، از جمله غیرفعال با پسوند هشدار، را نمایش می‌دهد و فقط exists/integer را بررسی می‌کند؛ انتخاب منوی غیرفعال از پنل ممکن است ولی Runtime چیزی نشان نمی‌دهد. selector داخل site_header فقط activeها را فهرست می‌کند. این اختلاف نیز ابهام UI ایجاد می‌کند. [E11، E12]

### ۵.۲ حذف ظاهری امن در برابر حذف قابلیت

| فیلد/بخش | پیشنهاد | شرط حفظ قابلیت |
| --- | --- | --- |
| Menu.location در فرم عادی | حذف ورودی متن آزاد؛ نمایش فقط در جزئیات Legacy در دوره انتقال | انتساب واقعی در مرکز جایگاه‌ها ارائه شود؛ داده DB پاک/تبدیل نشود و fallback قبلی برای سایت مهاجرت‌نکرده باقی بماند |
| Menu.location در ستون/فیلتر | با «جایگاه‌های مصرف مؤثر» جایگزین؛ فیلتر Legacy در بخش پیشرفته باقی بماند | موارد سفارشی ناشناخته مانند sidebar حذف داده‌ای نشوند |
| header_menu_id / footer_menu_id در Site Settings | تبدیل selector به خلاصه فقط‌خواندنی و لینک «مدیریت جایگاه‌های منو» | نویسنده دوم حذف شود؛ نگهداری settingهای قدیمی و معنای آنها در compatibility reader باقی بماند |
| settings.menu_id در site_header | حذف انتخاب Menu برای Template جدید؛ خلاصه منوی مؤثر و لینک به مرکز | داده Template قدیمی در fill/dehydrate/normalize/save بدون حذف round-trip شود؛ در دوره انتقال، ویرایش انتخاب Legacy فقط از مسیر مرکزی و صریح ممکن باشد |
| content.menu_columns[*].menu_id در site_footer | حذف فوری توصیه نمی‌شود | ابتدا سه جایگاه مستقل در مرکز و binding ثابت ستون‌ها آماده شود؛ title/طراحی ستون‌ها باقی بماند |
| header_template_id / footer_template_id | حفظ در بخش طراحی | تکرار انتساب Menu نیستند؛ حذفشان امکان انتخاب طراحی را می‌گیرد |
| search/sticky/top-bar/CTA، تماس، رنگ، عنوان ستون | حفظ در طراحی/تنظیمات موجود | محتوا و رفتار مستقل از انتساب Navigation هستند |
| Menu.title/status | حفظ | هویت منو و توقف انتشار آن همچنان لازم‌اند |
| Menu.slug | می‌تواند به بخش پیشرفته منتقل شود | مقدار موجود حفظ و برای منوی جدید خودکار تولید شود؛ کاربرد Seeder و مصرف‌کنندگان سفارشی احتمالی نادیده گرفته نشود |

**خطر مشخص در ذخیره فرم:** ManageSiteSettings.persistSettings همه کلیدهای settingsMeta را می‌نویسد و برای داده غایب null در نظر می‌گیرد. اگر selector حذف شود ولی کلید از حلقه ذخیره خارج نشود، ذخیره نام سایت می‌تواند انتساب قدیمی را پاک کند. خارج‌کردن فیلد از UI باید با انتقال مالکیت write آن همراه باشد؛ صرف hidden/dehydrated(false) کردن آن کافی نیست. همچنین اگر menu_id از schema/normalizer بلوک حذف شود، یک save قالب می‌تواند داده Legacy را از بین ببرد. این دو مورد، پیش‌نیاز پیاده‌سازی امن‌اند. [E11–E13]

## ۶. نصب تازه و علت‌های نمایش‌ندادن منو

### ۶.۱ سه نوع «نصب تازه»

| مسیر نصب | نتیجه قابل استنتاج از سورس |
| --- | --- |
| فقط migration | جدول‌ها ایجاد می‌شوند؛ منو، انتساب یا Template اولیه تضمین نمی‌شود |
| دیتابیس خالی + اجرای کامل DatabaseSeeder طبق README | main-menu با location=main، آیتم‌ها و header_menu_id ساخته می‌شود؛ IndustrialHeaderTemplateSeeder Template منتشرشده را می‌سازد/انتخاب می‌کند و menu_id همان main-menu را در بلوک می‌گذارد |
| ساخت دستی اولین منو از پنل | منوی active با slug ساخته می‌شود؛ location و header_menu_id و Template binding خودکار تعیین نمی‌شوند |

در همان full seed، footer-menu با location=footer و footer_menu_id ساخته می‌شود؛ سپس CorporateFooterTemplateSeeder سه منوی دیگر با location=null و سه binding اختصاصی ایجاد و Template فوتر شرکتی را انتخاب می‌کند. پس در نصب seedشده، تغییر footer_menu_id ممکن است روی فوتر فعال شرکتی اثری نداشته باشد. این اثر از مسیر Runtime اثبات می‌شود، نه از مشاهده Shiften. [E14–E16]

برای clean full seed، نقص «اصلاً header_menu_id ساخته نمی‌شود» از سورس تأیید نشد. نقص قابل اثبات، چند محل ثبت انتساب و نبود راهنمای مرکزی برای ساخت دستی است. در مسیرهای جست‌وجوشده app/config/routes و فایل‌های نصب، Setup Wizard مخصوص نصب King/انتساب منو پیدا نشد؛ wizardهای خدمات و فعالیت‌ها، installer منو نیستند. وجود ابزار استقرار خارج از repository یا setup اختصاصی سایت واقعی نامعلوم است.

### ۶.۲ چرا active بودن کافی نیست؟

نمایش به این زنجیره نیاز دارد: renderer فعال آن جایگاه را مصرف کند؛ منوی انتخاب‌شده وجود داشته و active باشد؛ rootItem فعال و مقصد قابل حل داشته باشد؛ Source لازم ثبت/available باشد؛ و View سطح موردنظر را نمایش دهد. منوی فعالِ بدون انتساب، منوی بدون آیتم، یا منویی که همه آیتم‌هایش فیلتر می‌شوند، ناوبری قابل مشاهده تولید نمی‌کند.

`location=header` کافی نیست چون:

1. fallback هدر دقیقاً `main` است؛ helperText فرم که header/footer/sidebar را مثال می‌زند، با این قرارداد همسو نیست.
2. header_menu_id مثبت بر location مقدم است؛ حتی اگر آن انتخاب غیرفعال/حذف‌شده باشد، منوی دیگری خودکار جایگزین نمی‌شود.
3. در صنعتی، menu_id بلوک نیز پیش از lookup main قرار می‌گیرد.
4. ممکن است Template فعال اصلاً مصرف‌کننده Navigation نداشته باشد.

location یکتا نیست. اگر چند منوی active با location=main یا footer موجود باشند، findActiveMenu از first بدون orderBy انتخاب می‌کند. انتخاب قطعی و مستند «منوی آخر» یا «کوچک‌ترین ID» وجود ندارد؛ نباید در مهاجرت خودکار حدس زده شود.

### ۶.۳ محدودیت و رفتار تکرار Seeder

firstOrCreate تنظیمات موجود را الزاماً repair نمی‌کند؛ مقدار null/قدیمیِ یک کلید موجود می‌تواند باقی بماند. Seeder صنعتی فقط اگر Template بلوک نداشته باشد blocks را می‌نویسد. Seeder شرکتی برای آیتم‌های منوهای نام‌گذاری‌شده خود updateOrCreate دارد؛ بنابراین اجرای مجدد آن یک ابزار بی‌خطرِ صرفاً اتصال جایگاه نیست. **پیشنهاد اجرایی برای Production، اجرای مجدد seed برای حل ابهام منو نیست.** بررسی نسخه مستقر، داده و مسیر نصب واقعی باید جدا انجام شود.

## ۷. تعارض‌ها و سازگاری سایت‌های موجود

| وضعیت قبلی | خروجی فعلی که باید در حالت Legacy حفظ شود | اقدام پیشنهادی پس از تأیید طرح |
| --- | --- | --- |
| فقط header_menu_id مثبت و active | در هدر پیش‌فرض و صنعتی همان منو | در مرکز، منبع مؤثر «تنظیم قبلی سایت» نمایش داده شود؛ استفاده به‌عنوان انتساب مرکزی فقط با ذخیره صریح مدیر |
| فقط location=main | هدر پیش‌فرض؛ صنعتی در نبود menu_id بلوک | همان lookup و رفتار موجود؛ تبدیل خودکار به header یا تنظیم دیگر انجام نشود |
| فقط location=header | در مسیرهای فعلی هدر انتخاب نمی‌شود | پیام ناسازگاری و پیشنهاد انتساب دستی؛ اضافه‌کردن alias خاموش، تغییر رفتار محسوب می‌شود |
| فقط menu_id بلوک صنعتی | فقط صنعتی از آن استفاده می‌کند؛ هدر پیش‌فرض آن را نمی‌بیند | حفظ binding و نمایش منبع Legacy؛ با تأیید مدیر، انتساب سراسری مرکزی ساخته شود |
| header_menu_id=A، block=B، main=C | هر دو هدر A را می‌گیرند؛ در صورت unavailable بودن A، B/C جایگزین نمی‌شوند | تعارض‌های B/C فقط خوانده و توضیح داده شوند؛ پاک یا همسان‌سازی خودکار نشوند |
| بدون header_menu_id، Templateهای مختلف با menu_idهای مختلف | صنعتی بر اساس Template مورد استفاده متفاوت است | نگهداری تفاوت تا انتخاب صریح مرکزی؛ تبدیل به یک جایگاه سراسری، تصمیم قابل مشاهده مدیر است |
| فقط footer_menu_id یا location=footer | فوتر پیش‌فرض همان منو؛ شرکتی از ستون‌های خودش می‌گیرد | نمایش دامنه مصرف واقعی؛ فرض یکسان‌بودن سه ستون با یک منو ممنوع |
| فقط menu_columns در Template شرکتی | هر ستون مستقل؛ غایب/غیرفعال/خالی حذف می‌شود | حفظ کامل سه binding و ترتیب آنها تا آماده‌شدن جایگاه‌های مرکزی |
| footer_template_id غایب ولی Template پیش‌فرض matching وجود دارد | امکان رندر Template شرکتی با مسیر TemplateService | تشخیص renderer مؤثر از Resolver موجود، نه صرف وجود selector |
| منوی مثبتِ انتخاب‌شده حذف/غیرفعال | منوی نامرتبط جایگزین نمی‌شود | حفظ انتخاب نامعتبر برای تشخیص خطا و نمایش هشدار؛ خودکار null کردن آن fallback را ناخواسته فعال می‌کند |
| کلید خام خراب/صفر در ساختار قبلی | getter فعلی null و امکان fallback دارد | معنای قبلی در compatibility reader ثابت بماند؛ رفتار سخت‌گیرانه جدید فقط در قرارداد مرکزی صریح اعمال شود |
| location سفارشی یا Template سفارشی | مصرف‌کننده احتمالی خارج از مسیرهای شناخته‌شده نامعلوم است | حفظ داده و اعلام unsupported/unknown؛ قبل از حذف UI پیشرفته inventory سایت لازم است |

هدف دوره انتقال دو بخش دارد: «فقط یک محل عادی برای مدیریت» می‌تواند زودتر فراهم شود؛ «تمام سایت‌های قدیمی فقط از انتساب مرکزی بخوانند» بدون انتخاب صریح و بررسی تعارض‌ها، هم‌زمان قابل تضمین نیست. نگهداری compatibility reader، منبع قابل ویرایش موازی جدید محسوب نمی‌شود، مشروط به اینکه در UI به‌وضوح Legacy معرفی و پس از ثبت مرکزی دیگر اعمال نشود.

## ۸. طرح معماری پیشنهادی

### ۸.۱ مرزبندی مسئولیت‌ها

- Menu و MenuItem: محتوای ناوبری، ساختار و لینک‌ها؛ بدون تعیین طراحی Header.
- مرکز «منوها و جایگاه‌های نمایش»: تنها مالک write انتساب Menu به جایگاه.
- MenuService: تعیین Menu مؤثر برای جایگاه و سپس استفاده از فیلترهای موجود؛ قابلیت explain برای پنل می‌تواند منشأ انتخاب/خطا را به داده مدیریتی اضافه کند.
- Source Registry/ResolvesNavigationUrl/Visibility/MenuTreeService: قرارداد فعلی بدون تغییر.
- Header/Footer Template: طراحی، CTA، نمایش جست‌وجو، sticky، عنوان و چیدمان ستون‌ها؛ مصرف جایگاه مشخص به‌جای انتخاب مستقیم Menu برای داده جدید.
- header_template_id/footer_template_id و Template Resolverهای فعلی: حفظ انتخاب طراحی و Preview؛ این انتساب‌ها به مرکز منو منتقل نمی‌شوند.

ساخت یک Navigation System، Model یا درخت موازی پیشنهاد نمی‌شود. اگر helper کوچکی برای خواندن انتساب لازم باشد، فقط در لایه انتخاب MenuService قرار می‌گیرد و خروجی آن همان Menu فعلی است.

### ۸.۲ گزینه کمینه در برابر قرارداد نهایی

**گزینه کمینه:** انتقال selectorهای header_menu_id/footer_menu_id به Menu Builder و استفاده از همان کلیدها. این کار برای کاهش پراکندگی UI هدر مفید و بدون تغییر Schema است، اما به‌تنهایی تمایز «بدون منو» از «ارث‌بری Legacy»، سه ستون فوتر و چند Template قدیمی را حل نمی‌کند. null در قرارداد فعلی به معنی fallback است. استفاده از 0 به‌عنوان sentinel جدید نیز معنای داده قدیمی را تغییر می‌دهد.

**پیشنهاد نهایی این گزارش:** نگهداری یک سند انتساب نسخه‌دار در settings موجود، مثلاً با کلید پیشنهادی `navigation_display_locations`، group=navigation و type=json. این کلید **اکنون در سورس وجود ندارد** و صرفاً پیشنهاد برای مرحله بعد است. نام و قرارداد آن باید پیش از پیاده‌سازی تثبیت شود. افزودن رکورد متنی JSON به settings، Migration یا تغییر Schema نمی‌خواهد.

این سند تنها Source of Truth فعال برای جایگاه‌هایی است که مدیر صریحاً در مرکز ثبت کرده است. header_menu_id/footer_menu_id، location و JSONهای قدیمی فقط ورودی سازگاریِ خواندنی برای جایگاه ثبت‌نشده‌اند؛ همزمان با سند جدید sync یا بازنویسی نمی‌شوند. بنابراین دو منبع قابل ویرایش برای header ایجاد نخواهد شد.

نکته اجرایی مهم: شاخه type=json در persistSettings فعلی از `array_values()` استفاده می‌کند و برای فهرست‌هایی مانند واحدهای خدمت نوشته شده است. سند associative جایگاه‌ها نباید صرفاً به settingsMeta آن فرم اضافه شود؛ در غیر این صورت کلیدهای version/locations از دست می‌روند. writer مرکزی باید پس از اعتبارسنجی، ساختار associative را بدون array_values با JSON encoding صریح به SettingsService.set بدهد. این یک مسیر ذخیره انتساب در همان سرویس است، نه جدول یا Navigation Core جدید. [E11، E19]

شکل پیشنهادی قرارداد، صرفاً نمونه ساختاری با ID فرضی:

```json
{
  "version": 1,
  "locations": {
    "header": { "mode": "menu", "menu_id": 123 },
    "footer": { "mode": "none" },
    "footer_column_1": { "mode": "menu", "menu_id": 123 }
  }
}
```

معنای حالات:

| حالت | رفتار پیشنهادی |
| --- | --- |
| جایگاه در سند وجود ندارد | compatibility reader دقیقاً رفتار فعلی همان renderer/context را اجرا می‌کند؛ صرف بازکردن پنل چیزی ثبت نمی‌کند |
| mode=menu با شناسه معتبر active | همان Menu از MenuService.byId و Navigation Core فعلی رندر می‌شود |
| mode=menu با شناسه حذف‌شده/غیرفعال/خراب | بدون ناوبری برای همان جایگاه، با خطای قابل مشاهده در پنل؛ بدون fallback به Legacy |
| mode=none | عمداً بدون منو؛ Legacy نادیده گرفته می‌شود و این حالت با کلید غایب یکسان نیست |
| JSON/version/mode غیرقابل فهم | خطای پیکربندی مدیریتی و رفتار محافظه‌کارانه بدون انتخاب منوی تصادفی؛ به‌عنوان «ثبت‌نشده» تفسیر نشود |

برای داده جدید، فقط menu_id مثبت یا mode=none پذیرفته شود. در صورت قطع دسترسی به settings، نباید exception پنهان‌شده توسط fallback عمومی SettingsService به‌اشتباه «نبود سند» تلقی شود؛ نحوه تشخیص خطای خواندن و JSON نامعتبر باید در helper انتساب مشخص و تست شود. این به معنی بازنویسی SettingsService برای همه مصرف‌کنندگان نیست.

اشتراک یک منو بین چند جایگاه با تکرار همان Menu ID در چند entry ممکن است؛ نیازی به duplicate کردن Menu/MenuItem یا تغییر location تک‌مقداری نیست. menu_id آیتم‌ها هرگز برای این کار تغییر نمی‌کند. جلوگیری از overwrite همزمان دو مدیر نیز برای سند مشترک لازم است: transaction همراه مقایسه checksum محتوای خوانده‌شده یا merge کنترل‌شده، نه last-write بدون اطلاع. فیلد version در مثال، نسخه Schema سند است و به‌تنهایی تشخیص تغییر همزمان را فراهم نمی‌کند.

### ۸.۳ جایگاه‌ها و فوتر سه‌ستونه

حداقل جایگاه‌های شناخته‌شده پیشنهادی: header، footer برای partial پیش‌فرض، و footer_column_1/2/3 برای فوتر شرکتی. این نام‌ها قرارداد پیشنهادی جدیدند؛ `main` در Legacy به معنی fallback هدر باقی می‌ماند.

مرکز باید نشان دهد کدام جایگاه توسط renderer مؤثر مصرف می‌شود؛ انتخاب footer ساده نباید به مدیر وعده تغییر سه ستون شرکتی بدهد. سه ستون مستقل باید مستقل بمانند، حتی اگر مدیر یک منوی واحد را عمداً در چند ستون انتخاب کند. تبدیل همه ستون‌ها به footer_menu_id افت قابلیت است و توصیه نمی‌شود.

عنوان، ترتیب و ظاهر ستون‌ها در Template باقی می‌مانند. **ترتیب ستون با هویت جایگاه یکی نیست:** داده قدیمی columnها index-based است و normalizer آن را values() می‌کند. پیش از حذف selectorهای ستون، باید binding پایدار جایگاه برای Template جدید مشخص شود؛ در صورت افزودن فیلد اختیاری slot در JSON بلوک، parser باید داده قبلی را بدون حذف menu_id حفظ کند. جابه‌جایی Repeater یا وجود چند بلوک site_footer نباید منوی ستون‌ها را بی‌صدا عوض کند. سایت‌های دارای چند نمونه/چیدمان اختصاصی تا تثبیت این نگاشت، در حالت Legacy باقی می‌مانند؛ همسان‌سازی حدسی و خودکار پذیرفته نیست.

برای header، هر دو renderer شناخته‌شده در حالت مرکزی همان جایگاه header را مصرف می‌کنند؛ تغییر Template فقط طراحی را عوض می‌کند. Templateهای قدیمی با menu_id متفاوت، تا ثبت صریح مدیر همان رفتار context قبلی را دارند. انتخاب مرکزی باید در Preview نیز واضح و یکسان باشد؛ اگر Preview منوی Legacy یک قالب را نشان می‌دهد، provenance آن باید صریح باشد.

### ۸.۴ نیاز به قابلیت‌های نمایشی

در UI جایگاه، قابلیت renderer مانند عمق قابل نمایش و وضعیت مصرف جایگاه فقط‌خواندنی باشد. پشتیبانی داده از ۲۰ سطح حفظ می‌شود و هدر صنعتی رفتار چندسطحی/Responsive فعلی را ادامه می‌دهد. اگر هم‌سطح‌شدن عمق تمام هدرها جزو پذیرش نهایی باشد، باید یک گام نمایشی مجزا برای Component/partials/CSS/JS موجود تعریف شود؛ این کار همچنان به تغییر Schema یا Source Resolver نیاز ندارد. فوترهای root-only نیز نباید بی‌تست ناگهان همه descendants را نشان دهند.

## ۹. پیشنهاد UX برای پنل

همان MenuResource زیر عنوان روشن «منوها» حفظ و دو بخش در آن ارائه شود:

### «ویرایش منو»

- انتخاب منو، ساخت منو و Builder موجود؛ title/status و تنظیمات پیشرفته slug باقی بمانند.
- «استفاده در جایگاه‌ها»: فهرست جایگاه‌های مؤثر با checkbox یا Action انتساب؛ تمام این کنترل‌ها از همان سرویس ذخیره مرکزی استفاده کنند.
- هشدار وجود منوی بدون آیتم یا صفر آیتم قابل نمایش، بدون افزودن لینک یا آیتم ساختگی.
- هنگام حذف/غیرفعال‌کردن منوی مصرف‌شده، فهرست جایگاه‌های متأثر نشان داده شود؛ داده انتساب خودکار خالی نشود تا fallback ناخواسته فعال نشود.

### «جایگاه‌های نمایش»

جدول پیشنهادی:

| جایگاه | وضعیت renderer | منوی مؤثر | منبع فعلی | اقدام |
| --- | --- | --- | --- | --- |
| هدر | پیش‌فرض یا صنعتی / بدون مصرف‌کننده | عنوان یا «بدون منو» | مرکزی / تنظیم قبلی سایت / بلوک Legacy / location=main / نامعتبر | انتخاب، ویرایش منو، ثبت مرکزی |
| فوتر پیش‌فرض | فعال/غیرفعال با طراحی فعلی | عنوان/خالی | مرکزی / footer_menu_id / location=footer | مدیریت همان جایگاه |
| ستون‌های فوتر شرکتی | فعال در طراحی فعلی یا نیازمند نگاشت | مستقل برای هر ستون | مرکزی یا Template Legacy | مدیریت هر ستون |

گزینه «بدون منو» از گزینه «حفظ رفتار قدیمی» جدا باشد. تغییر منوی یک جایگاه، انتساب قبلی همان جایگاه را جایگزین کند و در صورت اثرگذاری بر خروجی فعلی، تأیید مختصر نمایش دهد. تخصیص همان Menu به جایگاه دیگر نباید از جایگاه اول حذفش کند.

در صفحه Site Settings به‌جای selector تکراری، خلاصه منوی مؤثر و لینک به همین صفحه نمایش داده شود. در Template Editor نیز خلاصه و لینک جایگاه نشان داده شود؛ داده Legacy فقط در بخش صریح سازگاری قابل مدیریت باشد. کنترل «استفاده از همین انتخاب فعلی به‌صورت مرکزی» باید پیش‌نمایش تعارض‌ها بدهد و تنها پس از اقدام مدیر بنویسد. یک انتخاب تکراری مستقل در پنل یا داشبورد جدید ساخته نشود.

برای نصب تازه بدون seed، مسیر ساده باشد: «ساخت منو → افزودن آیتم → انتخاب هدر/فوتر در همان صفحه → ذخیره». active کردن، خودش انتساب نیست. ساخت اولین منو نباید بی‌اطلاع مدیر جایگاه‌های موجود را تصاحب کند. اگر Starter رسمی با seed نصب شود، انتساب‌های اولیه با IDهای ایجادشده همان نصب ثبت شوند؛ هیچ ID ثابت یا محتوای اختصاصی Shiften در Runtime اضافه نشود.

## ۱۰. برنامه پیاده‌سازی مرحله‌ای پس از تأیید

| مرحله | کار | معیار پذیرش و محدودیت |
| --- | --- | --- |
| ۱. تثبیت قرارداد و inventory | تعیین نام کلید مرکزی، جایگاه‌ها، modeها، سیاست JSON خراب و هویت ستون‌ها؛ تهیه inventory خواندنی انتخاب‌های فعلی/Templateها در هر سایت مجاز | هیچ داده قدیمی تبدیل نشود؛ تعارض‌ها و renderer مؤثر قابل توضیح باشند |
| ۲. نمایش مرکزی بدون جابه‌جایی داده | افزودن بخش جایگاه‌ها به MenuResource؛ نمایش provenance و لینک‌های Site Settings/Template | صرف مشاهده یا ذخیره تنظیمات نامرتبط، هیچ انتسابی را تغییر ندهد |
| ۳. مالکیت write و قرارداد مرکزی | افزودن reader/writer محدود در لایه MenuService/Settings؛ ذخیره سند فقط با اقدام صریح؛ invalidation کلید؛ حذف writeهای موازی از save عمومی | نبود entry همان رفتار فعلی؛ entry صریح همیشه مقدم؛ none و invalid بدون fallback |
| ۴. هدرها | اتصال هدر پیش‌فرض و صنعتی به انتخاب جایگاه header؛ site_header.menu_id فقط Legacy خواندنی | هر دو renderer منوی مرکزی یکسان؛ Template قدیمی تا انتخاب مرکزی رفتار قبلی را حفظ کند |
| ۵. فوتر | انتقال footer پیش‌فرض؛ سپس سه ستون پس از تثبیت slot identity و round-trip داده | حفظ سه منوی مستقل، عناوین، ترتیب و Preview؛ عدم حذف bindingهای قدیمی |
| ۶. ساده‌سازی UI | حذف selectorهای تکراری برای داده جدید، خلاصه فقط‌خواندنی و مسیر مدیریت واحد؛ location آزاد به Legacy پیشرفته منتقل شود | هیچ save قالب/تنظیمات، داده پنهان قدیمی را null یا حذف نکند |
| ۷. نصب تازه | اصلاح مسیر starter/Seeder یا راهنمای همان MenuResource برای ثبت انتساب روشن | نصب خالی، نصب با seed و تکرار seed بر سایت موجود سناریوهای جدا؛ بدون overwrite تنظیمات موجود |
| ۸. انتشار تدریجی و بازگشت | فعال‌سازی سایت‌به‌سایت پس از تست و بازبینی؛ حفظ snapshot قبل از انتخاب صریح و برنامه rollback خواندن انتساب | حذف داده Legacy یا قطع compatibility reader به‌عنوان پیش‌فرض این پروژه مطرح نیست |

بازگشت انتخاب مرکزی به حالت Legacy باید یک اقدام مدیریتی صریح با پیش‌نمایش نتیجه باشد؛ حذف کور entry یا downgrade کد ممکن است منوی دیگری را آشکار کند. هیچ Migration برای جابه‌جایی خودکار انتخاب‌ها یا اجرای seed روی سایت‌های موجود پیشنهاد نمی‌شود.

## ۱۱. ریسک‌ها و الزامات Backward Compatibility

| ریسک | الزام |
| --- | --- |
| حذف UI ولی باقی‌ماندن حلقه writer قدیمی | جلوگیری از نوشتن null؛ تست ذخیره نام سایت و تنظیمات نامرتبط |
| از دست رفتن menu_id در ذخیره/نرمال‌سازی Template | round-trip کامل JSON Legacy، از جمله قالب draft و duplicate/template snapshot |
| چند Menu با یک location | عدم انتخاب خودکار «برنده جدید»؛ اعلام ابهام و انتخاب دستی |
| تفسیر صفر/خالی به‌عنوان none | برای داده قدیمی ممنوع؛ mode صریح مرکزی نیاز است |
| سراسری‌شدن منوی Templateهای متفاوت | تغییر فقط پس از ثبت صریح مدیر؛ توضیح اثر بر قالب‌ها و Preview |
| یکی‌کردن فوتر سه‌ستونه با footer_menu_id | حفظ سه slot و محدودیت renderer؛ بدون حذف قابلیت |
| تغییر order یا عمق | حفظ parent_id/sort_order و قرارداد renderer؛ تفاوت tie-break مدیریت با Runtime برای sort برابر نیز در fixture پوشش داده شود |
| حذف Menu بدون FK settings/JSON | انتخاب نامعتبر باقی و قابل تشخیص باشد؛ انتخاب تصادفی جایگزین نشود |
| خاموش‌شدن ماژول یا Source | همان Registry/availability/resolvedUrl؛ عدم حذف MenuItem از مدیریت |
| کش | SettingsService کش static در پردازش دارد و set فقط همان کلید را invalidates می‌کند؛ MenuService/Template Resolver بررسی‌شده کش پایدار Menu/HTML ندارند. همگامی workerها/CDN جدا بررسی شود |
| دو مدیر همزمان | قفل/نسخه و تشخیص تعارض سند مرکزی؛ حفظ جایگاه نامرتبط هنگام ذخیره |
| مجوزها | دسترسی پنل فعلی User.canAccessPanel به admin فعال محدود است؛ مسیر جدید نباید bypass ایجاد کند. کنترل دسترسی در عملیات backend، نه فقط مخفی‌کردن دکمه؛ مالکیت آیتم‌های Builder حفظ شود |
| سفارشی‌سازی خارج از repository | locationهای ناشناخته/partialهای سفارشی داده نامعتبر فرض نشوند؛ inventory و تأیید سایت لازم است |

## ۱۲. فایل‌های احتمالی تغییر پس از تأیید طرح

این جدول برنامه آینده است؛ **هیچ‌کدام در این مرحله تغییر نکرده‌اند.**

| فایل/بخش | تغییر مورد انتظار |
| --- | --- |
| `app/Filament/Resources/MenuResource.php` | عنوان، بخش جایگاه‌ها، حذف location آزاد از جریان عادی و نمایش مصرف مؤثر |
| `app/Filament/Resources/MenuResource/Pages/ListMenus.php` | ورود به مدیریت جایگاه‌ها و جریان ساخت/انتساب، با استفاده از Resource فعلی |
| `app/Filament/Resources/MenuResource/Pages/EditMenu.php` | عملیات انتساب مرکزی، توضیح تعارض، بررسی مصرف هنگام حذف/غیرفعال‌سازی |
| `resources/views/filament/resources/menu-resource/pages/edit-menu.blade.php` | UI جایگاه‌ها کنار Builder موجود؛ بدون Builder موازی |
| `app/Filament/Pages/ManageSiteSettings.php` | تبدیل selectorهای Menu به خلاصه/لینک و خارج‌کردن آنها از writer عمومی؛ انتخاب Template حفظ شود |
| `app/Services/SettingsService.php` یا helper محدود متعلق به MenuService | خواندن/اعتبارسنجی سند انتساب و invalidation؛ حفظ APIهای Legacy برای مصرف‌کنندگان قبلی |
| `app/Services/MenuService.php` | انتخاب جایگاه مرکزی، provenance و compatibility reader؛ byId و فیلتر موجود حفظ شوند |
| `app/View/Components/Navigation.php` | مصرف جایگاه از همان MenuService، در صورت نیاز با حفظ constructor و maxDepth قبلی |
| `app/CMS/Blocks/SiteHeader/SiteHeaderRuntime.php` | مصرف header مرکزی و محدودکردن menu_id بلوک به سازگاری ثبت‌نشده |
| `app/CMS/Blocks/SiteHeader/SiteHeaderBlock.php` و `SiteHeaderDataNormalizer.php` | خلاصه/لینک، حذف انتخاب جدید Menu و حفظ round-trip Legacy |
| `app/CMS/Blocks/SiteFooter/SiteFooterRuntime.php`، `SiteFooterBlock.php` و `SiteFooterDataNormalizer.php` | نگاشت سه slot و حفظ ستون‌ها، عناوین و داده قدیمی |
| `database/seeders/DatabaseSeeder.php`، `IndustrialHeaderTemplateSeeder.php` و `CorporateFooterTemplateSeeder.php` | فقط مسیر نصب تازه رسمی؛ ثبت انتساب از IDهای همان اجرا و عدم بازنویسی انتخاب‌های موجود |
| `README.md` و راهنمای پنل | یک مسیر روشن برای ساخت/انتساب و توضیح Legacy/none |
| فایل‌های تست بخش بعد | قراردادهای انتخاب، persistence و UI |

به‌صورت پیش‌فرض نیازی به تغییر Migrationها، مدل Menu/MenuItem، NavigationSourceRegistry، NavigationSource، ServiceNavigationSource، MenuTreeService، Action Foundation یا Routeهای عمومی نیست. Header/Footer Template Resolver و Layout عمومی نیز برای صرف انتقال مالکیت انتساب نباید بازطراحی شوند. CSS/JS و rendererهای موجود فقط در صورت تصویب تغییر عمق/رفتار نمایشی، دامنه جدا خواهند داشت.

## ۱۳. تست‌های لازم در مرحله پیاده‌سازی

تست‌های زیر **پیشنهادند و در این ممیزی اجرا نشده‌اند**. همه Feature/Integrationها با Factory/Fixture و دیتابیس آزمایشی جدا یا SQLite در حافظه انجام شوند؛ موفقیت آنها تأیید Production نیست.

| گروه | سناریوهای ضروری |
| --- | --- |
| ذخیره و Scope | ساخت منو در جریان فعلی، انتساب به header، جایگزینی، یک منو در header و footer، بدون تغییر MenuItem، رد ID ناموجود/غیرفعال برای انتخاب جدید |
| وضعیت‌های انتساب | تفاوت entry غایب، none، menu معتبر، menu حذف‌شده/غیرفعال، JSON خراب، نسخه ناشناخته و mode نامعتبر؛ none/invalid نباید Legacy را فعال کنند |
| هدر Legacy | فقط header_menu_id؛ فقط main؛ فقط header؛ فقط block.menu_id؛ هر سه متناقض؛ انتخاب مثبت نامعتبر؛ مقادیر خام خراب قدیمی؛ هر دو renderer |
| Template و Preview | تغییر طراحی با حفظ header مرکزی، حفظ menu_idهای draft، Preview بدون انتساب مرکزی، Template بدون بلوک Navigation، فقدان/حذف Template؛ تفاوت fallback هدر و فوتر حفظ شود |
| فوتر | footer_menu_id/location در partial، سه منوی مستقل در شرکتی، shared Menu بین ستون‌ها، ستون خالی/غیرفعال، reorder/duplicate بلوک و هویت slot؛ null selector فوتر با default Template |
| حفاظت از داده | ذخیره نام سایت بدون دست‌زدن به انتساب، save Template بدون حذف Legacy ID، rollback انتخاب مرکزی، عدم پاک‌شدن locationهای سفارشی، حذف منو و باقی‌ماندن انتخاب نامعتبر |
| Navigation Core | Source معتبر/نامعتبر و غیرفعال، URL مرجع پس از تغییر slug، حذف مرجع، قرارداد انتشار مخصوص هر نوع، `_blank` و rel، فیلتر inactive، nesting و ترتیب برابر، جلوگیری از foreign/duplicate/missing آیتم در ذخیره درخت |
| چندسطحی/Responsive | حفظ ۲۰ سطح صنعتی و محدودیت فعلی هدر پیش‌فرض/فوتر؛ تست RTL flyout، Accordion، aria-controls یکتا، focus/keyboard و More overflow روی عرض‌های مختلف |
| کش و همزمانی | فعال‌کردن مسیر کش SettingsService در تست، گرم‌کردن انتخاب قبل از ذخیره، خواندن پس از تغییر، reload، دو ذخیره همزمان در جایگاه‌های متفاوت، اجرای چند worker در محیط تست مناسب |
| نصب تازه | فقط migration، full seed روی DB آزمایشی خالی، ساخت دستی و انتساب در پنل، اجرای Seeder صنعتی/شرکتی به‌تنهایی، تکرار seed با انتخاب قبلی بدون overwrite داده مدیر |
| مجوز | عملیات جایگاه برای مدیر مجاز، رد کاربر فاقد دسترسی و درخواست دستکاری‌شده، حفظ محدودیت مالکیت MenuItem |

فایل‌های موجود مناسب برای بسط یا Regression در مرحله بعد:

- `tests/Feature/MenuManagementUxTest.php`
- `tests/Feature/HomepageHeaderIntegrationTest.php`
- `tests/Feature/SiteHeaderTemplateTest.php`
- `tests/Feature/SiteFooterTemplateTest.php`
- `tests/Feature/SettingsAdminPageTest.php`
- `tests/Feature/ServiceNavigationSourceTest.php`
- `tests/Feature/BlogNavigationSourceTest.php`
- `tests/Unit/NavigationSourceRegistryTest.php`
- `tests/Unit/NavigationSourceVisibilityTest.php`
- `tests/Unit/MenuItemUrlResolutionTest.php`
- `tests/Unit/NavigationRendererTest.php`
- `tests/Unit/SiteHeaderDataNormalizerTest.php`
- `tests/js/industrial-navigation-overflow.test.js` برای رفتار overflow؛ بررسی بصری مرورگر همچنان جدا لازم است.

## ۱۴. تصمیم پیشنهادی و پاسخ نهایی

مرکز مدیریت همان MenuResource/Menu Builder فعلی باشد. انتساب‌های مرکزی فقط از آنجا نوشته شوند؛ Site Settings و Template Editor اطلاعات مؤثر و لینک مدیریت را نشان دهند. انتخاب طراحی Header/Footer، مستقل از انتخاب Menu باقی بماند. داده قدیمی حذف یا خودکار تبدیل نشود؛ تا تصمیم مدیر، خواندن Legacy دقیقاً قرارداد فعلی را حفظ کند.

**بله، با Schema فعلی و Navigation Core موجود قابل انجام است.** ظرفیت settings.value برای قرارداد نسخه‌دار جایگاه‌ها، ساختار MenuItem و درخت فعلی برای چندسطحی، و MenuService برای مصرف همان Menu کافی‌اند. شرط آن، افزودن انتخاب جایگاه در لایه موجود سرویس و UI، قطع writerهای تکراری، تعریف none/Legacy/invalid و نگاشت مستقل فوتر سه‌ستونه است. حفظ همه رفتارهای متفاوت Legacy و هم‌زمان یکسان‌کردن فوری آنها، بدون انتخاب صریح مدیر ممکن نیست؛ انتقال باید مرحله‌ای و قابل بازگشت باشد.
