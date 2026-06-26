@php
    $value = trim((string) ($icon ?? ''));
    $fallback = trim((string) ($fallback ?? ''));
    $content = $value !== '' ? $value : $fallback;
@endphp

@if ($content !== '')
    @if (\Illuminate\Support\Str::startsWith($content, 'icon-'))
        <i class="{{ $content }}" aria-hidden="true"></i>
    @else
        {{ $content }}
    @endif
@endif
