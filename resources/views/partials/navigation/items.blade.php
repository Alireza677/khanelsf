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
            <ul>
                @include('partials.navigation.items', [
                    'items' => $item['children'],
                ])
            </ul>
        @endif
    </li>
@endforeach
