@php
    $statePath = $getStatePath();
    $fieldId = $getId();
    $images = collect($images ?? [])->values();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-slot name="label">{{ $getLabel() }}</x-slot>

    <div
        x-data="{
            open: false,
            search: '',
            selected: $wire.entangle(@js($statePath)),
            draft: [],
            images: @js($images),
            normalize(ids) { return [...new Set((ids || []).map(String))] },
            openPicker() {
                this.draft = this.normalize(this.selected)
                this.search = ''
                this.open = true
            },
            cancel() { this.open = false },
            confirm() {
                this.selected = this.normalize(this.draft)
                this.open = false
            },
            isSelected(id) { return this.draft.includes(String(id)) },
            toggle(id) {
                const value = String(id)
                this.draft = this.isSelected(value)
                    ? this.draft.filter((item) => item !== value)
                    : [...this.draft, value]
            },
            remove(id) { this.selected = this.normalize(this.selected).filter((item) => item !== String(id)) },
            filteredImages() {
                const query = this.search.trim().toLowerCase()
                return query ? this.images.filter((image) => [image.title, image.name]
                    .some((value) => String(value || '').toLowerCase().includes(query))) : this.images
            },
            selectedLibraryImages() {
                return this.normalize(this.selected).map((id) => this.images.find((image) => String(image.id) === id)).filter(Boolean)
            },
            move(id, offset) {
                const ids = this.normalize(this.selected)
                const index = ids.indexOf(String(id))
                const next = index + offset
                if (index === -1 || next < 0 || next >= ids.length) return
                const [item] = ids.splice(index, 1)
                ids.splice(next, 0, item)
                this.selected = ids
            },
            reorder(event) {
                const grid = event.target
                const ids = grid.sortable.toArray()
                // Restore the old DOM order before Alpine reconciles its keyed loop.
                this.normalize(this.selected).forEach((id) => {
                    const node = [...grid.children].find((item) => item.getAttribute('x-sortable-item') === id)
                    if (node) grid.append(node)
                })
                this.selected = ids
            },
        }"
        x-on:keydown.escape.window="if (open) { $event.stopPropagation(); cancel() }"
        x-on:media-library-images-refreshed.window="if ($event.detail.statePath === @js($statePath)) images = $event.detail.images"
        class="space-y-3"
        data-media-library-multiple-picker
    >
        <div x-show="selectedLibraryImages().length">
            <div
                x-sortable
                x-on:end.stop="reorder($event)"
                data-sortable-animation-duration="0"
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 8rem), 1fr)); gap: .75rem"
            >
                <template x-for="(image, index) in selectedLibraryImages()" x-bind:key="String(image.id)">
                    <div x-bind:x-sortable-item="String(image.id)" class="relative min-w-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                        <img x-bind:src="image.url" x-bind:alt="image.title || image.name" draggable="false" style="aspect-ratio: 1; width: 100%; object-fit: cover" class="w-full object-cover">
                        <button type="button" x-on:click="remove(image.id)" x-bind:aria-label="'حذف ' + (image.title || image.name) + ' از گالری'" style="position: absolute; inset-block-start: .5rem; inset-inline-end: .5rem; background-color: rgb(var(--gray-950) / .7); color: rgb(var(--gray-50))" class="rounded-full p-2">
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                        <span class="block truncate px-2 py-1 text-xs" x-text="image.title || image.name"></span>
                        <div class="flex items-center justify-between gap-1 border-t border-gray-200 p-1 dark:border-gray-700">
                            <button type="button" x-sortable-handle aria-label="مرتب‌سازی با کشیدن تصویر" class="cursor-move rounded p-2">
                                <x-heroicon-o-bars-3 class="h-4 w-4" />
                            </button>
                            <button type="button" x-on:click="move(image.id, -1)" x-bind:disabled="index === 0" aria-label="انتقال به جایگاه قبلی" class="rounded p-2 disabled:opacity-30">
                                <x-heroicon-o-chevron-up class="h-4 w-4" />
                            </button>
                            <button type="button" x-on:click="move(image.id, 1)" x-bind:disabled="index === selectedLibraryImages().length - 1" aria-label="انتقال به جایگاه بعدی" class="rounded p-2 disabled:opacity-30">
                                <x-heroicon-o-chevron-down class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <x-filament::button type="button" x-on:click="openPicker()" size="sm" data-media-gallery-trigger>
            انتخاب تصاویر گالری
        </x-filament::button>
        <span class="text-sm text-gray-500" x-text="normalize(selected).length + ' تصویر در گالری'"></span>

        <template x-teleport="body">
            <div x-cloak x-show="open" x-trap.inert.noscroll="open" style="display: none" class="cms-modal-layer flex items-center justify-center overflow-hidden p-4" dir="rtl" role="dialog" aria-modal="true" aria-labelledby="{{ $fieldId }}-gallery-heading">
                <div x-on:click="cancel()" style="background-color: rgb(var(--gray-950) / .6)" class="cms-modal-backdrop" aria-hidden="true"></div>
                <div style="height: calc(100dvh - 2rem); max-height: calc(100dvh - 2rem)" class="cms-modal-panel flex w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
                    <div style="padding: 1rem 1.25rem" class="flex items-center justify-between gap-3 border-b border-gray-200 dark:border-gray-800">
                        <div class="min-w-0">
                            <h3 id="{{ $fieldId }}-gallery-heading" class="text-lg font-semibold">انتخاب تصاویر گالری</h3>
                            <p class="text-sm text-gray-500">تصاویر را انتخاب کنید و سپس «تأیید انتخاب» را بزنید.</p>
                        </div>
                        <button type="button" x-on:click="cancel()" aria-label="بستن و لغو تغییرات انتخاب" class="rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <div style="padding: 1rem 1.25rem" class="space-y-3 border-b border-gray-200 dark:border-gray-800">
                        <x-filament::input.wrapper>
                            <x-filament::input type="search" x-model="search" placeholder="جست‌وجوی نام یا عنوان تصویر..." aria-label="جست‌وجوی تصاویر کتابخانه" />
                        </x-filament::input.wrapper>
                        @if ($field->hasAction('refreshLibrary'))
                            <div class="flex flex-wrap items-center gap-3">
                                @if (\App\Filament\Resources\MediaResource::canAccess())
                                    <x-filament::button tag="a" :href="\App\Filament\Resources\MediaResource::getUrl('upload')" target="_blank" rel="noopener" color="gray" size="sm">
                                        بارگذاری تصویر جدید (پنجره جدید)
                                    </x-filament::button>
                                @endif
                                {{ $getAction('refreshLibrary') }}
                            </div>
                            <p class="text-xs text-gray-500">پس از بارگذاری در بخش رسانه، کتابخانه را تازه‌سازی کنید؛ انتخاب فعلی شما حفظ می‌شود.</p>
                        @endif
                    </div>
                    <div style="padding: 1.25rem" class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                        <div x-show="images.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">تصویری در کتابخانه رسانه وجود ندارد.</div>
                        <div x-show="images.length > 0 && filteredImages().length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">تصویری مطابق جست‌وجوی شما پیدا نشد.</div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 8rem), 1fr)); gap: .75rem">
                            <template x-for="image in filteredImages()" x-bind:key="String(image.id)">
                                <button type="button" x-on:click="toggle(image.id)" x-bind:aria-pressed="isSelected(image.id)" class="relative min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white text-right shadow-sm dark:border-gray-800 dark:bg-gray-950" x-bind:style="isSelected(image.id) ? 'border-color: rgb(var(--primary-600)); box-shadow: 0 0 0 2px rgb(var(--primary-500) / .25)' : ''">
                                    <img x-bind:src="image.url" x-bind:alt="image.title || image.name" style="display: block; width: 100%; height: 8rem; object-fit: cover" draggable="false">
                                    <span class="block truncate px-3 py-2 text-sm" x-text="image.title || image.name"></span>
                                    <span x-show="isSelected(image.id)" style="position: absolute; inset-block-start: .5rem; inset-inline-end: .5rem; background-color: rgb(var(--primary-600)); color: rgb(var(--gray-50))" class="rounded-full p-1" aria-hidden="true"><x-heroicon-o-check class="h-4 w-4" /></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div style="padding: 1rem 1.25rem" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 dark:border-gray-800">
                        <span role="status" aria-atomic="true" class="text-sm text-gray-500" x-text="draft.length + ' تصویر انتخاب شده است'"></span>
                        <div class="flex items-center gap-2">
                            <x-filament::button type="button" color="gray" x-on:click="cancel()">انصراف</x-filament::button>
                            <x-filament::button type="button" x-on:click="confirm()">تأیید انتخاب</x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-dynamic-component>
