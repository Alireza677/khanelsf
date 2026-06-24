@php
    $record = $getRecord();
    $url = $record->getUrl();
    $mimeType = $record->mime_type ?? '';
@endphp

@if (str_starts_with($mimeType, 'image/'))
    <img src="{{ $url }}" alt="{{ $record->name }}" style="height: 4rem; width: 4rem; object-fit: cover; border-radius: .375rem;">
@elseif (str_starts_with($mimeType, 'video/'))
    <video src="{{ $url }}" muted playsinline style="height: 4rem; width: 6rem; object-fit: cover; border-radius: .375rem;"></video>
@else
    <span>{{ strtoupper(pathinfo($record->file_name, PATHINFO_EXTENSION)) }}</span>
@endif
