<x-filament-panels::page>
    <style>
        .king-product-import-table { min-width: 0; }
        .king-product-import-table .fi-ta-content { overflow-x: auto; }
        .king-product-import-table table { min-width: 90rem; }
        .king-product-import-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        @media (min-width: 768px) { .king-product-import-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    </style>
    <div class="space-y-6" dir="rtl">
        <div wire:loading role="status" aria-live="polite" class="text-sm text-gray-500 dark:text-gray-400">
            در حال پردازش اطلاعات، لطفاً صبر کنید…
        </div>
        @php
            $batch = $this->currentImport();
        @endphp
        @if ($batch)
            @if ($batch->status === 'processing')
                <div wire:poll.2s="continueImport" wire:key="import-progress-{{ $batch->id }}" role="status" aria-live="polite" class="space-y-2">
                    <p>در حال ثبت: {{ array_sum($batch->summary['results'] ?? []) }} از {{ $batch->summary['products'] }} ردیف بررسی شد. صفحه را تا پایان باز نگه دارید.</p>
                    <progress class="w-full" aria-label="پیشرفت درون‌ریزی محصولات" value="{{ array_sum($batch->summary['results'] ?? []) }}" max="{{ $batch->summary['products'] }}"></progress>
                </div>
            @endif
            <x-filament::section heading="۳. بررسی و پیش‌نمایش" description="برای هر ردیف، عملیات پیشنهادی و هشدارها را بررسی کنید. تصمیم‌های دسته‌بندی و تطبیق، تا تأیید نهایی محصولی را تغییر نمی‌دهند.">
                <div class="space-y-4" aria-live="polite">
                    <p class="text-sm break-words">
                        فایل: {{ $batch->original_filename }} · شماره پیگیری: {{ $batch->id }} · تعداد شیت‌ها: {{ count($batch->sheet_names) }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $batch->mode === 'create_only' ? 'روش ثبت: فقط ایجاد' : 'روش ثبت: ایجاد و به‌روزرسانی ردیف‌های تأییدشده' }}
                        · {{ $batch->status === 'completed' ? 'پردازش پایان یافته است' : ($batch->status === 'processing' ? 'در حال پردازش؛ در صورت قطع، این سابقه را دوباره باز کنید' : 'پیش‌نمایش؛ هنوز محصولی ثبت نشده است') }}
                    </p>
                    <dl class="king-product-import-stats">
                        @foreach (\App\Models\ProductImportRow::ACTION_LABELS as $key => $label)
                            <div>
                                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="text-xl font-semibold">{{ $key === 'new' ? ($batch->summary['new_products'] ?? 0) : ($key === 'update' ? ($batch->summary['updatable_products'] ?? 0) : ($batch->summary['actions'][$key] ?? 0)) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="text-sm">
                        دسته‌های استفاده‌شده: {{ $batch->summary['used_categories'] ?? 0 }} · مشخصات مجاز: {{ $batch->summary['eligible_specifications'] ?? 0 }}
                        · مشخصات خارج از فهرست محصولات، بدون ورود: {{ $batch->summary['ignored_specifications'] ?? 0 }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        دسته موجود: {{ $batch->summary['category_counts']['existing'] ?? 0 }} · ایجاد دسته تأییدشده: {{ $batch->summary['category_counts']['new'] ?? 0 }}
                        · دسته نیازمند تصمیم: {{ $batch->summary['category_counts']['pending'] ?? 0 }}
                    </p>
                    @foreach ($batch->warnings as $warning)
                        <p class="text-sm" role="note">{{ $warning }}</p>
                    @endforeach
                    @if ($batch->status === 'completed')
                        <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                            <p class="font-semibold">نتیجه نهایی</p>
                            <dl class="king-product-import-stats">
                                @foreach (\App\Models\ProductImportRow::RESULT_LABELS as $key => $label)
                                    <div><dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt><dd class="text-xl font-semibold">{{ $batch->summary['results'][$key] ?? 0 }}</dd></div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @else
            <x-filament::section heading="ورود محصولات از اکسل" description="قالب استاندارد را دریافت کنید، اطلاعات محصولات را تکمیل و فایل را آپلود کنید.">
                <ol class="list-inside list-decimal space-y-2 text-sm">
                    <li>آپلود فایل و اعتبارسنجی ساختار، قیمت‌ها و شناسه‌ها</li>
                    <li>انتخاب روش ثبت و تطبیق دسته‌ها و محصولات مشابه</li>
                    <li>بررسی جدول قابل جست‌وجو و دریافت گزارش خطا</li>
                    <li>تأیید نهایی و مشاهده نتیجه هر ردیف</li>
                </ol>
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">محصولات با مقادیر فایل ثبت می‌شوند. مقادیر خالی انتشار و خرید، پیش‌فرض غیرفعال دارند. تصاویر و اسناد از این مسیر وارد نمی‌شوند.</p>
            </x-filament::section>
        @endif
        <div class="king-product-import-table">{{ $this->table }}</div>
    </div>
</x-filament-panels::page>
