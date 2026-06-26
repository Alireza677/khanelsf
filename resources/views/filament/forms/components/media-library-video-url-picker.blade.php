@php
    $statePath = $getStatePath();
    $videos = collect($videos ?? [])->values();
@endphp

@once
    <script>
        window.__mediaLibraryVideoItems = @js($videos);
    </script>
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
            videos: window.__mediaLibraryVideoItems || [],
            filteredVideos() {
                const query = this.search.trim().toLowerCase()

                if (! query) {
                    return this.videos
                }

                return this.videos.filter((video) => String(video.name || '').toLowerCase().includes(query))
            },
            choose(url) {
                this.selectedUrl = url
                this.open = false
            },
            clear() {
                this.selectedUrl = null
            },
        }"
        x-on:keydown.escape.window="open = false"
        class="space-y-3"
    >
        <input type="hidden" {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}">

        <div dir="rtl" class="flex flex-wrap items-start gap-4">
            <div
                style="width: 240px; max-width: 100%"
                class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
            >
                <template x-if="selectedUrl">
                    <video
                        x-bind:src="selectedUrl"
                        muted
                        playsinline
                        preload="metadata"
                        style="width: 240px; max-width: 100%; height: 140px; object-fit: cover"
                        class="block"
                    ></video>
                </template>

                <template x-if="! selectedUrl">
                    <div
                        style="width: 240px; max-width: 100%; height: 140px"
                        class="flex items-center justify-center bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500"
                    >
                        <x-heroicon-o-video-camera class="h-10 w-10" />
                    </div>
                </template>

                <div class="border-t border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <span class="block truncate" x-text="selectedUrl || 'ویدیویی انتخاب نشده'"></span>
                </div>
            </div>

            <div class="min-w-0 flex-1 space-y-3">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="url"
                        x-model.debounce.400ms="selectedUrl"
                        placeholder="نشانی ویدیو را وارد کنید یا از کتابخانه رسانه انتخاب کنید"
                    />
                </x-filament::input.wrapper>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="button" size="sm" x-on:click="open = true">
                        انتخاب ویدیو از کتابخانه رسانه
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        x-show="selectedUrl"
                        x-on:click="clear"
                    >
                        حذف ویدیو
                    </x-filament::button>
                </div>
            </div>
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
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">انتخاب ویدیو</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            یک ویدیوی بارگذاری‌شده را انتخاب کنید یا نشانی دلخواه را وارد کنید.
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
                            placeholder="جست‌وجوی ویدیوها..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div style="padding: 1.25rem" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                    <template x-if="videos.length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            ویدیوی قابل استفاده‌ای در کتابخانه رسانه پیدا نشد.
                        </div>
                    </template>

                    <template x-if="videos.length > 0 && filteredVideos().length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            ویدیویی مطابق جست‌وجوی شما پیدا نشد.
                        </div>
                    </template>

                    <div class="grid gap-3" style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem">
                        <template x-for="video in filteredVideos()" x-bind:key="video.id">
                            <button
                                type="button"
                                x-on:click="choose(video.url)"
                                style="min-width: 0; width: 100%; overflow: hidden"
                                class="overflow-hidden rounded-lg border bg-white text-left shadow-sm transition hover:border-primary-500 hover:ring-2 hover:ring-primary-500/30 dark:bg-gray-950"
                                x-bind:class="selectedUrl === video.url ? 'border-primary-600 ring-2 ring-primary-500/40' : 'border-gray-200 dark:border-gray-800'"
                            >
                                <video
                                    x-bind:src="video.url"
                                    muted
                                    playsinline
                                    preload="metadata"
                                    style="display: block; width: 100%; height: 9rem; object-fit: cover"
                                    class="w-full object-cover"
                                ></video>
                                <span class="block truncate px-3 py-2 text-sm text-gray-700 dark:text-gray-200" x-text="video.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
