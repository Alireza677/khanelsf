@php
    $fields ??= app(\App\Services\FormSchema::class)->fields($form);
    $displayMode ??= 'page';
    $attributionContext = is_array($attributionContext ?? null) ? $attributionContext : [];
    $renderInstanceId = strtolower((string) \Illuminate\Support\Str::ulid());
    $formDomId = 'form-'.$form->getKey().'-'.$renderInstanceId;
    $calculatorResultState = session('calculator_result_state');
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

@if (! $form->isCalculator() && session('form_success'))
    <p class="form-status" role="status">{{ session('form_success') }}</p>
@endif

@if ($errors->any())
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

    @if (filled($attributionContext['page_id'] ?? null))
        <input type="hidden" name="_context_page_id" value="{{ $attributionContext['page_id'] }}">
    @endif
    @if (filled($attributionContext['page_url'] ?? null))
        <input type="hidden" name="_context_page_url" value="{{ $attributionContext['page_url'] }}">
    @endif
    @if (filled($attributionContext['block_id'] ?? null))
        <input type="hidden" name="_context_block_id" value="{{ $attributionContext['block_id'] }}">
    @endif

    <div style="left: -9999px; position: absolute;" aria-hidden="true">
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
            class="form-step"
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
                <div class="form-field">
                    @if ($field['type'] === 'textarea')
                        <label for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <textarea id="{{ $inputId }}" name="{{ $field['name'] }}" rows="5" placeholder="{{ $field['placeholder'] }}" @required($field['required'])>{{ old($field['name']) }}</textarea>
                    @elseif ($field['type'] === 'select')
                        <label for="{{ $inputId }}">{{ $field['label'] }}</label>
                        <select id="{{ $inputId }}" name="{{ $field['name'] }}" @required($field['required'])>
                            <option value="">انتخاب کنید</option>
                            @foreach ($field['options'] as $value => $label)
                                <option value="{{ $value }}" @selected(old($field['name']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif (in_array($field['type'], ['image_choice', 'radio_card'], true))
                        <fieldset class="form-choice-group">
                            <legend>{{ $field['label'] }}</legend>
                            <div class="form-choice-grid">
                                @foreach ($field['options'] as $optionIndex => $option)
                                    @php($optionId = $inputId.'-option-'.strtolower($option['option_id']))
                                    <label class="form-choice-card" for="{{ $optionId }}">
                                        <input id="{{ $optionId }}" name="{{ $field['name'] }}" type="radio" value="{{ $option['value'] }}" @checked(old($field['name']) === $option['value']) @required($field['required'])>
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
                        <input id="{{ $inputId }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ old($field['name']) }}" placeholder="{{ $field['placeholder'] }}" @required($field['required'])>
                    @endif

                    @error($field['name'])
                        <p class="form-error">{{ $message }}</p>
                    @enderror
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
