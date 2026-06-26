@php
    $requestedHeadingTag = $tag ?? 'h2';
    $headingTag = in_array($requestedHeadingTag, ['h1', 'h2'], true) ? $requestedHeadingTag : 'h2';
@endphp

<{{ $headingTag }} class="block-title">{{ $title }}</{{ $headingTag }}>
