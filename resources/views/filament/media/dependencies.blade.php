<div dir="rtl" class="space-y-4">
    <div class="flex flex-wrap items-center gap-4">
        @if (str_starts_with((string) $record->mime_type, 'image/'))
            <img src="{{ $record->getUrl() }}" alt="{{ $record->displayTitle() }}" style="width: 6rem; height: 6rem; object-fit: cover; border-radius: .75rem">
        @endif
        <div style="min-width: 0; max-width: 100%; overflow-wrap: anywhere">
            <strong>{{ $record->displayTitle() }}</strong>
            <p role="status" class="text-sm text-gray-500">{{ count($dependencies) }} محل استفاده / ارجاع ثبت‌شده</p>
        </div>
    </div>
    @if ($dependencies)
        <p class="text-sm">حذف تا رفع تمام ارجاع‌ها مسدود است. فقط محل‌های قابل جایگزینی و دارای مجوز را انتخاب کنید.</p>
        <ul class="space-y-3" style="max-height: 18rem; overflow-y: auto; padding: .25rem">
            @foreach ($dependencies as $dependency)
                <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" style="overflow-wrap: anywhere">
                    <strong class="text-sm">{{ $dependency['type'] }}: {{ $dependency['title'] }}</strong>
                    <p class="text-sm text-gray-500" style="overflow-wrap: anywhere">{{ $dependency['label'] }}</p>
                    <p class="text-sm">{{ $dependency['replaceable'] ? 'قابل جایگزینی با انتخاب شما' : 'نیازمند جایگزینی دستی یا بررسی توسط کاربر دارای مجوز' }}</p>
                    @if ($dependency['shared_template'] ?? false)
                        <p class="text-sm">این ارجاع متعلق به یک قالب است؛ تغییر آن در تمام محتواهای استفاده‌کننده از قالب دیده می‌شود.</p>
                    @endif
                    @if ($dependency['diagnostic'] ?? null)
                        <p class="text-sm">{{ $dependency['diagnostic'] }}</p>
                    @endif
                    <div class="flex flex-wrap gap-3">
                        @if ($dependency['edit_url'])
                            <x-filament::link :href="$dependency['edit_url']" target="_blank" rel="noopener">ویرایش در مدیریت</x-filament::link>
                        @endif
                        @if ($dependency['public_url'])
                            <x-filament::link :href="$dependency['public_url']" target="_blank" rel="noopener">مشاهده در سایت</x-filament::link>
                        @else
                            <span class="text-xs text-gray-500">نشانی عمومیِ قابل نمایش ندارد.</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-sm">ارجاعی در Consumerهای ثبت‌شده پیدا نشد. حذف فایل قابل بازگشت نیست.</p>
    @endif
</div>
