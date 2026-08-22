<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            heading="نسخه‌های پشتیبان"
            description="فقط سه نسخه آخر روی سرور نگهداری می‌شود. برای نگهداری بلندمدت، نسخه موردنظر را دانلود و در محل امن ذخیره کنید."
            icon="heroicon-o-server-stack"
        >
            <p class="text-sm text-gray-600 dark:text-gray-300">
                نسخه‌ها در فضای خصوصی سرور ذخیره می‌شوند و فقط مدیران می‌توانند آن‌ها را از داخل CMS دانلود کنند.
            </p>
        </x-filament::section>

        <x-filament::section heading="نسخه‌های اخیر" description="فرایندهای در حال انجام و سه نسخه سالم آخر">
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
