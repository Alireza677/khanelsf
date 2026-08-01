@php
    $requestedHeadingTag = $tag ?? 'h2';
    $headingTag = \App\CMS\Blocks\Support\HeadingLevel::normalize($requestedHeadingTag);
@endphp

<{{ $headingTag }} class="block-title">{{ $title }}</{{ $headingTag }}>
