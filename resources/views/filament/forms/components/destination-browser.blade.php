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
        style="width:min(44rem,calc(100vw - 2rem));max-height:85dvh;padding:1.25rem;border-radius:.75rem;overflow:auto;overscroll-behavior:contain;color:inherit"
        class="bg-white dark:bg-gray-900 dark:text-white"
    >
        <div class="flex items-center justify-between gap-3" style="margin-bottom:1rem">
            <h2 class="text-lg font-bold">انتخاب مقصد</h2>
            <x-filament::button type="button" color="gray" x-on:click="close()">بستن</x-filament::button>
        </div>
        <label x-show="mode === 'url'" class="block text-sm">
            نوع مقصد
            <select x-model="target" x-on:change="query = ''; category = ''; load(1)" class="w-full rounded-lg border-gray-300 dark:bg-gray-800">
                <option value="page">برگه</option><option value="project">پروژه</option>
                <option value="product">محصول</option><option value="service">خدمت</option>
            </select>
        </label>
        <label x-show="target === 'project' && categories.length" class="block text-sm">
            دسته پروژه
            <select x-model="category" x-on:change="load(1)" class="w-full rounded-lg border-gray-300 dark:bg-gray-800">
                <option value="">همه دسته‌ها</option>
                <template x-for="categoryOption in categories" :key="categoryOption.id"><option :value="categoryOption.id" x-text="categoryOption.name"></option></template>
            </select>
        </label>
        <label class="block text-sm" style="margin-top:.75rem">
            جست‌وجوی عنوان، مسیر یا شناسه
            <input type="search" x-ref="search" x-model="query" maxlength="100" autocomplete="off"
                x-on:input="invalidate()" x-on:input.debounce.300ms="load(1)"
                class="w-full rounded-lg border-gray-300 dark:bg-gray-800" placeholder="همه موارد از ابتدا نمایش داده می‌شوند">
        </label>
        <p class="text-sm" x-show="state && mode === 'reference'" style="margin-top:.75rem">انتخاب فعلی: <span x-text="label"></span> — <span x-text="state"></span></p>
        <div aria-live="polite" class="text-sm" style="margin:.75rem 0">
            <span x-show="loading">در حال دریافت فهرست…</span>
            <span x-show="!loading && !error" x-text="`${total} نتیجه — صفحه ${page} از ${lastPage}`"></span>
            <span x-show="error" x-text="error" role="alert"></span>
        </div>
        <x-filament::button type="button" color="gray" x-show="error" x-on:click="load(page)">تلاش مجدد</x-filament::button>
        <p x-show="!loading && !error && items.length === 0">موردی یافت نشد.</p>
        <div class="grid gap-2" x-bind:aria-busy="loading">
            <template x-for="item in items" :key="item.reference_id">
                <button type="button" x-on:click="choose(item)" x-bind:disabled="!item.selectable || disabled || loading"
                    x-bind:aria-pressed="mode === 'reference' && String(state) === String(item.reference_id)"
                    class="w-full rounded-lg border border-gray-200 p-3 text-start dark:border-gray-700"
                    x-bind:style="!item.selectable ? 'opacity:.55;cursor:not-allowed' : ''">
                    <span class="block font-bold" x-text="item.title"></span>
                    <span class="block text-sm" style="overflow-wrap:anywhere" x-text="item.subtitle"></span>
                    <span class="block text-sm" x-text="item.details || ''"></span>
                    <span class="block text-sm"><span x-text="`شناسه ${item.reference_id} — ${item.status}`"></span><span x-show="mode === 'reference' && String(state) === String(item.reference_id)"> — انتخاب فعلی ✓</span></span>
                </button>
            </template>
        </div>
        <div class="flex items-center justify-between gap-3" style="margin-top:1rem">
            <x-filament::button type="button" color="gray" x-on:click="load(page - 1)" x-bind:disabled="loading || page <= 1">صفحه قبل</x-filament::button>
            <x-filament::button type="button" color="gray" x-on:click="load(page + 1)" x-bind:disabled="loading || page >= lastPage">صفحه بعد</x-filament::button>
        </div>
    </dialog>
</div>
