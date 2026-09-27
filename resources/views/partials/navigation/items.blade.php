@foreach ($items as $item)
    <li @class(['has-children' => $item['children'] !== []])>
        <a
            href="{{ $item['url'] }}"
            target="{{ $item['target'] }}"
            @if ($item['target'] === '_blank') rel="noopener noreferrer" @endif
        >
            {{ $item['label'] }}
        </a>

        @if ($item['children'] !== [])
            @php($submenuId = ($submenuIdPrefix ?? 'navigation-submenu').'-'.$loop->index)
            @if ($mobileSubmenus ?? false)
                <button
                    type="button"
                    class="industrial-header__submenu-toggle"
                    data-industrial-submenu-toggle
                    aria-label="باز و بسته کردن زیرمنوی {{ $item['label'] }}"
                    aria-expanded="false"
                    aria-controls="{{ $submenuId }}"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
            @endif
            <ul @if ($mobileSubmenus ?? false) id="{{ $submenuId }}" @endif>
                @include('partials.navigation.items', [
                    'items' => $item['children'],
                    'mobileSubmenus' => $mobileSubmenus ?? false,
                    'submenuIdPrefix' => $submenuId,
                ])
            </ul>
        @endif
    </li>
@endforeach
