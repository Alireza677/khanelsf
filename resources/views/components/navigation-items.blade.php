@foreach ($items as $item)
    @php
        $hasVisibleChildren = $depth < $maxDepth && $item->children->isNotEmpty();
    @endphp

    <li @class(['has-children' => $variant === 'header' && $hasVisibleChildren])>
        <a
            href="{{ $item->resolvedUrl() ?: '#' }}"
            target="{{ $item->target }}"
            @if ($item->target === '_blank') rel="noopener noreferrer" @endif
        >
            {{ $item->title }}
        </a>

        @if ($hasVisibleChildren)
            <ul>
                @include('components.navigation-items', [
                    'items' => $item->children,
                    'depth' => $depth + 1,
                    'maxDepth' => $maxDepth,
                    'variant' => $variant,
                ])
            </ul>
        @endif
    </li>
@endforeach
