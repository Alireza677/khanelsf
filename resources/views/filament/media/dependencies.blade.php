<div dir="rtl" class="king-media-dependencies">
    <div class="king-media-source">
        @if (str_starts_with((string) $record->mime_type, 'image/'))
            <img src="{{ $record->getUrl() }}" alt="" class="king-media-source-thumbnail">
        @else
            <x-heroicon-o-document class="king-media-source-thumbnail text-gray-400" aria-hidden="true" />
        @endif
        <div class="king-media-source-copy">
            <strong>{{ $record->displayTitle() }}</strong>
            <p role="status" class="text-sm text-gray-500 dark:text-gray-400">
                @if ($dependencies)
                    این رسانه در <x-filament::badge size="sm">{{ count($dependencies) }} محل</x-filament::badge> استفاده شده است.
                @else
                    محل استفاده‌ای در بخش‌های ثبت‌شده پیدا نشد.
                @endif
            </p>
        </div>
    </div>
    @if ($dependencies)
        <h3 class="king-media-section-heading">
            <x-heroicon-o-square-3-stack-3d aria-hidden="true" />
            محل‌های استفاده <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ count($dependencies) }} مورد)</span>
        </h3>
        <ul class="king-media-usage-list" aria-label="محل‌های استفاده از رسانه" tabindex="0">
            @foreach ($dependencies as $dependency)
                <li class="king-media-usage-row">
                    <div class="king-media-usage-title">
                        <x-heroicon-o-folder aria-hidden="true" />
                        <span>{{ $dependency['type'] }}: {{ $dependency['title'] }}</span>
                    </div>
                    <div class="king-media-usage-kind">
                        <x-filament::badge :color="$dependency['replaceable'] ? 'primary' : 'gray'" size="sm">{{ $dependency['label'] }}</x-filament::badge>
                    </div>
                    <div class="king-media-usage-links">
                        @if ($dependency['edit_url'])
                            <x-filament::button :href="$dependency['edit_url']" tag="a" target="_blank" rel="noopener" size="sm" color="primary" outlined icon="heroicon-o-pencil-square" :aria-label="'ویرایش '.$dependency['title']">ویرایش</x-filament::button>
                        @endif
                        @if ($dependency['public_url'])
                            <x-filament::button :href="$dependency['public_url']" tag="a" target="_blank" rel="noopener" size="sm" color="gray" outlined icon="heroicon-o-arrow-top-right-on-square" :aria-label="'مشاهده '.$dependency['title']">مشاهده</x-filament::button>
                        @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">بدون نشانی عمومی</span>
                        @endif
                    </div>
                    @if (! $dependency['replaceable'])
                        <p class="king-media-usage-note text-sm text-gray-500 dark:text-gray-400">{{ $dependency['diagnostic'] ?? (($dependency['status'] ?? '') === 'restricted' ? 'دسترسی لازم برای جایگزینی ندارید.' : 'نیازمند اصلاح دستی؛ حذف تا رفع این ارجاع مسدود است.') }}</p>
                    @endif
                    @if ($dependency['shared_template'] ?? false)
                        <p class="king-media-usage-note text-sm text-gray-500 dark:text-gray-400">تغییر قالب در همهٔ محتواهای استفاده‌کننده از آن اعمال می‌شود.</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
