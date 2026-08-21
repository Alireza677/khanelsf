@if ($datetime)
    <time {{ $attributes }} datetime="{{ $datetime }}">{{ $formatted ?? $fallback }}</time>
@else
    <span {{ $attributes }}>{{ $formatted ?? $fallback }}</span>
@endif
