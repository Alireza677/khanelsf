@php
    $previewLocations = app(\App\Services\IranLocations::class);
    $previewId = 'iran-preview-'.($uuid ?? strtolower((string) \Illuminate\Support\Str::ulid()));
@endphp
<div class="form-builder-location-preview" dir="rtl" wire:key="{{ $previewId }}" wire:ignore x-on:click.stop
    x-data="{
        province: '', city: '',
        provinces: @js(array_values($previewLocations->provinces())),
        cities: @js($previewLocations->browserData()),
    }">
    <div>
        <label id="{{ $previewId }}-province-label" for="{{ $previewId }}-province">استان</label>
        <div class="form-select" data-form-select>
            <select id="{{ $previewId }}-province" class="form-select__native" data-form-select-native
                x-model="province" x-on:change="city = ''" aria-label="استان در پیش‌نمایش">
                <option value="">انتخاب کنید</option>
                <template x-for="item in provinces" :key="item.id">
                    <option :value="String(item.id)" x-text="item.name"></option>
                </template>
            </select>
        </div>
    </div>
    <div>
        <label id="{{ $previewId }}-city-label" for="{{ $previewId }}-city">شهر</label>
        <div class="form-select" data-form-select>
            <select id="{{ $previewId }}-city" class="form-select__native" data-form-select-native
                x-model="city" :disabled="! province" aria-label="شهر در پیش‌نمایش">
                <option value="" x-text="province ? 'انتخاب کنید' : 'ابتدا استان را انتخاب کنید'"></option>
                <template x-for="item in cities.filter(item => item.province_id === province)" :key="item.id">
                    <option :value="item.id" x-text="item.name"></option>
                </template>
            </select>
        </div>
    </div>
</div>
