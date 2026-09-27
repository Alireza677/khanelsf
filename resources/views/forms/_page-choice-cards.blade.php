<fieldset class="form-choice-group form-page__choice-group" @if($fieldHasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
    <legend>{{ $field['label'] }}</legend>
    <div class="form-page__choices">
        @foreach ($field['options'] as $option)
            @php($optionId = $inputId.'-option-'.strtolower($option['option_id']))
            @php($hasMedia = filled($option['image'] ?? null) || filled($option['icon'] ?? null))
            <label class="form-choice-card form-page__choice {{ $hasMedia ? 'has-media' : 'no-media' }}" for="{{ $optionId }}">
                @if ($hasMedia)
                    <span class="form-page__choice-media" aria-hidden="true">
                        @if ($option['image'])
                            <img src="{{ $imageUrl($option['image']) }}" alt="" loading="lazy">
                        @elseif (filled($option['icon'] ?? null))
                            @include('partials.blocks._icon', ['icon' => $option['icon']])
                        @endif
                    </span>
                @endif
                <span class="form-page__choice-content">
                    <span class="form-page__choice-title">{{ $option['label'] }}</span>
                    @if (filled($option['description'] ?? null))
                        <span class="form-page__choice-description">{{ $option['description'] }}</span>
                    @endif
                </span>
                <input id="{{ $optionId }}" name="{{ $field['name'] }}" type="radio" value="{{ $option['value'] }}" @checked($oldValue($field['name']) === $option['value']) @required($field['required'])>
            </label>
        @endforeach
    </div>
</fieldset>
