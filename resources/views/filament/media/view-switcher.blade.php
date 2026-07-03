<div
    class="media-view-switcher"
    x-data="{ mediaView: $wire.entangle('mediaView') }"
    role="group"
    aria-label="نوع نمایش رسانه‌ها"
>
    <button
        type="button"
        class="media-view-switcher__button"
        x-bind:class="{ 'is-active': mediaView === 'grid' }"
        wire:click="setMediaView('grid')"
        wire:loading.attr="disabled"
        wire:target="setMediaView"
        title="نمایش گرید"
        aria-label="نمایش گرید"
    >
        <x-filament::icon icon="heroicon-o-squares-2x2" class="h-5 w-5" />
    </button>

    <button
        type="button"
        class="media-view-switcher__button"
        x-bind:class="{ 'is-active': mediaView === 'list' }"
        wire:click="setMediaView('list')"
        wire:loading.attr="disabled"
        wire:target="setMediaView"
        title="نمایش لیستی"
        aria-label="نمایش لیستی"
    >
        <x-filament::icon icon="heroicon-o-list-bullet" class="h-5 w-5" />
    </button>
</div>
