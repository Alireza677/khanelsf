<img class="{{ $imageClass ?? '' }}" src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy" decoding="async"
    @if ($image['width'] && $image['height']) width="{{ $image['width'] }}" height="{{ $image['height'] }}" @endif>
