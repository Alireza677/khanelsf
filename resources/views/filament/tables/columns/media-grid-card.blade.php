@php
    $record = $getRecord();
    $url = $record->getUrl();
    $mimeType = $record->mime_type ?? '';
    $extension = strtoupper(pathinfo($record->file_name, PATHINFO_EXTENSION));
@endphp

<div class="media-grid-card" title="{{ $record->file_name }}">
    <div class="media-grid-card__preview">
        @if (str_starts_with($mimeType, 'image/'))
            <img
                src="{{ $url }}"
                alt="{{ $record->name }}"
                loading="lazy"
            >
        @elseif (str_starts_with($mimeType, 'video/'))
            <video
                src="{{ $url }}"
                muted
                playsinline
                preload="metadata"
            ></video>
            <span class="media-grid-card__type-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-s-play" class="h-6 w-6" />
            </span>
        @else
            <x-filament::icon icon="heroicon-o-document" class="media-grid-card__file-icon" />
            @if ($extension !== '')
                <span class="media-grid-card__extension">{{ $extension }}</span>
            @endif
        @endif
    </div>

    <div class="media-grid-card__title">{{ $record->file_name }}</div>
</div>
