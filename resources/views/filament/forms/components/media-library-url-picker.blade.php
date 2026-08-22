@php
    $statePath = $getStatePath();
    $images = collect($images ?? [])->values();
    $placeholderUrl = app(\App\Services\SettingsService::class)->imagePlaceholderUrl();
    $isBlockImageField = str_contains($statePath, 'blocks.');
    $sourceIdPath = filled($sourceIdField ?? null)
        ? str($statePath)->beforeLast('.')->append('.'.$sourceIdField)->toString()
        : null;
@endphp

@once
    <script>
        window.__mediaLibraryImageItems = @js($images);
    </script>

    <style>
        .block-media-picker {
            width: 100%;
        }

        .block-media-picker__trigger {
            width: 100%;
        }

        .block-media-picker__preview {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
        }

        .block-media-picker__empty {
            min-height: 12rem;
            width: 100%;
        }

        .block-media-picker__overlay {
            position: absolute;
            inset: 0;
            z-index: 10;
            background: rgba(0, 0, 0, .22);
            opacity: 0;
            pointer-events: none;
            transition: opacity 150ms ease;
        }

        .block-media-picker__action {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 0;
            min-height: 0;
            overflow: hidden;
            padding: 0 1rem;
            background: rgba(0, 0, 0, .92);
            color: #fff;
            opacity: 0;
            visibility: hidden;
            transition: height 150ms ease, min-height 150ms ease, opacity 150ms ease, visibility 150ms ease;
        }

        .block-media-picker__remove {
            position: absolute;
            left: .75rem;
            top: .75rem;
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: .375rem;
            background: rgba(0, 0, 0, .82) !important;
            color: #fff !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .2);
            opacity: 0;
            transition: opacity 150ms ease, background-color 150ms ease, transform 150ms ease;
        }

        .block-media-picker:hover .block-media-picker__overlay,
        .block-media-picker:focus-within .block-media-picker__overlay,
        .block-media-picker:hover .block-media-picker__remove,
        .block-media-picker:focus-within .block-media-picker__remove {
            opacity: 1;
        }

        .block-media-picker:hover .block-media-picker__action,
        .block-media-picker:focus-within .block-media-picker__action {
            height: 2.5rem;
            min-height: 2.5rem;
            opacity: 1;
            visibility: visible;
        }

        .block-media-picker__remove:hover,
        .block-media-picker__remove:focus-visible {
            background: rgb(220, 38, 38) !important;
            transform: scale(1.04);
        }

        @media (hover: none), (pointer: coarse) {
            .block-media-picker__remove {
                opacity: 1;
            }
        }
    </style>
