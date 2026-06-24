<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class LsfProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            [
                'key' => 'structure',
                'slug' => 'اجزای-سازه-ال-اس-اف',
                'name' => 'اجزای سازه ال‌اس‌اف',
                'description' => 'پروفیل‌ها و اجزای اصلی مورد نیاز برای اجرای سازه‌های سبک فولادی.',
            ],
            [
                'key' => 'covering',
                'slug' => 'عایق-و-پوشش-ساختمانی',
                'name' => 'عایق و پوشش ساختمانی',
                'description' => 'انواع عایق و صفحات پوششی مناسب دیوار، سقف و نمای ساختمان.',
            ],
            [
                'key' => 'fasteners',
                'slug' => 'اتصالات-و-ملزومات-نصب',
                'name' => 'اتصالات و ملزومات نصب',
                'description' => 'پیچ‌ها، مهارها و ملزومات مورد استفاده در اتصال اجزای سازه.',
            ],
            [
                'key' => 'tools',
                'slug' => 'ابزار-اجرای-سازه-سبک',
                'name' => 'ابزار اجرای سازه سبک',
                'description' => 'ابزارهای تخصصی برای برش، مونتاژ و اجرای دقیق سازه‌های سبک.',
            ],
        ])->mapWithKeys(function (array $category, int $index): array {
            $record = ProductCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'status' => 'active',
                    'sort_order' => $index + 1,
                    'seo_title' => $category['name'],
                    'seo_description' => $category['description'],
                    'seo_image' => null,
                    'robots_index' => true,
                    'robots_follow' => true,
                ],
            );

            return [$category['key'] => $record];
        });

        $products = [
            [
                'slug' => 'galvanized-stud-profile',
                'category' => 'structure',
                'title' => 'پروفیل استاد گالوانیزه',
                'excerpt' => 'پروفیل قائم سبک و مقاوم برای اجرای چهارچوب دیوارهای سازه‌ای و جداکننده.',
                'content' => 'پروفیل استاد گالوانیزه برای ساخت اعضای قائم دیوارهای سازه سبک به کار می‌رود. پوشش روی، مقاومت مناسبی در برابر خوردگی ایجاد می‌کند و دقت ابعادی آن باعث افزایش سرعت و کیفیت اجرا می‌شود.',
                'price' => 1850000,
                'sale_price' => null,
            ],
            [
                'slug' => 'galvanized-runner-profile',
                'category' => 'structure',
                'title' => 'پروفیل رانر گالوانیزه',
                'excerpt' => 'پروفیل افقی ویژه مهار استادها در بخش بالا و پایین دیوارهای سبک فولادی.',
                'content' => 'پروفیل رانر به عنوان مسیر افقی نصب استادها استفاده می‌شود و به کف و سقف متصل می‌گردد. ساخت دقیق و پوشش گالوانیزه این محصول، مونتاژ سریع و اتصال پایدار اجزای دیوار را فراهم می‌کند.',
                'price' => 1720000,
                'sale_price' => 1590000,
            ],
            [
                'slug' => 'lightweight-floor-joist',
                'category' => 'structure',
                'title' => 'تیرچه سقفی فولادی سبک',
                'excerpt' => 'عضو باربر سبک برای اجرای سقف و کف میان‌طبقه در ساختمان‌های پیش‌ساخته.',
                'content' => 'تیرچه سقفی فولادی سبک با وزن کم و مقاومت مناسب، بارهای کف و سقف را به دیوارهای باربر منتقل می‌کند. این محصول برای دهانه‌های متداول ساختمانی مناسب است و اجرای تاسیسات را آسان‌تر می‌سازد.',
                'price' => 3480000,
                'sale_price' => null,
            ],
            [
                'slug' => 'moisture-resistant-gypsum-board',
                'category' => 'covering',
                'title' => 'پنل گچی مقاوم در برابر رطوبت',
                'excerpt' => 'صفحه پوششی مناسب دیوار و سقف فضاهای دارای رطوبت کنترل‌شده.',
                'content' => 'پنل گچی مقاوم در برابر رطوبت برای پوشش داخلی آشپزخانه، سرویس و فضاهای مشابه طراحی شده است. سطح یکنواخت آن زیرسازی مناسبی برای رنگ و پوشش نهایی فراهم می‌کند.',
                'price' => 1260000,
                'sale_price' => null,
            ],
            [
                'slug' => 'fiber-cement-facade-board',
                'category' => 'covering',
                'title' => 'تخته سیمانی نمای ساختمان',
                'excerpt' => 'صفحه مقاوم سیمانی برای پوشش بیرونی و زیرسازی نمای سازه‌های سبک.',
                'content' => 'تخته سیمانی نما در برابر رطوبت، تغییرات دما و ضربه مقاومت مناسبی دارد. این صفحه برای پوشش خارجی دیوار و اجرای انواع نمای خشک روی سازه‌های سبک قابل استفاده است.',
                'price' => 2890000,
                'sale_price' => 2690000,
            ],
            [
                'slug' => 'rock-wool-insulation',
                'category' => 'covering',
                'title' => 'عایق پشم سنگ ساختمانی',
                'excerpt' => 'عایق حرارتی و صوتی مناسب فضای میان استادهای دیوار و تیرچه‌های سقف.',
                'content' => 'عایق پشم سنگ انتقال صدا و اتلاف انرژی را در دیوار و سقف کاهش می‌دهد. ساختار الیافی و مقاومت حرارتی مناسب آن، آسایش و ایمنی بیشتری برای ساختمان ایجاد می‌کند.',
                'price' => 980000,
                'sale_price' => null,
            ],
            [
                'slug' => 'acoustic-sealing-tape',
                'category' => 'fasteners',
                'title' => 'نوار درزبندی صوتی',
                'excerpt' => 'نوار انعطاف‌پذیر برای کاهش انتقال صدا و لرزش در محل اتصال رانر به سازه.',
                'content' => 'نوار درزبندی صوتی زیر رانرهای کف، سقف و کناره دیوار نصب می‌شود. این محصول با پر کردن ناهمواری‌های سطح، انتقال لرزش و عبور صدا از درزهای پیرامونی را کاهش می‌دهد.',
                'price' => 420000,
                'sale_price' => null,
            ],
            [
                'slug' => 'winged-self-drilling-screw',
                'category' => 'fasteners',
                'title' => 'پیچ سرمته بالدار',
                'excerpt' => 'پیچ اتصال سریع صفحات پوششی به پروفیل‌های فولادی سبک.',
                'content' => 'پیچ سرمته بالدار برای اتصال صفحات چوبی و سیمانی به زیرسازی فولادی استفاده می‌شود. باله‌های بدنه مسیر عبور صفحه را باز می‌کنند و نوک سرمته اتصال نهایی به پروفیل را انجام می‌دهد.',
                'price' => 650000,
                'sale_price' => 590000,
            ],
            [
                'slug' => 'structural-anchor-bolt',
                'category' => 'fasteners',
                'title' => 'رول بولت مهار سازه',
                'excerpt' => 'مهار مکانیکی مقاوم برای اتصال رانر و صفحات زیرسازی به بتن.',
                'content' => 'رول بولت مهار سازه برای ایجاد اتصال مطمئن میان اجزای فولادی و بستر بتنی کاربرد دارد. انتخاب اندازه مناسب آن با توجه به بار طراحی و ضخامت عضو اتصال انجام می‌شود.',
                'price' => 740000,
                'sale_price' => null,
            ],
            [
                'slug' => 'light-steel-profile-cutter',
                'category' => 'tools',
                'title' => 'دستگاه برش پروفیل سبک',
                'excerpt' => 'ابزار دقیق و سریع برای برش پروفیل‌های گالوانیزه در کارگاه و محل پروژه.',
                'content' => 'دستگاه برش پروفیل سبک امکان آماده‌سازی دقیق استاد، رانر و تیرچه را فراهم می‌کند. طراحی مناسب آن باعث کاهش پلیسه، افزایش سرعت مونتاژ و بهبود کیفیت برش در محل اجرا می‌شود.',
                'price' => 28500000,
                'sale_price' => 26800000,
            ],
        ];

        foreach ($products as $index => $product) {
            Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    'product_category_id' => $categories[$product['category']]->id,
                    'title' => $product['title'],
                    'excerpt' => $product['excerpt'],
                    'content' => '<p>'.$product['content'].'</p>',
                    'price' => $product['price'],
                    'sale_price' => $product['sale_price'],
                    'sku' => null,
                    'status' => 'published',
                    'published_at' => now(),
                    'is_featured' => $index < 4,
                    'sort_order' => $index + 1,
                    'has_stock' => true,
                    'stock_status' => 'in_stock',
                    'seo_title' => $product['title'],
                    'seo_description' => $product['excerpt'],
                    'seo_image' => null,
                    'robots_index' => true,
                    'robots_follow' => true,
                ],
            );
        }
    }
}
