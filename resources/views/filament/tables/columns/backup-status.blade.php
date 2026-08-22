@php
    $backup = $getRecord();
    $available = $backup->status === \App\Enums\BackupStatus::Completed
        && $backup->local_disk
        && $backup->local_path
        && \Illuminate\Support\Facades\Storage::disk($backup->local_disk)->exists($backup->local_path);
@endphp

@if ($backup->status === \App\Enums\BackupStatus::Queued)
    <div class="inline-flex items-center gap-2 text-warning-700 dark:text-warning-400">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20">
            <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4 animate-spin" />
        </span>
        <span class="font-medium">در صف ایجاد</span>
    </div>
@elseif ($backup->status === \App\Enums\BackupStatus::Creating)
    <div class="inline-flex items-center gap-2 text-warning-700 dark:text-warning-400">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20">
            <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4 animate-spin" />
        </span>
        <span class="font-medium">در حال آماده‌سازی</span>
    </div>
@elseif ($available)
    <div class="inline-flex items-center gap-2 text-success-700 dark:text-success-400">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-success-600 text-white">
            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
        </span>
        <span class="font-medium">آماده دانلود</span>
    </div>
@elseif ($backup->status === \App\Enums\BackupStatus::Failed)
    <div class="space-y-1">
        <div class="inline-flex items-center gap-2 text-danger-700 dark:text-danger-400">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-danger-600 text-white">
                <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
            </span>
            <span class="font-medium">ناموفق</span>
        </div>
        <p class="text-xs text-danger-600 dark:text-danger-400">ایجاد نسخه پشتیبان ناموفق بود.</p>
        <p class="text-xs text-gray-600 dark:text-gray-300">{{ $backup->safeFailureMessage() }}</p>
    </div>
@else
    <div class="inline-flex items-center gap-2 text-danger-700 dark:text-danger-400">
        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-danger-600 text-white">
            <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
        </span>
        <span class="font-medium">فایل در دسترس نیست</span>
    </div>
@endif
