<div class="grid gap-0.5" @if ($depth > 0) style="margin-inline-start: 1rem; border-inline-start: 1px solid rgba(var(--gray-200), 0.5);" @endif>
    @foreach ($group['items'] as $service)
        <div wire:key="menu-service-node-{{ $service['id'] }}" data-service-id="{{ $service['id'] }}" data-service-depth="{{ $depth }}">
            <div class="flex items-start gap-1">
                @if (! $searching && $service['has_children'])
                    <button
                        type="button"
                        wire:click="toggleServiceBranch({{ $service['id'] }})"
                        class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5"
                        aria-label="{{ $service['children'] === null ? 'نمایش زیرخدمات' : 'بستن زیرخدمات' }} {{ $service['title'] }}"
                        aria-expanded="{{ $service['children'] === null ? 'false' : 'true' }}"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-down" @class(['h-4 w-4 transition', 'rotate-180' => $service['children'] !== null]) />
                    </button>
                @endif
                <label class="menu-builder-page-option flex min-w-0 flex-1 cursor-pointer items-start gap-2 border-b border-gray-100 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                    <x-filament::input.checkbox wire:model="selectedServiceIds" value="{{ $service['id'] }}" />
                    <span class="min-w-0 flex-1">
                        <span class="flex min-w-0 items-center gap-1.5">
                            <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-950 dark:text-white" title="{{ $service['title'] }}">{{ $service['title'] }}</span>
                            <span class="menu-builder-source-type">خدمت</span>
                        </span>
                        <span class="block truncate text-[10px] text-gray-500" dir="ltr">{{ $service['url'] }}</span>
                    </span>
                </label>
            </div>
            @if ($service['children'] !== null)
                @include('filament.resources.menu-resource.pages.partials.service-source-tree', [
                    'group' => $service['children'],
                    'depth' => $depth + 1,
                    'searching' => $searching,
                ])
            @endif
        </div>
    @endforeach

    @if ((! $searching || $depth === 0) && ($group['page'] > 1 || $group['has_more']))
        <div class="flex items-center justify-between gap-2 px-2 py-2 text-xs text-gray-500">
            <button
                type="button"
                wire:click="changeServicePage({{ $group['parent_id'] }}, {{ $group['page'] - 1 }})"
                @disabled($group['page'] <= 1)
                class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-40 dark:hover:bg-white/5"
            >قبلی</button>
            <span>صفحه {{ $group['page'] }}</span>
            <button
                type="button"
                wire:click="changeServicePage({{ $group['parent_id'] }}, {{ $group['page'] + 1 }})"
                @disabled(! $group['has_more'])
                class="rounded px-2 py-1 hover:bg-gray-100 disabled:opacity-40 dark:hover:bg-white/5"
            >بعدی</button>
        </div>
    @endif
</div>
