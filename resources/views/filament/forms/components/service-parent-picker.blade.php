@php
    $statePath = $getStatePath();
    $items = collect($services ?? [])->values();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-slot name="label">{{ $getLabel() }}</x-slot>

    <div
        dir="rtl"
        x-data="{
            open: false,
            search: '',
            selected: $wire.entangle(@js($statePath)),
            items: @js($items),
            expanded: [],
            init() {
                const selected = this.selectedItem
                this.expanded = selected ? selected.ancestor_ids.map(String) : []
            },
            get selectedItem() {
                return this.items.find(item => String(item.id) === String(this.selected)) || null
            },
            get query() {
                return this.search.trim().toLocaleLowerCase('fa')
            },
            isExpanded(id) {
                return this.expanded.includes(String(id))
            },
            toggle(id) {
                const key = String(id)

                if (this.isExpanded(key)) {
                    this.expanded = this.expanded.filter(item => item !== key)
                    return
                }

                const branch = this.items.find(item => String(item.id) === key)

                if (! branch) return

                const siblingBranches = this.items
                    .filter(item => item.has_children
                        && item.depth === branch.depth
                        && String(item.parent_id ?? '') === String(branch.parent_id ?? ''))
                    .map(item => String(item.id))

                this.expanded = [
                    ...this.expanded.filter(item => ! siblingBranches.includes(item)),
                    key,
                ]
            },
            visible(item) {
                if (this.query) {
                    return `${item.name} ${item.path}`.toLocaleLowerCase('fa').includes(this.query)
                }

                return item.ancestor_ids.every(id => this.isExpanded(id))
            },
            ancestorPath(item) {
                return item.path.split(' ← ').slice(0, -1).join(' ← ')
            },
            choose(item) {
                if (item.disabled) return
                this.selected = item.id
                this.open = false
                this.search = ''
            },
            clear(close = false) {
                this.selected = null
                this.search = ''
                if (close) this.open = false
            },
        }"
        x-on:keydown.escape.window="open = false"
        class="space-y-3"
    >
        <input type="hidden" {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}">

        <div class="rounded-xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <template x-if="selectedItem">
                <div>
                    <div class="text-sm font-medium text-gray-950 dark:text-white" x-text="selectedItem.name"></div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="selectedItem.path"></div>
                </div>
            </template>
            <template x-if="! selectedItem">
                <div>
                    <div class="text-sm font-medium text-gray-950 dark:text-white">بدون والد / خدمت اصلی</div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">این خدمت در سطح اصلی نمایش داده می‌شود.</div>
                </div>
            </template>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button type="button" size="sm" x-on:click="open = true">
                    <span x-text="selectedItem ? 'تغییر خدمت والد' : 'انتخاب خدمت والد'"></span>
                </x-filament::button>
                <x-filament::button type="button" size="sm" color="gray" x-show="selectedItem" x-on:click="clear()">
                    حذف انتخاب
                </x-filament::button>
            </div>
        </div>