@endonce

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
            selectedUrl: $wire.entangle(@js($statePath)),
            selectedSourceId: @if ($sourceIdPath) $wire.entangle(@js($sourceIdPath)) @else null @endif,
            placeholderUrl: @js($placeholderUrl),
            images: window.__mediaLibraryImageItems || [],
            previewUrl() {
                return this.selectedUrl || this.placeholderUrl || ''
            },
            filteredImages() {
                const query = this.search.trim().toLowerCase()

                if (! query) {
                    return this.images
                }

                return this.images.filter((image) => String(image.name || '').toLowerCase().includes(query))
            },
            choose(image) {
                this.selectedUrl = image.url
                this.selectedSourceId = image.id
                this.open = false
            },
            clear() {
                this.selectedUrl = null
                this.selectedSourceId = null
            },
            syncSourceId() {
                if (@js((bool) $sourceIdPath)) {
                    this.selectedSourceId = this.images.find((image) => image.url === this.selectedUrl)?.id || null
                }
            },
        }"
        x-init="syncSourceId()"
        x-effect="selectedUrl; syncSourceId()"
        x-on:keydown.escape.window="open = false"
        class="space-y-3"
    >
        <input type="hidden" {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}">

        @if ($isBlockImageField)
            <div
                dir="rtl"
                class="block-media-picker group relative w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-gray-900"
                data-block-media-picker
            >
                <button
                    type="button"
                    x-on:click="open = true"
                    x-bind:aria-label="selectedUrl ? 'تغییر تصویر' : 'تصویر را انتخاب کنید'"
                    class="block-media-picker__trigger relative flex w-full cursor-pointer flex-col overflow-hidden bg-gray-50 text-sm font-medium text-gray-700 outline-none transition focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 dark:bg-gray-900 dark:text-gray-200"
                    data-block-media-picker-trigger
                >
                    <template x-if="selectedUrl">
                        <span class="flex w-full flex-col">
                            <span class="block-media-picker__preview block overflow-hidden bg-gray-100 dark:bg-gray-800">
                                <img
                                    x-bind:src="selectedUrl"
                                    alt=""
                                    class="absolute inset-0 h-full w-full object-cover"
                                >
                                <span
                                    class="block-media-picker__overlay"
                                    aria-hidden="true"
                                ></span>
                            </span>
                            <span class="block-media-picker__action text-center text-sm font-medium">
                                تصویر را انتخاب کنید
                            </span>
                        </span>
                    </template>

                    <template x-if="! selectedUrl">
                        <span class="block-media-picker__empty relative z-10 flex flex-col items-center justify-center gap-3 px-6 py-10 text-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-gray-300 dark:ring-white/10">
                                <x-heroicon-o-photo class="h-6 w-6" />
                            </span>
                            <span>تصویر را انتخاب کنید</span>
                        </span>
                    </template>

                </button>

                <button
                    type="button"
                    x-cloak
                    x-show="selectedUrl"
                    x-on:click.stop.prevent="clear()"
                    class="block-media-picker__remove cursor-pointer outline-none focus-visible:ring-2 focus-visible:ring-danger-500 focus-visible:ring-offset-2 dark:ring-offset-gray-900"
                    aria-label="حذف انتخاب تصویر"
                    title="حذف انتخاب تصویر"
                    data-block-media-picker-remove
                >
                    <x-heroicon-o-trash class="h-4 w-4" />
                </button>
            </div>
        @else
        <div dir="rtl" class="flex flex-wrap items-start gap-4">
            <div
                style="width: 200px; max-width: 100%"
                class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
            >
                <template x-if="previewUrl()">
                    <img
                        x-bind:src="previewUrl()"
                        alt=""
                        style="width: 200px; max-width: 100%; height: 200px; object-fit: cover"
                        class="block"
                    >
                </template>

                <template x-if="! previewUrl()">
                    <div
                        style="width: 200px; max-width: 100%; height: 200px"
                        class="flex items-center justify-center bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                    >
                        <x-heroicon-o-photo class="h-10 w-10" />
                    </div>
                </template>

                @unless ($isBlockImageField)
                    <div class="border-t border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <span class="block truncate" x-text="selectedUrl || (placeholderUrl ? 'تصویر پیش‌فرض سایت' : 'تصویری انتخاب نشده')"></span>
                    </div>
                @endunless
            </div>

            <div class="min-w-0 flex-1 space-y-3">
                @unless ($isBlockImageField)
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="url"
                            x-model.debounce.400ms="selectedUrl"
                            placeholder="نشانی تصویر را وارد کنید یا از کتابخانه رسانه انتخاب کنید"
                        />
                    </x-filament::input.wrapper>
                @endunless

                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="button" size="sm" x-on:click="open = true">
                        انتخاب از کتابخانه رسانه
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        x-show="selectedUrl"
                        x-on:click="clear"
                    >
                        حذف تصویر
                    </x-filament::button>
                </div>
            </div>
        </div>
        @endif

        <template x-teleport="body">
            <div
                x-cloak
                x-show="open"
                x-transition.opacity
                style="display: none"
                class="cms-modal-layer flex items-center justify-center overflow-hidden p-4"
            >
                <div
                    class="cms-modal-backdrop bg-gray-950/60"
                    style="background-color: rgba(2, 6, 23, 0.78); backdrop-filter: blur(2px)"
                    aria-hidden="true"
                ></div>
            <div
                x-on:click.outside="open = false"
                style="height: calc(100dvh - 2rem); max-height: calc(100dvh - 2rem)"
                class="cms-modal-panel flex h-[calc(100dvh-2rem)] max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            >
                <div style="padding: 1rem 1.25rem" class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">انتخاب تصویر</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $isBlockImageField ? 'یک تصویر بارگذاری‌شده را انتخاب کنید.' : 'یک تصویر بارگذاری‌شده را انتخاب کنید یا نشانی دلخواه را وارد کنید.' }}
                        </p>
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
                                x-on:click="choose(image)"
                                style="min-width: 0; width: 100%; overflow: hidden"
                                class="overflow-hidden rounded-lg border bg-white text-left shadow-sm transition hover:border-primary-500 hover:ring-2 hover:ring-primary-500/30 dark:bg-gray-950"
                                x-bind:class="selectedUrl === image.url ? 'border-primary-600 ring-2 ring-primary-500/40' : 'border-gray-200 dark:border-gray-800'"
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
        </template>
    </div>
</x-dynamic-component>
