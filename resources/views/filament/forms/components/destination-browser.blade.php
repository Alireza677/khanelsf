<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('destination-target-picker') }}&amp;hash={{ substr(md5_file(resource_path('js/filament/destination-picker.js')), 0, 12) }}"
    x-data="destinationTargetPicker({{ $configuration }})"
    dir="rtl"
    {{ $browserAttributes ?? '' }}
>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::button type="button" x-ref="trigger" x-on:click="open()" x-bind:disabled="disabled" aria-haspopup="dialog">
            <span x-text="({ page: 'انتخاب برگه', project: 'انتخاب پروژه', product: 'انتخاب محصول', service: 'انتخاب خدمت', form: 'انتخاب فرم', video: 'انتخاب ویدئو' })[target]"></span>
        </x-filament::button>
        <span x-show="mode === 'reference'" x-text="label" class="text-sm"></span>
        <x-filament::button type="button" color="gray" x-show="state && mode === 'reference'" x-on:click="clear()" x-bind:disabled="disabled">پاک‌کردن انتخاب</x-filament::button>
    </div>
    <dialog x-ref="dialog" data-destination-browser aria-label="انتخاب مقصد" dir="rtl"
        x-on:cancel.prevent="close()" x-on:keydown.stop x-on:keydown.escape.prevent="close()"
        class="destination-picker bg-white dark:bg-gray-900 dark:text-white"
    >
        <header class="destination-picker__header">
            <h2>انتخاب مقصد</h2>
            <x-filament::icon-button type="button" color="gray" size="sm" icon="heroicon-m-x-mark" label="بستن" x-on:click="close()" />
        </header>
        <div class="destination-picker__controls">
            <div class="destination-picker__filters">
                <label x-show="mode === 'url'">
                    <span>نوع مقصد</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select x-model="target" x-on:change="query = ''; category = ''; load(1)">
                            <option value="page">برگه</option><option value="project">پروژه</option>
                            <option value="product">محصول</option><option value="service">خدمت</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label x-show="target === 'project' && categories.length">
                    <span>دسته پروژه</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select x-model="category" x-on:change="load(1)">
                            <option value="">همه دسته‌ها</option>
                            <template x-for="categoryOption in categories" :key="categoryOption.id"><option :value="categoryOption.id" x-text="categoryOption.name"></option></template>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" x-ref="search" x-model="query" maxlength="100" autocomplete="off"
                    x-on:input="invalidate()" x-on:input.debounce.300ms="load(1)"
                    aria-label="جست‌وجوی مقصد" placeholder="جست‌وجو در مقصدها…" />
            </x-filament::input.wrapper>
            <p class="destination-picker__current" x-show="state && mode === 'reference'">انتخاب فعلی: <span x-text="label"></span></p>
            <div class="destination-picker__summary" aria-live="polite">
                <span x-show="loading">در حال دریافت فهرست…</span>
                <span x-show="!loading && !error" x-text="`${total} نتیجه`"></span>
            </div>
        </div>
        <div class="destination-picker__list" x-bind:aria-busy="loading">
            <div class="destination-picker__empty" x-show="loading">
                <x-filament::loading-indicator class="h-5 w-5" />
            </div>
            <div class="destination-picker__empty" x-show="error" role="alert">
                <span x-text="error"></span>
                <x-filament::button type="button" color="gray" size="sm" x-on:click="load(page)">تلاش مجدد</x-filament::button>
            </div>
            <div class="destination-picker__empty" x-show="!loading && !error && items.length === 0">
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-6 w-6" />
                <span>موردی یافت نشد.</span>
                <small>عبارت دیگری را جست‌وجو کنید.</small>
            </div>
            <div class="destination-picker__rows">
                <template x-for="item in items" :key="item.reference_id">
                    <button type="button" x-on:click="choose(item)" x-bind:disabled="!item.selectable || disabled || loading"
                        x-bind:aria-pressed="mode === 'reference' && String(state) === String(item.reference_id)"
                        class="destination-picker__row">
                        <span class="destination-picker__title">
                            <span class="destination-picker__selection" aria-hidden="true"><x-filament::icon icon="heroicon-m-check" class="h-3 w-3" /></span>
                            <span x-text="item.title" x-bind:title="item.title"></span>
                        </span>
                        <span class="destination-picker__badge" x-bind:data-selectable="item.selectable" x-text="item.status.split(' — ')[0]"></span>
                    </button>
                </template>
            </div>
        </div>
        <footer class="destination-picker__pagination">
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-m-chevron-right" x-on:click="load(page - 1)" x-bind:disabled="loading || page <= 1">قبلی</x-filament::button>
            <span x-text="`صفحه ${page} از ${lastPage}`"></span>
            <x-filament::button type="button" color="gray" size="sm" icon="heroicon-m-chevron-left" icon-position="after" x-on:click="load(page + 1)" x-bind:disabled="loading || page >= lastPage">بعدی</x-filament::button>
        </footer>
    </dialog>
</div>
