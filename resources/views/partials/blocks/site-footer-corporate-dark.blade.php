<footer class="site-footer corporate-footer" data-site-footer>
    <div class="container corporate-footer__inner">
        @if ($footer['menu_columns'] !== [] || $footer['contacts'] !== [])
            <div @class([
                'corporate-footer__primary',
                'corporate-footer__primary--adaptive' => count($footer['menu_columns']) + ($footer['contacts'] !== [] ? 1 : 0) !== 4,
            ])>
                @foreach ($footer['menu_columns'] as $column)
                    <nav class="corporate-footer__menu" aria-label="{{ $column['title'] ?: 'پیوندهای فوتر' }}">
                        @if ($column['title'])
                            <h2>{{ $column['title'] }}</h2>
                        @endif
                        <ul>
                            @foreach ($column['items'] as $item)
                                <li>
                                    <a
                                        href="{{ $item['url'] }}"
                                        @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif
                                    >{{ $item['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endforeach

                @if ($footer['contacts'] !== [])
                    <section class="corporate-footer__contact">
                        <h2>{{ $footer['contact_title'] }}</h2>
                        <address>
                            @foreach ($footer['contacts'] as $contact)
                                <div class="corporate-footer__contact-item">
                                    <span class="corporate-footer__contact-icon" aria-hidden="true">
                                        @switch($contact['type'])
                                            @case('phone') <x-heroicon-o-phone /> @break
                                            @case('mobile') <x-heroicon-o-device-phone-mobile /> @break
                                            @case('email') <x-heroicon-o-envelope /> @break
                                            @case('address') <x-heroicon-o-map-pin /> @break
                                            @default <x-heroicon-o-clock />
                                        @endswitch
                                    </span>
                                    <span>
                                        <span class="corporate-footer__contact-label">{{ $contact['label'] }}</span>
                                        @if ($contact['href'])
                                            <a href="{{ $contact['href'] }}">{{ $contact['value'] }}</a>
                                        @else
                                            <span>{{ $contact['value'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </address>
                    </section>
                @endif
            </div>
        @endif

        @if ($footer['about_text'] || $footer['badges'] !== [])
            <div @class([
                'corporate-footer__secondary',
                'corporate-footer__secondary--about-only' => $footer['badges'] === [],
                'corporate-footer__secondary--badges-only' => ! $footer['about_text'],
            ])>
                @if ($footer['about_text'])
                    <section class="corporate-footer__about">
                        <h2>{{ $footer['about_title'] }}</h2>
                        <p>{{ $footer['about_text'] }}</p>
                    </section>
                @endif

                @if ($footer['badges'] !== [])
                    <section class="corporate-footer__badges">
                        <h2>{{ $footer['badges_title'] }}</h2>
                        <div class="corporate-footer__badge-list">
                            @foreach ($footer['badges'] as $badge)
                                @if ($badge['url'])
                                    <a href="{{ $badge['url'] }}" class="corporate-footer__badge" @if (str_starts_with($badge['url'], 'http')) target="_blank" rel="noopener noreferrer" @endif>
                                        <img src="{{ $badge['image_url'] }}" alt="{{ $badge['alt'] }}">
                                        @if ($badge['title']) <span>{{ $badge['title'] }}</span> @endif
                                    </a>
                                @else
                                    <div class="corporate-footer__badge">
                                        <img src="{{ $badge['image_url'] }}" alt="{{ $badge['alt'] }}">
                                        @if ($badge['title']) <span>{{ $badge['title'] }}</span> @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        @endif

        <div class="corporate-footer__bottom">
            <p>&copy; {{ $footer['year'] }} {{ $footer['site_name'] }}</p>
            @if ($footer['legal_links'] !== [])
                <nav aria-label="پیوندهای حقوقی">
                    @foreach ($footer['legal_links'] as $link)
                        @include('partials.actions.render', [
                            'label' => $link['label'],
                            'class' => 'corporate-footer__legal-link',
                            'presentation' => $link['presentation'],
                        ])
                    @endforeach
                </nav>
            @endif
        </div>
    </div>
</footer>
