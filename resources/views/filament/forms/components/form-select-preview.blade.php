@php($selectPreviewId = 'select-preview-'.$uuid)
<div class="form-builder-select-preview" dir="rtl" wire:ignore
    wire:key="{{ $selectPreviewId }}-{{ hash('sha256', json_encode([$label, $itemState['placeholder'] ?? null, $itemState['options'] ?? []])) }}" x-on:click.stop>
    <label id="{{ $selectPreviewId }}-label" for="{{ $selectPreviewId }}">{{ $label }}</label>
    <div class="form-select" data-form-select>
        <select id="{{ $selectPreviewId }}" class="form-select__native" data-form-select-native aria-label="{{ $label }} در پیش‌نمایش">
            <option value="">{{ $itemState['placeholder'] ?? 'انتخاب کنید' }}</option>
            @foreach (is_array($itemState['options'] ?? null) ? $itemState['options'] : [] as $optionKey => $previewOption)
                @if (is_array($previewOption))
                    <option value="{{ $previewOption['value'] ?? $optionKey }}">{{ $previewOption['label'] ?? '' }}</option>
                @elseif (is_string($previewOption))
                    <option value="{{ $optionKey }}">{{ $previewOption }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>
