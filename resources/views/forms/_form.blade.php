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
    $hasStepMarkers = collect($fields)->contains(fn (array $field): bool => in_array($field['type'], ['page', 'step'], true));
    $steps = [];
    $currentStep = ['field_id' => null, 'label' => $form->name, 'description' => null, 'fields' => []];

    foreach ($fields as $field) {
        if (in_array($field['type'], ['page', 'step'], true)) {
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
    action="{{ route('forms.submit', $form->slug) }}"
    @if($isMultiStep) data-multi-step-form @endif
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
                @php($fieldHasError = $formErrors->has($field['name']))
                @php($errorId = $inputId.'-error')
                @php($columnSpan = \App\Services\FormSchema::normalizeColumnSpan(data_get($field, 'layout.span')))
                <div class="form-field form-field--span-{{ $columnSpan }}">
                    @if ($field['type'] === 'textarea')
                        <label for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <textarea id="{{ $inputId }}" name="{{ $field['name'] }}" rows="5" placeholder="{{ $field['placeholder'] }}" @required($field['required']) @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>{{ $oldValue($field['name']) }}</textarea>
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
                        <p id="{{ $errorId }}" class="form-error">{{ $formErrors->first($field['name']) }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach

    @if ($isMultiStep)
        <div class="form-step-navigation">
            <button class="button" type="button" data-step-back hidden>قبلی</button>
            <button class="button" type="button" data-step-next>بعدی</button>
            <button class="button" type="submit" data-step-submit hidden>{{ data_get($form->settings, 'submit_label', 'ارسال') }}</button>
        </div>
    @else
        <button class="button" type="submit">{{ data_get($form->settings, 'submit_label', 'ارسال') }}</button>
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
