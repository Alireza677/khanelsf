@php
    $settings = app(\App\Services\SettingsService::class);

    $siteName = $settings->siteName();
    $contactEmail = $settings->contactEmail();
    $contactPhone = $settings->contactPhone();
    $contactAddress = $settings->contactAddress();
    $footerText = $settings->footerText();
    $socialLinks = $settings->socialLinks();
@endphp

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <section>
                <h2>{{ $siteName }}</h2>

                @if ($footerText)
                    <p>{{ $footerText }}</p>
                @endif

                @if ($contactEmail || $contactPhone || $contactAddress)
                    <address>
                        @if ($contactEmail)
                            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                        @endif

                        @if ($contactPhone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}">{{ $contactPhone }}</a>
                        @endif

                        @if ($contactAddress)
                            <span>{{ $contactAddress }}</span>
                        @endif
                    </address>
                @endif
            </section>

            <x-navigation placement="footer" variant="footer" />

            @if ($socialLinks)
                <nav aria-label="Social links">
                    <h3>Social</h3>
                    <ul class="social-links">
                        @foreach ($socialLinks as $link)
                            <li>
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer">
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>

        <div class="footer-bottom">
            &copy; {{ now()->year }} {{ $siteName }}
        </div>
    </div>
</footer>
