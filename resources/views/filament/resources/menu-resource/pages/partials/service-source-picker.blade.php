@php
    $servicePicker = $this->getServicePicker();
    $searchingServices = filled(trim($this->serviceSearch));
@endphp

<div class="menu-builder-accordion" dir="rtl">
    <button
        type="button"
        class="menu-builder-accordion-trigger"
        x-on:click="open = open === 'services' ? null : 'services'; if (open === 'services' && ! $wire.servicesLoaded) $wire.loadServices()"
        x-bind:aria-expanded="(open === 'services').toString()"
        aria-controls="menu-service-picker-panel"
    >
        <x-filament::icon icon="heroicon-o-squares-2x2" class="h-4 w-4 text-gray-500" />
        <span class="min-w-0 flex-1">
            <span class="block text-xs font-semibold text-gray-950 dark:text-white">خدمات</span>
            <span class="block text-[10px] text-gray-500">خدمات منتشرشده و زیرخدمات</span>
        </span>
        <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 text-gray-400 transition" x-bind:class="{ 'rotate-180': open === 'services' }" />
    </button>

    <div id="menu-service-picker-panel" x-show="open === 'services'" x-cloak x-transition class="menu-builder-accordion-panel">
        <form wire:submit="addSelectedServices" class="grid gap-2.5">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.350ms="serviceSearch"
                    maxlength="200"
                    placeholder="جست‌وجوی خدمات…"
                    aria-label="جست‌وجوی خدمات در همه سطوح"
                />
            </x-filament::input.wrapper>

            <div class="text-[10px] text-gray-500" role="status" wire:loading wire:target="loadServices,serviceSearch,toggleServiceBranch,changeServicePage">در حال بارگذاری خدمات…</div>

            <div class="menu-builder-page-list rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900" data-search-driver="server" data-search-source="service">
                @if ($servicePicker !== null)
                    @include('filament.resources.menu-resource.pages.partials.service-source-tree', [
                        'group' => $servicePicker,
                        'depth' => 0,
                        'searching' => $searchingServices,
                    ])
                    @if ($servicePicker['items'] === [])
                        <p class="px-3 py-8 text-center text-xs text-gray-500" role="status">{{ $searchingServices ? 'هیچ خدمتی با این عبارت پیدا نشد.' : 'خدمت قابل نمایش عمومی در این صفحه وجود ندارد.' }}</p>
                    @endif
                @endif
            </div>

            <p class="text-[10px] leading-5 text-gray-500">انتخاب‌ها بین صفحه‌ها و جست‌وجوها حفظ می‌شوند. هر خدمت مستقل به منو اضافه می‌شود.</p>
            <p class="text-xs text-gray-500" x-text="'انتخاب‌شده: ' + $wire.selectedServiceIds.length"></p>

            @foreach ($errors->get('selectedServiceIds*') as $messages)
                @foreach ($messages as $message)
                    <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @endforeach
            @endforeach

            <x-filament::button type="submit" icon="heroicon-m-plus" wire:target="addSelectedServices" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="addSelectedServices">افزودن به منو</span>
                <span wire:loading.flex wire:target="addSelectedServices" class="items-center gap-1.5">
                    <x-filament::loading-indicator class="h-3.5 w-3.5" />
                    در حال افزودن…
                </span>
            </x-filament::button>
        </form>
    </div>
</div>
