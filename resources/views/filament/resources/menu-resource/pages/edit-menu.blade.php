<x-filament-panels::page
    @class([
        'fi-resource-edit-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'fi-resource-record-' . $record->getKey(),
    ])
>
    <style>
        .menu-builder-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .menu-builder-sidebar {
            order: 2;
            min-width: 0;
        }

        .menu-builder-main {
            order: 1;
            min-width: 0;
        }

        .menu-builder-sidebar .fi-btn {
            width: 100%;
            justify-content: center;
        }

        .menu-builder-compact {
            font-size: 0.8125rem;
        }

        .menu-builder-compact .fi-section-header {
            padding: 0.65rem 0.85rem;
        }

        .menu-builder-compact .fi-section-content {
            padding: 0.75rem;
        }

        .menu-builder-compact .fi-section-header-icon {
            width: 1.1rem;
            height: 1.1rem;
        }

        .menu-builder-compact .fi-section-header-heading {
            font-size: 0.8125rem;
        }

        .menu-builder-compact .fi-section-header-description,
        .menu-builder-compact .fi-fo-field-wrp-helper-text {
            font-size: 0.6875rem;
            line-height: 1.2rem;
        }

        .menu-builder-compact .fi-fo-field-wrp-label,
        .menu-builder-compact .fi-input,
        .menu-builder-compact .fi-select-input {
            font-size: 0.75rem;
        }

        .menu-builder-compact .fi-input,
        .menu-builder-compact .fi-select-input {
            min-height: 2rem;
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }

        .menu-builder-compact .fi-btn {
            min-height: 2rem;
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
            font-size: 0.75rem;
        }

        .menu-builder-compact .fi-fo-component-ctn {
            gap: 0.75rem;
        }

        .menu-builder-compact .fi-fo-field-wrp {
            gap: 0.25rem;
        }

        .menu-builder-main,
        .menu-builder-sidebar {
            gap: 1rem;
        }

        .menu-builder-accordion {
            overflow: hidden;
            border: 1px solid rgba(var(--gray-200), 1);
            border-radius: 0.625rem;
            background: rgba(var(--gray-50), 0.5);
        }

        .dark .menu-builder-accordion {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.02);
        }

        .menu-builder-accordion-trigger {
            display: flex;
            width: 100%;
            min-height: 2.5rem;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.65rem;
            text-align: start;
        }

        .menu-builder-accordion-trigger:hover {
            background: rgba(var(--gray-100), 0.8);
        }

        .dark .menu-builder-accordion-trigger:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .menu-builder-accordion-panel {
            border-top: 1px solid rgba(var(--gray-200), 1);
            padding: 0.65rem;
        }

        .dark .menu-builder-accordion-panel {
            border-color: rgba(255, 255, 255, 0.1);
        }

        .menu-builder-page-list {
            height: 400px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .menu-builder-page-option {
            min-height: 2.35rem;
            padding: 0.35rem 0.5rem;
        }

        .menu-builder-source-type {
            flex: none;
            border-radius: 9999px;
            background: rgba(var(--gray-100), 1);
            padding: 0.125rem 0.375rem;
            font-size: 0.5625rem;
            line-height: 0.875rem;
            color: rgba(var(--gray-600), 1);
        }

        .dark .menu-builder-source-type {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(var(--gray-300), 1);
        }

        .menu-builder-source-status {
            flex: none;
            border-radius: 0.2rem;
            background: rgba(var(--gray-100), 1);
            padding: 0 0.25rem;
            font-size: 0.5rem;
            line-height: 0.75rem;
            color: rgba(var(--gray-500), 1);
        }

        .dark .menu-builder-source-status {
            background: rgba(255, 255, 255, 0.07);
            color: rgba(var(--gray-400), 1);
        }

        .menu-builder-mobile-add-button {
            display: inline-flex;
        }

        .menu-tree-children {
            margin-inline-start: 0.5rem;
            margin-top: 0.3rem;
            padding-inline-start: 0.5rem;
            gap: 0.35rem;
        }

        .menu-tree-drop-hint {
            opacity: 0.55;
            min-height: 1.25rem;
        }

        .menu-builder-tree-list {
            gap: 0.4rem;
        }

        .menu-tree-summary-row {
            min-height: 2.5rem;
            padding: 0.25rem 0.4rem;
        }

        .menu-tree-drag-handle {
            width: 2rem;
            height: 2rem;
        }

        .menu-tree-title {
            font-size: 0.75rem;
            line-height: 1rem;
        }

        .menu-tree-edit-panel {
            padding: 0.65rem;
        }

        .menu-tree-node:hover > .menu-tree-children > .menu-tree-drop-hint {
            border-color: rgba(var(--gray-200), 1);
            background: rgba(var(--gray-50), 1);
            opacity: 1;
        }

        .dark .menu-tree-node:hover > .menu-tree-children > .menu-tree-drop-hint {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.02);
        }

        @media (min-width: 640px) {
            .menu-tree-children {
                margin-inline-start: 1.5rem;
                padding-inline-start: 1rem;
            }
        }

        @media (max-width: 639px) {
            .menu-tree-item-actions .fi-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (min-width: 1024px) {
            .menu-builder-layout {
                grid-template-columns: minmax(17rem, 0.9fr) minmax(0, 2.6fr);
            }

            .menu-builder-sidebar {
                position: sticky;
                top: 1.5rem;
                order: 1;
            }

            .menu-builder-main {
                order: 2;
            }

            .menu-builder-mobile-add-button {
                display: none;
            }
        }
    </style>

    @php
        $availablePages = $this->getAvailablePages();
        $menuTree = $this->getMenuTree();
        $menuItemsCount = $record->items()->count();
        $sourceValidationSection = $errors->has('selectedPageIds')
            ? 'pages'
            : (
                $errors->has('selectedSourceKeys')
                    ? 'sources'
                    : (
                        $errors->has('customItemTitle') || $errors->has('customItemUrl')
                            ? 'custom'
                            : null
                    )
            );
        $pageSourceItems = $availablePages
            ->map(fn ($page) => [
                'id' => $page->getKey(),
                'title' => $page->title,
                'url' => $page->slug === 'home' ? '/' : '/' . $page->slug,
                'type' => 'page',
                'typeLabel' => 'برگه',
            ])
            ->values();
        $navigationSources = collect($this->getVisibleNavigationSources())->values();
    @endphp

    <div class="menu-builder-layout menu-builder-compact">
        <aside
            id="menu-item-source-panel"
            class="menu-builder-sidebar flex flex-col gap-6"
        >
            <x-filament::section
                heading="افزودن آیتم"
                description="یک منبع را باز کنید و آیتم‌های موردنظر را اضافه کنید."
                icon="heroicon-o-plus-circle"
                compact
            >
                <div
                    x-data="{
                        open: $persist(null).as(@js('menu-builder-source-panel-'. $record->getKey())),
                        sourceSearch: {
                            driver: 'local',
                            source: 'page',
                            query: '',
                            endpoint: null,
                        },
                        sourceItems: @js($pageSourceItems),
                        init() {
                            const validationSection = @js($sourceValidationSection)

                            if (validationSection) {
                                this.open = validationSection
                            }
                        },
                        normalize(value) {
                            return String(value || '').trim().toLocaleLowerCase('fa')
                        },
                        matchesSource(item) {
                            const query = this.normalize(this.sourceSearch.query)

                            return ! query
                                || this.normalize(item.title).includes(query)
                        },
                        filteredSourceCount() {
                            return this.sourceItems.filter((item) => this.matchesSource(item)).length
                        },
                    }"
                    class="grid gap-2"
                >
                    <div class="menu-builder-accordion">
                        <button
                            type="button"
                            class="menu-builder-accordion-trigger"
                            x-on:click="open = open === 'pages' ? null : 'pages'"
                            x-bind:aria-expanded="(open === 'pages').toString()"
                        >
                            <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4 text-gray-500" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-gray-950 dark:text-white">برگه‌ها</span>
                                <span class="block text-[10px] text-gray-500">{{ $availablePages->count() }} مورد</span>
                            </span>
                            <x-filament::icon
                                icon="heroicon-m-chevron-down"
                                class="h-4 w-4 text-gray-400 transition"
                                x-bind:class="{ 'rotate-180': open === 'pages' }"
                            />
                        </button>

                        <div
                            x-show="open === 'pages'"
                            x-cloak
                            x-transition
                            class="menu-builder-accordion-panel"
                        >
                            <form wire:submit="addSelectedPages" class="grid gap-2.5">
                                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                                    <x-filament::input
                                        type="search"
                                        x-model.debounce.150ms="sourceSearch.query"
                                        placeholder="جست‌وجوی برگه…"
                                        aria-label="جست‌وجوی برگه‌ها"
                                    />
                                </x-filament::input.wrapper>

                                <div
                                    class="menu-builder-page-list rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900"
                                    data-search-driver="local"
                                    data-search-source="page"
                                >
                                    @forelse ($availablePages as $page)
                                        <label
                                            wire:key="menu-page-option-{{ $page->getKey() }}"
                                            data-source-item
                                            data-source-type="page"
                                            x-show="matchesSource(@js([
                                                'title' => $page->title,
                                                'url' => $page->slug === 'home' ? '/' : '/' . $page->slug,
                                                'typeLabel' => 'برگه',
                                            ]))"
                                            class="menu-builder-page-option flex cursor-pointer items-start gap-2 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                                        >
                                            <x-filament::input.checkbox
                                                wire:model="selectedPageIds"
                                                value="{{ $page->getKey() }}"
                                            />

                                            <span class="min-w-0 flex-1">
                                                <span class="flex min-w-0 items-center gap-1.5">
                                                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-950 dark:text-white">
                                                        {{ $page->title }}
                                                    </span>
                                                    <span class="menu-builder-source-type">برگه</span>
                                                </span>
                                                <span class="block truncate text-[10px] text-gray-500" dir="ltr">
                                                    {{ $page->slug === 'home' ? '/' : '/' . $page->slug }}
                                                </span>
                                            </span>

                                            @if ($page->status !== 'published')
                                                <span class="menu-builder-source-status">
                                                    پیش‌نویس
                                                </span>
                                            @endif
                                        </label>
                                    @empty
                                        <div class="px-3 py-8 text-center text-xs text-gray-500">
                                            هنوز برگه‌ای وجود ندارد.
                                        </div>
                                    @endforelse

                                    @if ($availablePages->isNotEmpty())
                                        <div
                                            x-show="sourceSearch.query.trim() && filteredSourceCount() === 0"
                                            x-cloak
                                            class="px-3 py-8 text-center text-xs text-gray-500"
                                            role="status"
                                        >
                                            <x-filament::icon
                                                icon="heroicon-o-magnifying-glass"
                                                class="mx-auto mb-2 h-5 w-5 text-gray-400"
                                            />
                                            هیچ برگه‌ای با این عبارت پیدا نشد.
                                        </div>
                                    @endif
                                </div>

                                @error('selectedPageIds')
                                    <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                @enderror

                                <x-filament::button
                                    type="submit"
                                    icon="heroicon-m-plus"
                                    wire:target="addSelectedPages"
                                    wire:loading.attr="disabled"
                                    :disabled="$availablePages->isEmpty()"
                                >
                                    <span wire:loading.remove wire:target="addSelectedPages">افزودن به منو</span>
                                    <span wire:loading.flex wire:target="addSelectedPages" class="items-center gap-1.5">
                                        <x-filament::loading-indicator class="h-3.5 w-3.5" />
                                        در حال افزودن…
                                    </span>
                                </x-filament::button>
                            </form>
                        </div>
                    </div>

                    <div class="menu-builder-accordion">
                        <button
                            type="button"
                            class="menu-builder-accordion-trigger"
                            x-on:click="open = open === 'custom' ? null : 'custom'"
                            x-bind:aria-expanded="(open === 'custom').toString()"
                        >
                            <x-filament::icon icon="heroicon-o-link" class="h-4 w-4 text-gray-500" />
                            <span class="min-w-0 flex-1 text-xs font-semibold text-gray-950 dark:text-white">
                                پیوند دلخواه
                            </span>
                            <x-filament::icon
                                icon="heroicon-m-chevron-down"
                                class="h-4 w-4 text-gray-400 transition"
                                x-bind:class="{ 'rotate-180': open === 'custom' }"
                            />
                        </button>

                        <div
                            x-show="open === 'custom'"
                            x-cloak
                            x-transition
                            class="menu-builder-accordion-panel"
                        >
                            <form wire:submit="addCustomItem" class="grid gap-2.5">
                                <label class="grid gap-1">
                                    <span class="text-xs font-medium text-gray-800 dark:text-gray-200">عنوان</span>
                                    <x-filament::input.wrapper :valid="! $errors->has('customItemTitle')">
                                        <x-filament::input type="text" wire:model="customItemTitle" maxlength="255" />
                                    </x-filament::input.wrapper>
                                    @error('customItemTitle')
                                        <span class="text-xs text-danger-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="grid gap-1">
                                    <span class="text-xs font-medium text-gray-800 dark:text-gray-200">URL</span>
                                    <x-filament::input.wrapper :valid="! $errors->has('customItemUrl')">
                                        <x-filament::input
                                            type="text"
                                            wire:model="customItemUrl"
                                            placeholder="/about یا https://example.com"
                                            maxlength="255"
                                            dir="ltr"
                                        />
                                    </x-filament::input.wrapper>
                                    @error('customItemUrl')
                                        <span class="text-xs text-danger-600">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="grid gap-1">
                                    <span class="text-xs font-medium text-gray-800 dark:text-gray-200">نحوه باز شدن</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model="customItemTarget">
                                            <option value="_self">همین صفحه</option>
                                            <option value="_blank">صفحه جدید</option>
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>

                                <x-filament::button
                                    type="submit"
                                    color="gray"
                                    icon="heroicon-m-link"
                                    wire:target="addCustomItem"
                                    wire:loading.attr="disabled"
                                >
                                    <span wire:loading.remove wire:target="addCustomItem">افزودن به منو</span>
                                    <span wire:loading.flex wire:target="addCustomItem" class="items-center gap-1.5">
                                        <x-filament::loading-indicator class="h-3.5 w-3.5" />
                                        در حال افزودن…
                                    </span>
                                </x-filament::button>
                            </form>
                        </div>
                    </div>

                    <div class="menu-builder-accordion">
                        <button
                            type="button"
                            class="menu-builder-accordion-trigger"
                            x-on:click="open = open === 'sources' ? null : 'sources'"
                            x-bind:aria-expanded="(open === 'sources').toString()"
                        >
                            <x-filament::icon icon="heroicon-o-squares-plus" class="h-4 w-4 text-gray-500" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold text-gray-950 dark:text-white">مقصدهای سیستمی</span>
                                <span class="block text-[10px] text-gray-500">
                                    {{ $navigationSources->count() }} مورد فعال
                                </span>
                            </span>
                            <x-filament::icon
                                icon="heroicon-m-chevron-down"
                                class="h-4 w-4 text-gray-400 transition"
                                x-bind:class="{ 'rotate-180': open === 'sources' }"
                            />
                        </button>

                        <div
                            x-show="open === 'sources'"
                            x-cloak
                            x-transition
                            class="menu-builder-accordion-panel"
                        >
                            <form wire:submit="addSelectedSources" class="grid gap-2.5">
                                <div class="menu-builder-page-list rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                                    @forelse ($navigationSources as $source)
                                        <label
                                            wire:key="menu-navigation-source-{{ $source['source_key'] }}"
                                            data-navigation-source="{{ $source['source_key'] }}"
                                            class="menu-builder-page-option flex cursor-pointer items-start gap-2 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                                        >
                                            <x-filament::input.checkbox
                                                wire:model="selectedSourceKeys"
                                                value="{{ $source['source_key'] }}"
                                            />

                                            <span class="min-w-0 flex-1">
                                                <span class="flex min-w-0 items-center gap-1.5">
                                                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-950 dark:text-white">
                                                        {{ $source['label'] }}
                                                    </span>
                                                    <span class="menu-builder-source-type">سیستمی</span>
                                                </span>
                                                <span class="block truncate text-[10px] text-gray-500" dir="ltr">
                                                    {{ $source['url'] }}
                                                </span>
                                            </span>
                                        </label>
                                    @empty
                                        <div class="px-3 py-8 text-center text-xs text-gray-500">
                                            مقصد سیستمی فعالی وجود ندارد.
                                        </div>
                                    @endforelse
                                </div>

                                @error('selectedSourceKeys')
                                    <p class="text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                @enderror

                                <x-filament::button
                                    type="submit"
                                    icon="heroicon-m-plus"
                                    wire:target="addSelectedSources"
                                    wire:loading.attr="disabled"
                                    :disabled="$navigationSources->isEmpty()"
                                >
                                    <span wire:loading.remove wire:target="addSelectedSources">افزودن به منو</span>
                                    <span wire:loading.flex wire:target="addSelectedSources" class="items-center gap-1.5">
                                        <x-filament::loading-indicator class="h-3.5 w-3.5" />
                                        در حال افزودن…
                                    </span>
                                </x-filament::button>
                            </form>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </aside>

        <div class="menu-builder-main flex min-w-0 flex-col gap-6">
            <x-filament::section
                heading="تنظیمات منو"
                description="نام، محل نمایش و وضعیت کلی این منو را مدیریت کنید."
                icon="heroicon-o-cog-6-tooth"
                compact
            >
                <x-filament-panels::form
                    id="form"
                    :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                    wire:submit="save"
                >
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </x-filament-panels::form>
            </x-filament::section>

            <x-filament::section
                heading="ساختار منو"
                description="با دستگیره آیتم‌ها را جابه‌جا کنید. تغییرات ترتیب و زیرمنو به‌صورت خودکار ذخیره می‌شوند."
                icon="heroicon-o-list-bullet"
                compact
            >
                <x-slot name="headerEnd">
                    <span class="shrink-0 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                        {{ $menuItemsCount }} آیتم
                    </span>
                </x-slot>

                @if (count($menuTree))
                    <div class="mb-2.5 flex items-start gap-2 rounded-lg bg-primary-50/70 px-2.5 py-2 text-[11px] text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-m-information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p>
                            برای ویرایش روی هر آیتم بزنید؛ برای مرتب‌سازی فقط دستگیره
                            <x-filament::icon icon="heroicon-m-bars-3" class="inline h-4 w-4 align-text-bottom" />
                            را بکشید.
                        </p>
                    </div>
                @endif

                <div
                    x-data="{
                        saving: false,
                        serializeList(list) {
                            return Array.from(list.children)
                                .filter((element) => element.matches('[data-menu-tree-item]'))
                                .map((element) => {
                                    const childrenList = Array.from(element.children)
                                        .find((child) => child.matches('[data-menu-tree-children]'))

                                    return {
                                        id: Number(element.getAttribute('x-sortable-item')),
                                        children: childrenList ? this.serializeList(childrenList) : [],
                                    }
                                })
                        },
                        async saveTree() {
                            if (this.saving) return

                            this.saving = true

                            try {
                                await $wire.saveMenuTree(this.serializeList(this.$refs.rootList))
                            } finally {
                                this.saving = false
                            }
                        },
                    }"
                    x-on:end.stop="saveTree()"
                    class="relative"
                >
                    <div
                        x-show="saving"
                        x-cloak
                        aria-live="polite"
                        class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-white/75 backdrop-blur-[2px] dark:bg-gray-900/75"
                    >
                        <div class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-medium text-gray-700 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-800 dark:text-gray-200 dark:ring-white/10">
                            <x-filament::loading-indicator class="h-5 w-5" />
                            در حال ذخیره ساختار…
                        </div>
                    </div>

                    @if (count($menuTree))
                        <ul
                            x-ref="rootList"
                            x-sortable
                            x-sortable-group="menu-tree-{{ $record->getKey() }}"
                            data-sortable-animation-duration="150"
                            class="menu-builder-tree-list flex min-h-10 flex-col"
                        >
                            @include('filament.resources.menu-resource.pages.partials.menu-tree-items', [
                                'items' => $menuTree,
                                'menuId' => $record->getKey(),
                                'depth' => 0,
                            ])
                        </ul>
                    @else
                        <div class="rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/60 px-5 py-12 text-center dark:border-white/15 dark:bg-white/[0.02]">
                            <x-filament::icon
                                icon="heroicon-o-list-bullet"
                                class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500"
                            />
                            <p class="mt-4 text-base font-semibold text-gray-800 dark:text-gray-200">
                                منوی شما هنوز خالی است
                            </p>
                            <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                                برای شروع، یک برگه یا پیوند دلخواه از بخش افزودن آیتم انتخاب کنید.
                            </p>
                            <x-filament::button
                                type="button"
                                size="sm"
                                color="gray"
                                icon="heroicon-m-plus"
                                class="menu-builder-mobile-add-button mt-5"
                                x-on:click="document.getElementById('menu-item-source-panel')?.scrollIntoView({ behavior: 'smooth' })"
                            >
                                رفتن به افزودن آیتم
                            </x-filament::button>
                        </div>
                    @endif

                    @error('menuTree')
                        <p class="mt-3 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
            </x-filament::section>

        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
