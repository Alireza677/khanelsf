@php
    $fields ??= app(\App\Services\FormSchema::class)->fields($form);
    $displayMode ??= 'page';
    $attributionContext = is_array($attributionContext ?? null) ? $attributionContext : [];
    $instanceToken = is_string($instanceToken ?? null) && preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $instanceToken) === 1
        ? $instanceToken
        : null;
    $renderInstanceId = $instanceToken ?? strtolower((string) \Illuminate\Support\Str::ulid());
    $formDomId = 'form-'.$form->getKey().'-'.$renderInstanceId;
    $feedbackInstance = session('_form_feedback_instance');
    $ownsFeedback = $instanceToken === null || hash_equals((string) $instanceToken, (string) $feedbackInstance);
    $errorBagName = $instanceToken === null ? 'default' : 'form_'.substr(hash('sha256', $instanceToken), 0, 24);
    $formErrors = $errors->getBag($errorBagName);
    $oldValue = fn (string $name): mixed => $ownsFeedback ? old($name) : null;
    $successMessage = $instanceToken === null
        ? session('form_success')
        : data_get(session('form_success_instances', []), $instanceToken);
    $calculatorResultState = $instanceToken === null
        ? session('calculator_result_state')
        : data_get(session('calculator_result_instances', []), $instanceToken);
    $calculatorResult = $form->isCalculator()
        && (int) data_get($calculatorResultState, 'form_id') === (int) $form->getKey()
            ? data_get($calculatorResultState, 'calculation_result')
            : null;
    $calculatorReportUrl = $calculatorResult
        ? data_get($calculatorResultState, 'report_url')
        : null;
    $hasStepMarkers = collect($fields)->contains(fn (array $field): bool => $field['type'] === 'page');
    $steps = [];
    $currentStep = ['field_id' => null, 'label' => $form->name, 'description' => null, 'fields' => []];

    foreach ($fields as $field) {
        if ($field['type'] === 'page') {
            if ($currentStep['fields'] !== []) {
                $steps[] = $currentStep;
            }

            $currentStep = ['field_id' => $field['field_id'], 'label' => $field['label'], 'description' => $field['description'], 'fields' => []];
            continue;
        }

        $currentStep['fields'][] = $field;
    }

    if ($currentStep['fields'] !== [] || $steps === []) {
        $steps[] = $currentStep;
    }

    $isMultiStep = $hasStepMarkers && count($steps) > 1;
    $submitConfirmationEnabled = \App\Support\FormSubmitConfirmation::enabled($form);
    $submitConfirmationText = \App\Support\FormSubmitConfirmation::text($form);
    $submitConfirmationKey = \App\Support\FormSubmitConfirmation::INPUT_KEY;
    $submitConfirmationChecked = $ownsFeedback && (string) old($submitConfirmationKey) === '1';
    $submitConfirmationHasError = $formErrors->has($submitConfirmationKey);
    $imageUrl = static function (?string $image): ?string {
        if ($image === null || str_starts_with($image, '/') || preg_match('#^https?://#i', $image) === 1) {
            return $image;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    };
@endphp

@if (! $form->isCalculator() && filled($successMessage))
    <p class="form-status" role="status">{{ $successMessage }}</p>
@endif

@if ($formErrors->any())
    <div class="form-error" role="alert">
        <p>لطفا فرم را بررسی کنید و دوباره تلاش کنید.</p>
    </div>
@endif

<form
    id="{{ $formDomId }}"
    class="form-card"
    method="post"
    enctype="multipart/form-data"
    action="{{ route('forms.submit', $form->slug) }}"
    @if($isMultiStep) data-multi-step-form @endif
    @if($isMultiStep && $submitConfirmationHasError) data-submit-confirmation-error="true" @endif
    @if($isMultiStep && $calculatorResult) data-initial-step="last" @endif
>
    @csrf

    <input type="hidden" name="_display_mode" value="{{ $displayMode }}">
    @if ($instanceToken !== null)
        <input type="hidden" name="_form_instance" value="{{ $instanceToken }}">
    @endif

    @if (filled($attributionContext['page_id'] ?? null))
        <input type="hidden" name="_context_page_id" value="{{ $attributionContext['page_id'] }}">
    @endif
    @if (filled($attributionContext['page_url'] ?? null))
        <input type="hidden" name="_context_page_url" value="{{ $attributionContext['page_url'] }}">
    @endif
    @if (filled($attributionContext['block_id'] ?? null))
        <input type="hidden" name="_context_block_id" value="{{ $attributionContext['block_id'] }}">
    @endif

    <div class="form-honeypot" aria-hidden="true">
        <label for="{{ $formDomId }}-website">وب‌سایت</label>
        <input id="{{ $formDomId }}-website" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>

    @if ($isMultiStep)
        <div class="form-step-indicator" aria-live="polite">
            <span data-step-current>۱</span>
            <span>از {{ count($steps) }}</span>
        </div>
    @endif

    @foreach ($steps as $stepIndex => $step)
        @php($stepDomId = $formDomId.'-step-'.strtolower($step['field_id'] ?? (string) $stepIndex))
        <section
            class="form-step form-fields"
            data-form-step="{{ $stepIndex }}"
            aria-labelledby="{{ $stepDomId }}"
            aria-hidden="{{ $isMultiStep && $stepIndex > 0 ? 'true' : 'false' }}"
            @if($isMultiStep && $stepIndex > 0) hidden @endif
        >
            @if ($isMultiStep)
                <header class="form-step__header">
                    <h2 id="{{ $stepDomId }}">{{ $step['label'] }}</h2>
                    @if ($step['description'])
                        <p>{{ $step['description'] }}</p>
                    @endif
                </header>
            @endif

            @foreach ($step['fields'] as $field)
                @php($inputId = $formDomId.'-field-'.strtolower($field['field_id']))
                @php($fieldHasError = $formErrors->has($field['name']) || $formErrors->has($field['name'].'.*'))
                @php($errorId = $inputId.'-error')
                @php($columnSpan = \App\Services\FormSchema::normalizeColumnSpan(data_get($field, 'layout.span')))
                <div class="form-field form-field--span-{{ $columnSpan }}">
                    @if ($field['type'] === 'step')
                        <div class="form-section-divider">
                            <div class="form-section-divider__title">{{ $field['label'] }}</div>
                            <div class="form-section-divider__line" aria-hidden="true"></div>
                            @if ($field['description'])
                                <p>{{ $field['description'] }}</p>
                            @endif
                        </div>
                    @elseif ($field['type'] === 'date')
                        @php($canonicalDate = is_string($oldValue($field['name'])) ? $oldValue($field['name']) : '')
                        <label id="{{ $inputId }}-label" for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <div
                            class="form-date-picker"
                            data-form-date-picker
                            data-placeholder="{{ $field['placeholder'] ?: 'تاریخ را انتخاب کنید' }}"
                            @if($field['date_range_enabled'] && $field['min_date']) data-min-date="{{ $field['min_date'] }}" @endif
                            @if($field['date_range_enabled'] && $field['max_date']) data-max-date="{{ $field['max_date'] }}" @endif
                        >
                            <input
                                id="{{ $inputId }}"
                                class="form-date-picker__canonical"
                                name="{{ $field['name'] }}"
                                type="text"
                                inputmode="numeric"
                                value="{{ $canonicalDate }}"
                                placeholder="YYYY-MM-DD"
                                pattern="\d{4}-\d{2}-\d{2}"
                                autocomplete="off"
                                @required($field['required'])
                                @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                                data-form-date-canonical
                            >
                            <button
                                type="button"
                                class="form-date-picker__trigger"
                                aria-haspopup="dialog"
                                aria-expanded="false"
                                aria-labelledby="{{ $inputId }}-label {{ $inputId }}-display"
                                hidden
                                data-form-date-trigger
                            >
                                <span id="{{ $inputId }}-display" data-form-date-display></span>
                                <span class="form-date-picker__icon" aria-hidden="true">📅</span>
                            </button>
                            <div class="form-date-picker__calendar" role="dialog" aria-modal="false" aria-label="انتخاب تاریخ شمسی" hidden data-form-date-calendar></div>
                        </div>
                    @elseif ($field['type'] === 'number')
                        @php($numberValue = \App\Support\FormNumber::format($oldValue($field['name']), $field['settings']['thousands_separator']))
                        <label for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <input
                            id="{{ $inputId }}"
                            name="{{ $field['name'] }}"
                            type="text"
                            inputmode="{{ $field['settings']['allow_decimals'] ? 'decimal' : 'numeric' }}"
                            dir="ltr"
                            value="{{ $numberValue }}"
                            placeholder="{{ $field['placeholder'] }}"
                            data-form-number
                            data-thousands-separator="{{ $field['settings']['thousands_separator'] ? 'true' : 'false' }}"
                            data-allow-decimals="{{ $field['settings']['allow_decimals'] ? 'true' : 'false' }}"
                            data-decimal-places="{{ $field['settings']['decimal_places'] }}"
                            @required($field['required'])
                            @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                        >
                    @elseif ($field['type'] === 'textarea')
                        <label for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <textarea id="{{ $inputId }}" name="{{ $field['name'] }}" rows="5" placeholder="{{ $field['placeholder'] }}" @required($field['required']) @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>{{ $oldValue($field['name']) }}</textarea>
                    @elseif ($field['type'] === 'file')
                        @php($fileStatusId = $inputId.'-status')
                        <label id="{{ $inputId }}-label" for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <input
                            id="{{ $inputId }}"
                            class="sr-only form-file-input"
                            name="{{ $field['name'] }}"
                            type="file"
                            accept="{{ collect($field['settings']['allowed_extensions'])->map(fn (string $extension): string => '.'.$extension)->implode(',') }}"
                            @required($field['required'])
                            aria-describedby="{{ $fileStatusId }}{{ $fieldHasError ? ' '.$errorId : '' }}"
                            @if($fieldHasError) aria-invalid="true" @endif
                            data-form-file-input
                        >
                        <div class="form-file-picker" data-form-file-picker>
                            <label class="form-file-picker__button" for="{{ $inputId }}">انتخاب فایل</label>
                            <span id="{{ $fileStatusId }}" class="form-file-picker__status" aria-live="polite" data-form-file-status>فایلی انتخاب نشده است</span>
                        </div>
                        <div class="form-file-help">
                            <span>حداکثر حجم فایل: {{ \App\Support\PersianDate::digits($field['settings']['max_size_mb']) }} مگابایت</span>
                            <span>فرمت‌های مجاز: {{ $field['settings']['allowed_extensions_label'] }}</span>
                        </div>
                    @elseif ($field['type'] === 'select')
                        @php($selectedValue = $oldValue($field['name']) ?? '')
                        @php($selectLabelId = $inputId.'-label')
                        @php($selectValueId = $inputId.'-value')
                        @php($listboxId = $inputId.'-listbox')
                        <label id="{{ $selectLabelId }}" for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <div class="form-select" data-form-select dir="auto">
                            <select
                                id="{{ $inputId }}"
                                class="form-select__native"
                                name="{{ $field['name'] }}"
                                data-form-select-native
                                tabindex="-1"
                                aria-hidden="true"
                                @required($field['required'])
                                @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                            >
                                <option value="">انتخاب کنید</option>
                                @foreach ($field['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedValue === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button
                                type="button"
                                class="form-select__trigger"
                                role="combobox"
                                aria-haspopup="listbox"
                                aria-expanded="false"
                                aria-controls="{{ $listboxId }}"
                                aria-labelledby="{{ $selectLabelId }} {{ $selectValueId }}"
                                @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                                data-form-select-trigger
                            >
                                <span id="{{ $selectValueId }}" data-form-select-value>{{ $field['options'][$selectedValue] ?? 'انتخاب کنید' }}</span>
                                <span class="form-select__chevron" aria-hidden="true"></span>
                            </button>
                            <div
                                id="{{ $listboxId }}"
                                class="form-select__panel"
                                role="listbox"
                                aria-labelledby="{{ $selectLabelId }}"
                                hidden
                                data-form-select-listbox
                            >
                                <button type="button" class="form-select__option" role="option" id="{{ $listboxId }}-empty" data-form-select-option data-value="" aria-selected="{{ $selectedValue === '' ? 'true' : 'false' }}">انتخاب کنید</button>
                                @foreach ($field['options'] as $value => $label)
                                    <button type="button" class="form-select__option" role="option" id="{{ $listboxId }}-{{ $loop->index }}" data-form-select-option data-value="{{ $value }}" aria-selected="{{ $selectedValue === $value ? 'true' : 'false' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @elseif ($field['type'] === 'radio')
                        <fieldset class="form-adaptive-choice-group form-radio-group" @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                            <legend>{{ $field['label'] }}</legend>
                            <div class="form-adaptive-choice-grid form-radio-options">
                                @foreach ($field['options'] as $optionIndex => $option)
                                    @php($optionId = $inputId.'-option-'.strtolower($option['option_id']))
                                    <label class="form-adaptive-choice form-radio-option {{ \App\Support\FormChoicePresentation::sizeClass($option['label']) }}" for="{{ $optionId }}">
                                        <input
                                            id="{{ $optionId }}"
                                            name="{{ $field['name'] }}"
                                            type="radio"
                                            value="{{ $option['value'] }}"
                                            @checked($oldValue($field['name']) === $option['value'])
                                            @required($field['required'])
                                        >
                                        <span>{{ $option['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @elseif ($field['type'] === 'checkbox')
                        @php($selectedValues = is_array($oldValue($field['name'])) ? $oldValue($field['name']) : [])
                        <fieldset class="form-adaptive-choice-group form-checkbox-group" @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                            <legend>{{ $field['label'] }}</legend>
                            <div class="form-adaptive-choice-grid form-checkbox-options">
                                @foreach ($field['options'] as $optionIndex => $option)
                                    @php($optionId = $inputId.'-option-'.strtolower($option['option_id']))
                                    <label class="form-adaptive-choice form-checkbox-option {{ \App\Support\FormChoicePresentation::sizeClass($option['label']) }}" for="{{ $optionId }}">
                                        <input
                                            id="{{ $optionId }}"
                                            name="{{ $field['name'] }}[]"
                                            type="checkbox"
                                            value="{{ $option['value'] }}"
                                            @checked(in_array($option['value'], $selectedValues, true))
                                        >
                                        <span>{{ $option['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @elseif (in_array($field['type'], ['image_choice', 'radio_card'], true))
                        <fieldset class="form-choice-group" @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                            <legend>{{ $field['label'] }}</legend>
                            <div class="form-choice-grid">
                                @foreach ($field['options'] as $optionIndex => $option)
                                    @php($optionId = $inputId.'-option-'.strtolower($option['option_id']))
                                    <label class="form-choice-card" for="{{ $optionId }}">
                                        <input id="{{ $optionId }}" name="{{ $field['name'] }}" type="radio" value="{{ $option['value'] }}" @checked($oldValue($field['name']) === $option['value']) @required($field['required'])>
                                        @if ($option['image'])
                                            <img src="{{ $imageUrl($option['image']) }}" alt="" loading="lazy">
                                        @endif
                                        <span>{{ $option['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @else
                        <label for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <input id="{{ $inputId }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $oldValue($field['name']) }}" placeholder="{{ $field['placeholder'] }}" @required($field['required']) @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
                    @endif

                    @if ($fieldHasError)
                        <p id="{{ $errorId }}" class="form-error">{{ $formErrors->first($field['name']) ?: $formErrors->first($field['name'].'.*') }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach

    @if ($submitConfirmationEnabled)
        <div class="form-submit-confirmation" data-submit-confirmation>
            <label for="{{ $formDomId }}-submit-confirmation">
                <input
                    id="{{ $formDomId }}-submit-confirmation"
                    name="{{ $submitConfirmationKey }}"
                    type="checkbox"
                    value="1"
                    @checked($submitConfirmationChecked)
                    @if($submitConfirmationHasError) aria-invalid="true" aria-describedby="{{ $formDomId }}-submit-confirmation-error" @endif
                    data-submit-confirmation-input
                >
                <span>{{ $submitConfirmationText }}</span>
            </label>
            @if ($submitConfirmationHasError)
                <p id="{{ $formDomId }}-submit-confirmation-error" class="form-error">{{ $formErrors->first($submitConfirmationKey) }}</p>
            @endif
        </div>
    @endif

    @if ($isMultiStep)
        <div class="form-step-navigation">
            <button class="button" type="button" data-step-back hidden>قبلی</button>
            <button class="button" type="button" data-step-next>بعدی</button>
            <button class="button" type="submit" data-step-submit data-form-submit @disabled($submitConfirmationEnabled && ! $submitConfirmationChecked) hidden>{{ data_get($form->settings, 'submit_label', 'ارسال') }}</button>
        </div>
    @else
        <button class="button" type="submit" data-form-submit @disabled($submitConfirmationEnabled && ! $submitConfirmationChecked)>{{ data_get($form->settings, 'submit_label', 'ارسال') }}</button>
    @endif
</form>

@if ($calculatorResult)
    @include('forms._calculator-result-modal', [
        'form' => $form,
        'calculationResult' => $calculatorResult,
        'reportUrl' => $calculatorReportUrl,
        'modalId' => $formDomId.'-calculation-result',
    ])
@endif
