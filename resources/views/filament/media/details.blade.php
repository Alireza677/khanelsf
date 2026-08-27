@php
    $mimeType = (string) $record->mime_type;
    $isImage = str_starts_with($mimeType, 'image/');
    $isVideo = str_starts_with($mimeType, 'video/');
    $dimensions = method_exists($record, 'imageDimensions') ? $record->imageDimensions() : null;
    $url = $record->getUrl();
@endphp

<div class="media-details">
    <div class="media-details__navigation" aria-label="پیمایش رسانه‌ها">
        <button
            type="button"
            class="media-details__nav-button"
            wire:click="mountTableAction('details', '{{ $previousId }}')"
            @disabled(! $previousId)
        >
            <span aria-hidden="true">←</span> قبلی
        </button>
        <button
            type="button"
            class="media-details__nav-button"
            wire:click="mountTableAction('details', '{{ $nextId }}')"
            @disabled(! $nextId)
        >
            بعدی <span aria-hidden="true">→</span>
        </button>
    </div>

    <div class="media-details__layout">
        <div class="media-details__preview">
            @if ($isImage)
                <img src="{{ $url }}" alt="{{ $record->altText() }}">
            @elseif ($isVideo)
                <video src="{{ $url }}" controls preload="metadata"></video>
            @else
                <x-filament::icon icon="heroicon-o-document" class="media-details__file-icon" />
                <strong>{{ strtoupper(pathinfo($record->file_name, PATHINFO_EXTENSION)) ?: 'FILE' }}</strong>
            @endif
        </div>

        <dl class="media-details__metadata">
            <div><dt>نام فایل اولیه</dt><dd>{{ $record->originalFilename() }}</dd></div>
            <div><dt>شناسه رسانه</dt><dd dir="ltr">{{ $record->getKey() }}</dd></div>
            <div><dt>شناسه Asset</dt><dd dir="ltr">{{ $record->asset_key ?: 'Legacy asset' }}</dd></div>
            <div><dt>نوع فایل</dt><dd>{{ $mimeType ?: '—' }}</dd></div>
            <div><dt>حجم</dt><dd>{{ \App\Filament\Resources\MediaResource::formatSize((int) $record->size) }}</dd></div>
            @if ($dimensions)
                <div><dt>ابعاد</dt><dd dir="ltr">{{ $dimensions['width'] }} × {{ $dimensions['height'] }} px</dd></div>
            @endif
            <div><dt>تاریخ آپلود</dt><dd>{{ $record->created_at?->format('Y-m-d H:i') ?: '—' }}</dd></div>
            <div class="media-details__url">
                <dt>URL اصلی</dt>
                <dd x-data="{ copied: false }">
                    <input type="text" value="{{ $url }}" readonly dir="ltr">
                    <button
                        type="button"
                        x-on:click="navigator.clipboard.writeText(@js($url)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                        x-text="copied ? 'کپی شد' : 'کپی URL'"
                    ></button>
                </dd>
            </div>
        </dl>
    </div>
</div>
