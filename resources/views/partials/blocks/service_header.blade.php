@php
    $hero = app(\App\CMS\Blocks\Service\ServiceHeaderRuntime::class)->prepare(
        is_array($data ?? null) ? $data : [],
        is_array($context ?? null) ? $context : [],
        ! empty($isPreview) || ! empty($context['preview']),
    );
@endphp

@include('partials.presentations.hero', ['hero' => $hero])
