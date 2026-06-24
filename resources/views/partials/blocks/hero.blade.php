@php
    $template = in_array($data['template'] ?? 'default', ['hero_1', 'hero_2', 'hero_3'], true) ? $data['template'] : 'default';
@endphp

@include("partials.blocks.hero.{$template}", ['data' => $data])
