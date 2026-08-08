@props(['title' => null, 'class' => ''])

<section {{ $attributes->class(['portal-card', $class]) }}>
    @if ($title)<h2 class="portal-card__title">{{ $title }}</h2>@endif
    {{ $slot }}
</section>