<template x-teleport="body">
    <div
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        dir="rtl"
        role="dialog"
        aria-modal="true"
        aria-label="انتخاب خدمت والد"
        x-on:click.self="open = false"
        class="fixed inset-0 flex items-center justify-center"
        style="
            z-index: 9999;
            padding: 16px;
            background-color: rgba(17, 24, 39, 0.42);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        "
    >
        <div
            x-on:click.stop
            class="flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
            style="
                width: min(760px, calc(100vw - 24px));
                height: min(680px, calc(100dvh - 32px));
                min-height: 420px;
                max-width: none;
            "
        >
            {{-- Header --}}
            <header
                class="flex shrink-0 items-start justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700"
            >
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        انتخاب خدمت والد
                    </h2>

                    <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">
                        برای مشاهده زیرمجموعه‌ها روی نام یا فلش هر خدمت کلیک کنید.
                    </p>
                </div>

                <button
                    type="button"
                    x-on:click="open = false"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    aria-label="بستن"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </header>

            {{-- Search + Root option --}}
            <section class="shrink-0 border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="px-4 pb-3 pt-3">
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass
                            class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                        />

                        <input
                            x-model.debounce.200ms="search"
                            type="search"
                            placeholder="جستجو در نام خدمت یا مسیر والدها…"
                            class="block w-full rounded-lg border-gray-300 py-2.5 pl-10 pr-3 text-right text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                    </div>
                </div>

                <div
                    class="flex min-h-12 items-center gap-2 border-t border-gray-100 px-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60"
                    :class="! selectedItem && 'bg-primary-50 dark:bg-primary-950/40'"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-gray-800">
                        <x-heroicon-o-home class="h-4 w-4" />
                    </span>

                    <button
                        type="button"
                        x-on:click="clear(true)"
                        class="min-w-0 flex-1 text-right"
                    >
                        <span class="block truncate text-sm font-medium text-gray-900 dark:text-white">
                            بدون والد / خدمت اصلی
                        </span>

                        <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">
                            ثبت خدمت در سطح اصلی
                        </span>
                    </button>

                    <x-heroicon-o-check
                        class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400"
                        x-show="! selectedItem"
                    />

                    <button
                        type="button"
                        x-on:click="clear(true)"
                        class="shrink-0 rounded-md px-3 py-1.5 text-xs font-medium text-primary-600 ring-1 ring-inset ring-primary-300 transition hover:bg-primary-50 dark:text-primary-400 dark:ring-primary-700 dark:hover:bg-primary-950/40"
                    >
                        انتخاب
                    </button>
                </div>
            </section>

            {{-- Scrollable Tree --}}
            <div
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white px-2 py-1 dark:bg-gray-900"
            >
                <template x-for="item in items" :key="item.id">
                    <div
                        x-show="visible(item)"
                        class="group flex min-h-11 w-full items-center gap-1 overflow-hidden border-b border-gray-100 transition dark:border-gray-800"
                        :class="{
                            'bg-primary-50 dark:bg-primary-950/40':
                                String(selected) === String(item.id),

                            'bg-gray-50/80 dark:bg-gray-800/40':
                                item.has_children &&
                                isExpanded(item.id) &&
                                String(selected) !== String(item.id),

                            'hover:bg-gray-50 dark:hover:bg-gray-800/60':
                                String(selected) !== String(item.id) &&
                                !(item.has_children && isExpanded(item.id)),

                            'opacity-45':
                                item.disabled,
                        }"
                        :style="`padding-right: ${12 + (item.depth * 24)}px; padding-left: 12px;`"
                    >
                        {{-- Expand --}}
                        <button
                            type="button"
                            x-show="item.has_children && ! query"
                            x-on:click="toggle(item.id)"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                            :aria-expanded="isExpanded(item.id)"
                            :aria-label="isExpanded(item.id) ? 'بستن شاخه' : 'باز کردن شاخه'"
                        >
                            <x-heroicon-o-chevron-left
                                class="h-4 w-4 transition-transform duration-150"
                                x-bind:class="isExpanded(item.id) && '-rotate-90'"
                            />
                        </button>

                        <span
                            x-show="! item.has_children || query"
                            class="w-8 shrink-0"
                        ></span>

                        {{-- Name --}}
                        <button
                            type="button"
                            x-on:click="item.has_children && ! query ? toggle(item.id) : choose(item)"
                            class="min-w-0 flex-1 overflow-hidden py-1 text-right"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <span
                                    class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                    x-text="item.name"
                                ></span>

                                <span
                                    x-show="item.has_children"
                                    class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] leading-4 text-gray-400 dark:bg-gray-700/80 dark:text-gray-400"
                                    x-text="`${item.children_count} زیرخدمت`"
                                ></span>

                                <span
                                    x-show="item.disabled"
                                    class="shrink-0 rounded-md bg-danger-50 px-1.5 py-0.5 text-[10px] text-danger-600 dark:bg-danger-950/40 dark:text-danger-400"
                                >
                                    غیرقابل انتخاب
                                </span>
                            </span>

                            <span
                                x-show="query && ancestorPath(item)"
                                class="mt-0.5 block truncate text-[11px] leading-4 text-gray-500 dark:text-gray-400"
                                x-text="ancestorPath(item)"
                            ></span>
                        </button>

                        {{-- Selected --}}
                        <x-heroicon-o-check
                            class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400"
                            x-show="String(selected) === String(item.id)"
                        />

                        {{-- Select --}}
                        <button
                            type="button"
                            x-on:click.stop="choose(item)"
                            x-bind:disabled="item.disabled"
                            class="shrink-0 rounded-md px-3 py-1.5 text-xs font-medium text-primary-600 ring-1 ring-inset ring-primary-300 transition hover:bg-primary-50 disabled:cursor-not-allowed disabled:text-gray-400 disabled:ring-gray-200 dark:text-primary-400 dark:ring-primary-700 dark:hover:bg-primary-950/40 dark:disabled:text-gray-600 dark:disabled:ring-gray-700"
                        >
                            انتخاب
                        </button>
                    </div>
                </template>

                {{-- Empty search --}}
                <div
                    x-show="query && ! items.some(item => visible(item))"
                    class="flex h-40 items-center justify-center px-6 text-center"
                >
                    <div>
                        <x-heroicon-o-magnifying-glass
                            class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600"
                        />

                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            خدمتی با این عبارت پیدا نشد.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
    </div>
</x-dynamic-component>
