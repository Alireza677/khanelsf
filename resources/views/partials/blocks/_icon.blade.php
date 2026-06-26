@php
    $value = trim((string) ($icon ?? ''));
    $fallback = trim((string) ($fallback ?? ''));
    $content = $value !== '' ? $value : $fallback;
    $size = (int) ($size ?? 0);
    $style = $size > 0 ? "font-size: {$size}px" : null;
@endphp

@if ($content !== '')
    @if (\Illuminate\Support\Str::startsWith($content, 'icon-'))
        <i class="{{ $content }}" @if ($style) style="{{ $style }}" @endif aria-hidden="true"></i>
    @else
        {{ $content }}
    @endif
@endif
