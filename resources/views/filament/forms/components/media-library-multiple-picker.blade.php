@php
    $statePath = $getStatePath();
    $images = collect($images ?? [])->values();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-slot name="label">{{ $getLabel() }}</x-slot>

    <div
        x-data="{
            open: false,
            search: '',
            selected: $wire.entangle(@js($statePath)),
            images: @js($images),
            isSelected(id) { return (this.selected || []).map(String).includes(String(id)) },
            toggle(id) {
                const value = String(id)
                this.selected = this.isSelected(value)
                    ? (this.selected || []).filter((item) => String(item) !== value)
                    : [...(this.selected || []), value]
            },
            remove(id) { this.selected = (this.selected || []).filter((item) => String(item) !== String(id)) },
            filteredImages() {
                const query = this.search.trim().toLowerCase()
                return query ? this.images.filter((image) => image.name.toLowerCase().includes(query)) : this.images
            },
            selectedLibraryImages() { return this.images.filter((image) => this.isSelected(image.id)) },
        }"
        x-on:keydown.escape.window="open = false"
        class="space-y-3"
    >
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" x-show="selectedLibraryImages().length">
            <template x-for="image in selectedLibraryImages()" x-bind:key="image.id">
                <div class="relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <img x-bind:src="image.url" x-bind:alt="image.name" class="aspect-square w-full object-cover">
                    <button type="button" x-on:click="remove(image.id)" aria-label="حذف تصویر" class="absolute left-2 top-2 rounded-full bg-gray-950/70 p-1 text-white">
                        <x-heroicon-o-x-mark class="h-4 w-4" />
                    </button>
                    <span class="block truncate px-2 py-1 text-xs" x-text="image.name"></span>
                </div>
            </template>
        </div>

        <x-filament::button type="button" x-on:click="open = true" size="sm">
            انتخاب تصاویر از کتابخانه رسانه
        </x-filament::button>

        <div x-cloak x-show="open" x-transition.opacity style="display: none; background-color: rgba(2, 6, 23, 0.78); backdrop-filter: blur(2px)" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-gray-950/60 p-4">
            <div x-on:click.outside="open = false" style="height: calc(100dvh - 2rem); max-height: calc(100dvh - 2rem)" class="flex h-[calc(100dvh-2rem)] max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
                <div style="padding: 1rem 1.25rem" class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold">انتخاب تصاویر گالری</h3>
                        <p class="text-sm text-gray-500">می‌توانید چند تصویر را از کتابخانه رسانه انتخاب کنید.</p>
                    </div>
                    <button type="button" x-on:click="open = false" aria-label="بستن" class="rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div style="padding: 1rem 1.25rem" class="border-b border-gray-200 p-5 dark:border-gray-800">
                    <x-filament::input.wrapper>
                        <x-filament::input type="search" x-model="search" placeholder="جست‌وجوی تصاویر..." />
                    </x-filament::input.wrapper>
                </div>
                <div style="padding: 1.25rem" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                    <div x-show="images.length === 0" class="rounded-lg border border-dashed p-10 text-center text-sm text-gray-500">تصویری در کتابخانه رسانه وجود ندارد.</div>
                    <div x-show="images.length > 0 && filteredImages().length === 0" class="rounded-lg border border-dashed p-10 text-center text-sm text-gray-500">تصویری مطابق جست‌وجوی شما پیدا نشد.</div>
                    <div
                        class="grid gap-3"
                        style="display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.75rem"
                    >
                        <template x-for="image in filteredImages()" x-bind:key="image.id">
                            <button type="button" x-on:click="toggle(image.id)" style="min-width: 0; width: 100%; overflow: hidden" class="relative overflow-hidden rounded-lg border bg-white text-right shadow-sm dark:bg-gray-950" x-bind:class="isSelected(image.id) ? 'border-primary-600 ring-2 ring-primary-500/40' : 'border-gray-200 dark:border-gray-800'">
                                <img x-bind:src="image.url" x-bind:alt="image.name" style="display: block; width: 100%; height: 9rem; object-fit: cover" class="w-full object-cover">
                                <span class="block truncate px-3 py-2 text-sm" x-text="image.name"></span>
                                <span x-show="isSelected(image.id)" class="absolute left-2 top-2 rounded-full bg-primary-600 p-1 text-white"><x-heroicon-o-check class="h-4 w-4" /></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div style="padding: 1rem 1.25rem" class="border-t border-gray-200 px-5 py-4 text-left dark:border-gray-800">
                    <x-filament::button type="button" x-on:click="open = false">تأیید انتخاب</x-filament::button>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
