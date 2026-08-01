@foreach ($items as $item)
    @php
        $depth = $depth ?? 0;
        $typeLabels = [
            \App\Models\MenuItem::TYPE_PAGE => 'برگه',
            \App\Models\MenuItem::TYPE_CUSTOM_URL => 'پیوند دلخواه',
            \App\Models\MenuItem::TYPE_SOURCE => 'مقصد سیستمی',
            \App\Models\MenuItem::TYPE_POST => 'نوشته',
            \App\Models\MenuItem::TYPE_PRODUCT => 'محصول',
            \App\Models\MenuItem::TYPE_PROJECT => 'پروژه',
            \App\Models\MenuItem::TYPE_SERVICE => 'خدمت',
        ];
        $typeLabel = $typeLabels[$item['type']] ?? 'پیوند';
        $ownsUrl = $item['type'] === \App\Models\MenuItem::TYPE_CUSTOM_URL
            && blank($item['source_key'] ?? null);
    @endphp

    <li
        wire:key="menu-tree-item-{{ $item['id'] }}"
        x-sortable-item="{{ $item['id'] }}"
        data-menu-tree-item
        class="menu-tree-node group/menu-item"
        x-data="{
            open: @js(
                $errors->has('menuItems.' . $item['id'] . '.title')
                || $errors->has('menuItems.' . $item['id'] . '.url')
                || $errors->has('menuItems.' . $item['id'] . '.target')
                || $errors->has('menuItems.' . $item['id'] . '.status')
            ),
            saving: false,
            deleting: false,
            title: @js($item['title']),
            url: @js($item['url'] ?? ''),
            target: @js($item['target']),
            status: @js($item['status']),
            ownsUrl: @js($ownsUrl),
            async saveItem() {
                if (this.saving) return

                this.saving = true

                try {
                    const payload = {
                        title: this.title,
                        target: this.target,
                        status: this.status,
                    }

                    if (this.ownsUrl) {
                        payload.url = this.url === '' ? null : this.url
                    }

                    await $wire.updateMenuItem(@js($item['id']), payload)
                } finally {
                    this.saving = false
                }
            },
            async removeItem() {
                if (this.deleting || ! confirm('این آیتم حذف شود؟ زیرآیتم‌های آن حذف نمی‌شوند.')) return

                this.deleting = true

                try {
                    await $wire.deleteMenuItem(@js($item['id']))
                } finally {
                    this.deleting = false
                }
            },
        }"
    >
        <div class="menu-tree-card overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/10 transition hover:ring-gray-950/20 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-white/20">
            <div class="menu-tree-summary-row flex items-center gap-1.5 sm:gap-2">
                <button
                    type="button"
                    x-sortable-handle
                    class="menu-tree-drag-handle flex shrink-0 cursor-grab touch-none items-center justify-center rounded-md bg-gray-100 text-gray-500 transition hover:bg-primary-50 hover:text-primary-600 active:cursor-grabbing dark:bg-white/10 dark:text-gray-400 dark:hover:bg-primary-400/10 dark:hover:text-primary-300"
                    aria-label="جابجایی {{ $item['title'] }}"
                    title="برای جابجایی بکشید"
                >
                    <x-filament::icon icon="heroicon-m-bars-3" class="h-5 w-5" />
                </button>

                <button
                    type="button"
                    x-on:click.stop="open = ! open"
                    class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-1 py-1 text-right focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 sm:gap-3"
                    x-bind:aria-expanded="open.toString()"
                    aria-controls="menu-item-panel-{{ $item['id'] }}"
                >
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                            <span
                                class="menu-tree-title truncate font-medium text-gray-950 dark:text-white"
                                x-text="title || 'بدون عنوان'"
                            ></span>

                            <span
                                x-show="status !== 'active'"
                                class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300"
                            >
                                غیرفعال
                            </span>

                            <span class="rounded-md bg-primary-50 px-1.5 py-0.5 text-[10px] font-medium text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                                {{ $typeLabel }}
                            </span>

                            @if (filled($item['source_key'] ?? null))
                                <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300" dir="ltr">
                                    {{ $item['source_key'] }}
                                </span>
                            @endif

                            @if ($depth > 0)
                                <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                    زیرمنو
                                </span>
                            @endif
                        </span>

                        <span
                            class="mt-0.5 block truncate text-left text-xs text-gray-500 dark:text-gray-400"
                            dir="ltr"
                            x-text="url || 'بدون URL'"
                        ></span>
                    </span>

                    <x-filament::icon
                        icon="heroicon-m-chevron-down"
                        class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                        x-bind:class="{ 'rotate-180': open }"
                    />
                </button>
            </div>

            <form
                id="menu-item-panel-{{ $item['id'] }}"
                x-show="open"
                x-cloak
                x-transition
                x-on:submit.prevent="saveItem()"
                class="menu-tree-edit-panel border-t border-gray-200 bg-gray-50/60 dark:border-white/10 dark:bg-white/[0.02]"
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">عنوان</span>
                        <x-filament::input.wrapper :valid="! $errors->has('menuItems.' . $item['id'] . '.title')">
                            <x-filament::input
                                type="text"
                                x-model="title"
                                maxlength="255"
                                required
                            />
                        </x-filament::input.wrapper>
                        @error('menuItems.' . $item['id'] . '.title')
                            <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">URL</span>
                        @if ($ownsUrl)
                            <x-filament::input.wrapper :valid="! $errors->has('menuItems.' . $item['id'] . '.url')">
                                <x-filament::input
                                    type="text"
                                    x-model="url"
                                    maxlength="255"
                                    dir="ltr"
                                    placeholder="/about"
                                />
                            </x-filament::input.wrapper>
                        @else
                            <div
                                class="min-h-9 rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-left text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300"
                                dir="ltr"
                                x-text="url || 'مقصد در دسترس نیست'"
                            ></div>
                            <span class="text-xs text-gray-500">URL از مقصد انتخاب‌شده resolve می‌شود.</span>
                        @endif
                        @error('menuItems.' . $item['id'] . '.url')
                            <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">Target</span>
                        <x-filament::input.wrapper :valid="! $errors->has('menuItems.' . $item['id'] . '.target')">
                            <x-filament::input.select x-model="target">
                                <option value="_self">همین صفحه</option>
                                <option value="_blank">صفحه جدید</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        @error('menuItems.' . $item['id'] . '.target')
                            <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">وضعیت</span>
                        <x-filament::input.wrapper :valid="! $errors->has('menuItems.' . $item['id'] . '.status')">
                            <x-filament::input.select x-model="status">
                                <option value="active">فعال</option>
                                <option value="inactive">غیرفعال</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        @error('menuItems.' . $item['id'] . '.status')
                            <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <div class="menu-tree-item-actions mt-4 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <x-filament::button
                        type="button"
                        color="danger"
                        size="sm"
                        outlined
                        icon="heroicon-m-trash"
                        x-on:click="removeItem()"
                        x-bind:disabled="deleting || saving"
                        class="w-full justify-center sm:w-auto"
                    >
                        <span x-show="! deleting">حذف آیتم</span>
                        <span x-show="deleting">در حال حذف…</span>
                    </x-filament::button>

                    <x-filament::button
                        type="submit"
                        size="sm"
                        icon="heroicon-m-check"
                        x-bind:disabled="saving || deleting"
                        class="w-full justify-center sm:w-auto"
                    >
                        <span x-show="! saving">ذخیره تغییرات</span>
                        <span x-show="saving">در حال ذخیره…</span>
                    </x-filament::button>
                </div>
            </form>
        </div>

        <ul
            x-sortable
            x-sortable-group="menu-tree-{{ $menuId }}"
            data-sortable-animation-duration="150"
            data-menu-tree-children
            class="menu-tree-children flex min-h-5 flex-col rounded-lg border-r-2 border-dashed border-gray-200 transition-colors dark:border-white/10"
        >
            @include('filament.resources.menu-resource.pages.partials.menu-tree-items', [
                'items' => $item['children'],
                'menuId' => $menuId,
                'depth' => $depth + 1,
            ])

            @if (empty($item['children']))
                <li
                    aria-hidden="true"
                    class="menu-tree-drop-hint pointer-events-none flex min-h-7 items-center justify-center rounded-md border border-dashed border-transparent px-2 text-[10px] text-gray-400 transition dark:text-gray-500"
                >
                    اینجا رها کنید تا زیرمنو شود
                </li>
            @endif
        </ul>
    </li>
@endforeach
