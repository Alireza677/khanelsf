<x-filament-panels::page>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}

        @if ($duplicateFileNames !== [])
            <div class="text-sm font-medium text-warning-600 dark:text-warning-400" role="alert">
                نام فایل‌های زیر تکراری است:
                {{ implode('، ', $duplicateFileNames) }}.
                در صورت ادامه، یک عدد به انتهای نام آن‌ها اضافه می‌شود.
            </div>
        @endif

        <x-filament::button type="submit" form="form">
            بارگذاری
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
