<div data-footer-element="{{ $element['id'] }}" data-footer-element-type="{{ $element['type'] }}">
    @switch($element['type'])
        @case('branding')
            <section class="corporate-footer__branding">
                @if ($element['logo'])
                    @include('partials.blocks.site-footer.image', ['image' => $element['logo'], 'imageClass' => 'corporate-footer__logo'])
                @endif
                @if ($element['name'])
                    @include('partials.blocks._heading', ['title' => $element['name'], 'tag' => $heading])
                @endif
            </section>
            @break
        @case('navigation')
            <nav class="corporate-footer__menu" aria-label="{{ $element['title'] ?: 'پیوندهای فوتر' }}">
                @if ($element['title'])
                    @include('partials.blocks._heading', ['title' => $element['title'], 'tag' => $heading])
                @endif
                @include('partials.blocks.site-footer.navigation', ['items' => $element['items']])
            </nav>
            @break
        @case('contact')
            <section class="corporate-footer__contact">
                @if ($element['title'])
                    @include('partials.blocks._heading', ['title' => $element['title'], 'tag' => $heading])
                @endif
                <address>
                    @foreach ($element['contacts'] as $contact)
                        <div class="corporate-footer__contact-item">
                            <span class="corporate-footer__contact-icon" aria-hidden="true">
                                @include('partials.blocks._icon', ['icon' => match ($contact['type']) {
                                    'phone' => 'heroicon-o-phone', 'mobile' => 'heroicon-o-device-phone-mobile',
                                    'email' => 'heroicon-o-envelope', 'address' => 'heroicon-o-map-pin', default => 'heroicon-o-clock',
                                }])
                            </span>
                            <span class="corporate-footer__contact-value">
                                <span class="corporate-footer__contact-label">{{ $contact['label'] }}</span>
                                @if ($contact['presentation'])
                                    @include('partials.blocks.site-footer.link', ['link' => ['label' => $contact['value'], 'presentation' => $contact['presentation']], 'direction' => 'ltr'])
                                @else
                                    <bdi>{{ $contact['value'] }}</bdi>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </address>
            </section>
            @break
        @case('social_links')
        @case('legal_links')
            <nav aria-label="{{ $element['type'] === 'social_links' ? 'شبکه‌های اجتماعی' : 'پیوندهای حقوقی' }}">
                <ul class="{{ $element['type'] === 'social_links' ? 'social-links' : 'corporate-footer__legal-links' }}">
                    @foreach ($element['items'] as $link)
                        <li>@include('partials.blocks.site-footer.link', ['link' => $link, 'direction' => 'auto', 'iconOnly' => $element['type'] === 'social_links' && filled($link['icon'] ?? null)])</li>
                    @endforeach
                </ul>
            </nav>
            @break
        @case('rich_text')
            <section class="corporate-footer__about">
                @if ($element['title'])
                    @include('partials.blocks._heading', ['title' => $element['title'], 'tag' => $heading])
                @endif
                <div class="block-rich-text">{{ $element['html'] }}</div>
            </section>
            @break
        @case('image')
            <figure class="corporate-footer__badge">
                @if ($element['presentation'])
                    @include('partials.blocks.site-footer.link', ['link' => ['label' => null, 'image' => $element['image'], 'presentation' => $element['presentation']]])
                @else
                    @include('partials.blocks.site-footer.image', ['image' => $element['image']])
                @endif
                @if ($element['title']) <figcaption>{{ $element['title'] }}</figcaption> @endif
            </figure>
            @break
        @case('map')
            <section class="corporate-footer__map" aria-label="{{ $element['title'] ?: 'موقعیت مکانی' }}">
                @if ($element['title'])
                    @include('partials.blocks._heading', ['title' => $element['title'], 'tag' => $heading])
                @endif
                <div class="corporate-footer__map-card" @if ($element['image']) data-has-image @endif>
                    @if ($element['image'])
                        @include('partials.blocks.site-footer.image', ['image' => $element['image'], 'imageClass' => 'corporate-footer__map-image'])
                    @endif
                    @if ($element['presentation'])
                        @include('partials.blocks.site-footer.link', ['link' => ['label' => 'مسیریابی', 'icon' => 'heroicon-o-map', 'presentation' => $element['presentation']], 'direction' => 'auto', 'linkClass' => 'corporate-footer__directions'])
                    @endif
                </div>
            </section>
            @break
        @case('copyright')
            <p class="corporate-footer__copyright">&copy; @if ($element['year'] !== null) <bdi>{{ $element['year'] }}</bdi> @endif {{ $element['text'] }}</p>
            @break
    @endswitch
</div>
