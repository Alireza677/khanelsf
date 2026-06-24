@php
    $statePath = $getStatePath();
    $fieldId = $getId();
    $images = collect($images ?? [])->values();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <x-slot name="label">
        {{ $getLabel() }}
    </x-slot>

    <div
        x-data="{
            open: false,
            search: '',
            selected: $wire.entangle(@js($statePath)),
            images: @js($images),
            get selectedImage() {
                return this.images.find((image) => String(image.id) === String(this.selected)) || null
            },
            filteredImages() {
                const query = this.search.trim().toLowerCase()

                if (! query) {
                    return this.images
                }

                return this.images.filter((image) => image.name.toLowerCase().includes(query))
            },
            choose(id) {
                this.selected = id
                this.open = false
            },
            clear() {
                this.selected = null
            },
        }"
        x-on:keydown.escape.window="open = false"
        class="space-y-3"
    >
        <input type="hidden" {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}">

        <button
            type="button"
            x-on:click="open = true"
            class="group flex min-h-32 max-w-xl items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 text-sm text-gray-600 transition hover:border-primary-500 hover:bg-primary-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-primary-500"
        >
            <template x-if="selectedImage">
                <div class="relative h-36 w-full">
                    <img
                        x-bind:src="selectedImage.url"
                        x-bind:alt="selectedImage.name"
                        class="h-full w-full object-cover"
                    >
                    <div class="absolute inset-x-0 bottom-0 bg-black/60 px-4 py-3 text-left text-white">
                        <span class="block truncate font-medium" x-text="selectedImage.name"></span>
                        <span class="text-xs opacity-80">برای انتخاب تصویر دیگر کلیک کنید</span>
                    </div>
                </div>
            </template>

            <template x-if="! selectedImage">
                <div class="px-6 py-10 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-500 shadow-sm dark:bg-gray-800">
                        <x-heroicon-o-photo class="h-6 w-6" />
                    </div>
                    <span class="block font-medium text-gray-900 dark:text-white">انتخاب از کتابخانه رسانه</span>
                    <span class="mt-1 block text-xs text-gray-500">برای مشاهده تصاویر بارگذاری‌شده کلیک کنید</span>
                </div>
            </template>
        </button>

        <div class="flex gap-2">
            <x-filament::button type="button" x-on:click="open = true" size="sm">
                انتخاب تصویر
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                x-show="selected"
                x-on:click="clear"
            >
                حذف
            </x-filament::button>
        </div>

        <div
            x-cloak
            x-show="open"
            x-transition.opacity
            style="display: none; background-color: rgba(2, 6, 23, 0.78); backdrop-filter: blur(2px)"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-gray-950/60 p-4"
        >
            <div
                x-on:click.outside="open = false"
                style="height: calc(100dvh - 2rem); max-height: calc(100dvh - 2rem)"
                class="flex h-[calc(100dvh-2rem)] max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            >
                <div style="padding: 1rem 1.25rem" class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">انتخاب تصویر شاخص</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">یک تصویر بارگذاری‌شده را جست‌وجو و انتخاب کنید.</p>
                    </div>

                    <button
                        type="button"
                        x-on:click="open = false"
                        class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white"
                        aria-label="بستن"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div style="padding: 1rem 1.25rem" class="border-b border-gray-200 p-5 dark:border-gray-800">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="search"
                            x-model="search"
                            placeholder="جست‌وجوی تصاویر..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div style="padding: 1.25rem" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                    <template x-if="images.length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            تصویر قابل استفاده‌ای در کتابخانه رسانه پیدا نشد.
                        </div>
                    </template>

                    <template x-if="images.length > 0 && filteredImages().length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            تصویری مطابق جست‌وجوی شما پیدا نشد.
                        </div>
                    </template>

                    <div class="grid gap-3" style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.75rem">
                        <template x-for="image in filteredImages()" x-bind:key="image.id">
                            <button
                                type="button"
                                x-on:click="choose(image.id)"
                                style="min-width: 0; width: 100%; overflow: hidden"
                                class="overflow-hidden rounded-lg border bg-white text-left shadow-sm transition hover:border-primary-500 hover:ring-2 hover:ring-primary-500/30 dark:bg-gray-950"
                                x-bind:class="String(selected) === String(image.id) ? 'border-primary-600 ring-2 ring-primary-500/40' : 'border-gray-200 dark:border-gray-800'"
                            >
                                <img
                                    x-bind:src="image.url"
                                    x-bind:alt="image.name"
                                    style="display: block; width: 100%; height: 9rem; object-fit: cover"
                                    class="w-full object-cover"
                                >
                                <span class="block truncate px-3 py-2 text-sm text-gray-700 dark:text-gray-200" x-text="image.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
