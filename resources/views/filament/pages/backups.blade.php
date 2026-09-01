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

        @php($latestRestore = $this->latestRestore())
        @if ($latestRestore)
            <div wire:poll.5s>
                <x-filament::section heading="وضعیت بازیابی اخیر" icon="heroicon-o-arrow-path-rounded-square">
                    <div class="space-y-2 text-sm">
                        <p><strong>{{ $latestRestore->status->label() }}</strong></p>
                        <p class="text-gray-600 dark:text-gray-300">نسخه هدف: {{ $latestRestore->backup?->archive_name ?: $latestRestore->backup?->uuid }}</p>
                        @if ($latestRestore->safetyBackup)
                            <p class="text-gray-600 dark:text-gray-300">نسخه ایمنی: {{ $latestRestore->safetyBackup->archive_name }}</p>
                        @endif
                        @if ($latestRestore->error_message)
                            <p class="text-danger-600">{{ $latestRestore->error_message }}</p>
                        @endif
                        @if ($latestRestore->status === \App\Enums\BackupRestoreStatus::Failed && $latestRestore->active_lock)
                            <p class="text-warning-600">سایت برای جلوگیری از نمایش وضعیت نیمه‌بازیابی‌شده در حالت نگهداری باقی مانده است. بازیابی دستی نسخه ایمنی و آزادسازی کنترل‌شده لازم است.</p>
                            @if ($latestRestore->safetyBackup)
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Recovery واقعی نسخه ایمنی از CLI:
                                    <code dir="ltr">php artisan backup:restore:recover {{ $latestRestore->uuid }} --force</code>
                                </p>
                            @endif
                        @endif
                    </div>
                </x-filament::section>
            </div>
        @endif

        <x-filament::section heading="نسخه‌های اخیر" description="فرایندهای در حال انجام و سه نسخه سالم آخر">
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
