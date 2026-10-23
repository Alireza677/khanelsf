@php($previewLocations = app(\App\Services\IranLocations::class))
<div class="form-builder-location-preview" dir="rtl" wire:ignore x-on:click.stop
    x-data="{
        province: '', city: '', provinceQuery: '', cityQuery: '',
        provinces: @js(array_values($previewLocations->provinces())),
        cities: @js($previewLocations->browserData()),
        normalize(value) { return String(value).normalize('NFKC').replace(/ي|ى/g, 'ی').replace(/ك/g, 'ک').replace(/[\s\u200c\u200d]+/g, '') },
        matches(name, query) { return this.normalize(name).includes(this.normalize(query)) },
    }">
    <label>
        <span>استان</span>
        <input type="search" x-model="provinceQuery" placeholder="جست‌وجوی استان…" aria-label="جست‌وجوی استان در پیش‌نمایش">
        <select x-model="province" x-on:change="city = ''; cityQuery = ''" aria-label="استان در پیش‌نمایش">
            <option value="">انتخاب کنید</option>
            <template x-for="item in provinces.filter(item => String(item.id) === province || matches(item.name, provinceQuery))" :key="item.id">
                <option :value="String(item.id)" x-text="item.name"></option>
            </template>
        </select>
    </label>
    <label>
        <span>شهر</span>
        <input type="search" x-model="cityQuery" :disabled="! province" placeholder="جست‌وجوی شهر…" aria-label="جست‌وجوی شهر در پیش‌نمایش">
        <select x-model="city" :disabled="! province" aria-label="شهر در پیش‌نمایش">
            <option value="" x-text="province ? 'انتخاب کنید' : 'ابتدا استان را انتخاب کنید'"></option>
            <template x-for="item in cities.filter(item => item.province_id === province && (item.id === city || matches(item.name, cityQuery)))" :key="item.id">
                <option :value="item.id" x-text="item.name"></option>
            </template>
        </select>
    </label>
</div>
