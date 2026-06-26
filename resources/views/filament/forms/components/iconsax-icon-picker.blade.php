@php
    $statePath = $getStatePath();
@endphp

@once
    <link rel="stylesheet" href="{{ $stylesheet ?? asset('assets/iconsax/outline/style.css') }}">
    <script>
        window.__iconsaxIconPickerSelectionUrl = @js($selectionJsonUrl ?? asset('assets/iconsax/outline/selection.json'));
        window.__iconsaxIconPickerIcons = window.__iconsaxIconPickerIcons || null;
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
            loading: false,
            loadError: false,
            selected: $wire.entangle(@js($statePath)),
            icons: Array.isArray(window.__iconsaxIconPickerIcons) ? window.__iconsaxIconPickerIcons : [],
            normalize(value) {
                const icon = String(value || '').trim()

                if (! icon) {
                    return ''
                }

                return icon.startsWith('icon-') ? icon : `icon-${icon}`
            },
            get selectedClass() {
                return this.normalize(this.selected)
            },
            get selectedIcon() {
                const selected = this.selectedClass

                return this.icons.find((icon) => icon.class === selected) || null
            },
            async loadIcons() {
                if (this.icons.length || this.loading) {
                    return
                }

                if (Array.isArray(window.__iconsaxIconPickerIcons)) {
                    this.icons = window.__iconsaxIconPickerIcons
                    return
                }

                this.loading = true
                this.loadError = false

                try {
                    const startedAt = performance.now()
                    const response = await fetch(window.__iconsaxIconPickerSelectionUrl, {
                        headers: { Accept: 'application/json' },
                    })

                    if (! response.ok) {
                        throw new Error(`Iconsax selection failed with status ${response.status}`)
                    }

                    const selection = await response.json()
                    const prefix = selection?.preferences?.fontPref?.prefix || 'icon-'

                    this.icons = (selection?.icons || [])
                        .map((icon) => {
                            const name = icon?.properties?.name

                            if (! name) {
                                return null
                            }

                            const tags = (icon?.icon?.tags || []).filter(Boolean)

                            return {
                                name,
                                class: `${prefix}${name}`,
                                search: `${name} ${tags.join(' ')}`.trim(),
                            }
                        })
                        .filter(Boolean)

                    window.__iconsaxIconPickerIcons = this.icons
                    console.info('PERF PageResource edit: icon picker load ms', {
                        ms: Math.round((performance.now() - startedAt) * 100) / 100,
                        icons: this.icons.length,
                    })
                } catch (error) {
                    this.loadError = true
                    console.error(error)
                } finally {
                    this.loading = false
                }
            },
            filteredIcons() {
                const query = this.search.trim().toLowerCase()

                if (! query) {
                    return this.icons
                }

                return this.icons.filter((icon) => String(icon.search || icon.name || '').toLowerCase().includes(query))
            },
            choose(icon) {
                this.selected = icon.class
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

        <div dir="rtl" class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                x-on:click="open = true; loadIcons()"
                class="flex min-h-12 min-w-48 items-center gap-3 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm transition hover:border-primary-500 hover:ring-2 hover:ring-primary-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-xl text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <template x-if="selectedIcon">
                        <i x-bind:class="selectedIcon.class" aria-hidden="true"></i>
                    </template>
                    <template x-if="! selectedIcon && selectedClass">
                        <i x-bind:class="selectedClass" aria-hidden="true"></i>
                    </template>
                    <template x-if="! selectedIcon && ! selectedClass">
                        <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                    </template>
                </span>
                <span class="min-w-0 text-right">
                    <span class="block font-medium text-gray-950 dark:text-white" x-text="selectedIcon ? selectedIcon.name : 'انتخاب آیکن'"></span>
                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400" x-text="selectedClass || 'از لیست Iconsax انتخاب کنید'"></span>
                </span>
            </button>

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
                class="flex h-[calc(100dvh-2rem)] max-h-[calc(100dvh-2rem)] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            >
                <div style="padding: 1rem 1.25rem" class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-950 dark:text-white">انتخاب آیکن Iconsax</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">آیکن موردنظر را جست‌وجو و انتخاب کنید.</p>
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
                            placeholder="جست‌وجوی نام آیکن..."
                        />
                    </x-filament::input.wrapper>
                </div>

                <div style="padding: 1.25rem" class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-5">
                    <template x-if="loading">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            در حال بارگذاری آیکن‌ها...
                        </div>
                    </template>

                    <template x-if="loadError">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            بارگذاری فهرست آیکن‌ها ناموفق بود.
                        </div>
                    </template>

                    <template x-if="! loading && ! loadError && icons.length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            فایل آیکن‌های Iconsax پیدا نشد.
                        </div>
                    </template>

                    <template x-if="! loading && ! loadError && icons.length > 0 && filteredIcons().length === 0">
                        <div class="rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            آیکنی مطابق جست‌وجوی شما پیدا نشد.
                        </div>
                    </template>

                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr)); gap: 0.75rem">
                        <template x-for="icon in filteredIcons()" x-bind:key="icon.class">
                            <button
                                type="button"
                                x-on:click="choose(icon)"
                                class="flex min-h-28 flex-col items-center justify-center gap-3 rounded-lg border bg-white px-2 py-3 text-center shadow-sm transition hover:border-primary-500 hover:ring-2 hover:ring-primary-500/30 dark:bg-gray-950"
                                x-bind:class="selectedClass === icon.class ? 'border-primary-600 ring-2 ring-primary-500/40' : 'border-gray-200 dark:border-gray-800'"
                            >
                                <i x-bind:class="icon.class" class="text-3xl text-gray-800 dark:text-gray-100" aria-hidden="true"></i>
                                <span class="block w-full truncate text-xs font-medium text-gray-700 dark:text-gray-200" x-text="icon.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
