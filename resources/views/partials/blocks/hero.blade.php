@php
    $hero = app(\App\CMS\Blocks\Hero\HeroDataNormalizer::class)->normalize($data);
    $hero = app(\App\CMS\Blocks\Hero\HeroActionPresenter::class)->prepare($hero, $context ?? [], ! empty($isPreview) || ! empty($context['preview']));
    $template = in_array($hero['template'] ?? 'default', ['hero_1', 'hero_2', 'hero_3'], true) ? $hero['template'] : 'default';
@endphp

@include("partials.blocks.hero.{$template}", ['hero' => $hero])
