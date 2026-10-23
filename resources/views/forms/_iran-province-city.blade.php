@php
    $locations = app(\App\Services\IranLocations::class);
    $locationValue = $oldValue($field['name']);
    $locationPair = $locations->pair($locationValue);
    $provinceValue = $locationPair['province_id'] ?? '';
    // Preserve a valid province even when the submitted city failed validation.
    if ($provinceValue === '' && is_array($locationValue) && is_scalar($locationValue['province_id'] ?? null)) {
        $candidate = (string) $locationValue['province_id'];
        $provinceValue = isset($locations->provinces()[$candidate]) ? $candidate : '';
    }
    $cityValue = $locationPair['city_id'] ?? '';
    $locationSelects = [
        'province_id' => ['label' => 'استان', 'value' => $provinceValue, 'options' => $locations->provinces()],
        'city_id' => ['label' => 'شهر', 'value' => $cityValue, 'options' => array_filter($locations->cities(), fn (array $city): bool => (string) $city['province_id'] === $provinceValue)],
    ];
@endphp
<fieldset class="form-iran-location" data-iran-location dir="rtl" @if($fieldHasError) aria-describedby="{{ $errorId }}" @endif>
    <legend>{{ $field['label'] }}{{ $field['required'] ? ' *' : '' }}</legend>
    <div class="form-iran-location__grid">
        @foreach ($locationSelects as $part => $locationSelect)
            @php($locationId = $inputId.'-'.$part)
            @php($locationDisabled = $part === 'city_id' && $provinceValue === '')
            <div>
                <label id="{{ $locationId }}-label" for="{{ $locationId }}">{{ $locationSelect['label'] }}</label>
                <div class="form-select" data-form-select dir="rtl">
                    <select id="{{ $locationId }}" name="{{ $field['name'] }}[{{ $part }}]" class="form-select__native"
                        data-form-select-native data-location-part="{{ $part }}"
                        @required($field['required']) @disabled($locationDisabled)
                        @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                        <option value="">{{ $locationDisabled ? 'ابتدا استان را انتخاب کنید' : 'انتخاب کنید' }}</option>
                        @foreach ($locationSelect['options'] as $locationOption)
                            <option value="{{ $locationOption['id'] }}" @selected((string) $locationOption['id'] === $locationSelect['value'])>{{ $locationOption['name'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="form-select__trigger" role="combobox" aria-haspopup="listbox" aria-expanded="false"
                        aria-controls="{{ $locationId }}-listbox" aria-labelledby="{{ $locationId }}-label {{ $locationId }}-value"
                        @disabled($locationDisabled) data-form-select-trigger
                        @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                        <span id="{{ $locationId }}-value" data-form-select-value>انتخاب کنید</span>
                        <span class="form-select__chevron" aria-hidden="true"></span>
                    </button>
                    <div class="form-select__panel" hidden data-form-select-listbox>
                        <input type="search" class="form-select__search" placeholder="جست‌وجوی {{ $locationSelect['label'] }}…"
                            aria-label="جست‌وجوی {{ $locationSelect['label'] }}" aria-controls="{{ $locationId }}-listbox" autocomplete="off" data-form-select-search>
                        <div id="{{ $locationId }}-listbox" role="listbox" aria-labelledby="{{ $locationId }}-label" data-form-select-options>
                            <button type="button" class="form-select__option" role="option" tabindex="-1"
                                id="{{ $locationId }}-empty" data-form-select-option data-value="" aria-selected="false">{{ $locationDisabled ? 'ابتدا استان را انتخاب کنید' : 'انتخاب کنید' }}</button>
                            @foreach ($locationSelect['options'] as $locationOption)
                                <button type="button" class="form-select__option" role="option" tabindex="-1"
                                    id="{{ $locationId }}-option-{{ $locationOption['id'] }}" data-form-select-option data-value="{{ $locationOption['id'] }}" aria-selected="false">{{ $locationOption['name'] }}</button>
                            @endforeach
                        </div>
                        <p class="form-select__empty" hidden data-form-select-empty role="status">موردی پیدا نشد.</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</fieldset>
