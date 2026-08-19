@php
    $value = trim((string) ($icon ?? ''));
    $fallback = trim((string) ($fallback ?? ''));
    $content = $value !== '' ? $value : $fallback;
    $size = (int) ($size ?? 0);
    $style = $size > 0 ? "font-size: {$size}px" : null;
    $heroicon = null;

    if (\Illuminate\Support\Str::startsWith($content, 'heroicon-')) {
        try {
            $attributes = ['aria-hidden' => 'true'];
            if ($style) {
                $attributes['style'] = $style;
            }
            $heroicon = svg($content, $attributes)->toHtml();
        } catch (\Throwable) {
            $heroicon = null;
        }
    }
@endphp

@if ($content !== '')
    @if (\Illuminate\Support\Str::startsWith($content, 'icon-'))
        <i class="{{ $content }}" @if ($style) style="{{ $style }}" @endif aria-hidden="true"></i>
    @elseif ($heroicon !== null)
        {!! $heroicon !!}
    @elseif (\Illuminate\Support\Str::startsWith($content, 'heroicon-'))
        {{-- Invalid legacy Heroicon keys fail closed instead of leaking into visible text. --}}
    @else
        {{ $content }}
    @endif
@endif
