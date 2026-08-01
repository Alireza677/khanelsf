@php
    $data = app(\App\CMS\Blocks\Product\ProductSpecificationsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $specifications = collect($context['specifications'] ?? []);
    $settings = $data['settings'];
@endphp

@if ($specifications->isNotEmpty())
    <section class="content-block product-specifications product-specifications--{{ $settings['layout'] }}" dir="rtl">
        @include('partials.blocks._heading', ['title' => $data['content']['title'] ?: 'مشخصات محصول', 'tag' => data_get($data, 'settings.heading_tag', 'h2')])

        @foreach ($specifications->groupBy(fn ($specification) => $specification->group_name ?: '') as $group => $items)
            @if ($settings['show_group'] && filled($group))
                <h3>{{ $group }}</h3>
            @endif

            <dl class="product-specifications__list">
                @foreach ($items as $specification)
                    <div>
                        <dt>{{ $specification->label ?: $specification->key }}</dt>
                        <dd>
                            {{ $specification->value }}
                            @if ($settings['show_unit'] && filled($specification->unit))
                                <span>{{ $specification->unit }}</span>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endforeach
    </section>
@endif
