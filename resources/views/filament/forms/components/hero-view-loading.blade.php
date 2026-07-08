@php
    $target = str($getStatePath())->beforeLast('.')->append('.'.($targetField ?? 'hero_1_theme'))->toString();
@endphp

<div
    wire:loading.flex
    wire:target="{{ $target }}"
    class="items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
    role="status"
    aria-live="polite"
>
    <x-filament::loading-indicator class="h-4 w-4" />
    <span>در حال اعمال نمای هیرو...</span>
</div>
