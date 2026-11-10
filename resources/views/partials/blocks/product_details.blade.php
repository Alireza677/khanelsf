@php
    $data = app(\App\CMS\Blocks\Product\ProductDetailsBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $product = $context['product'] ?? null;
    $uid = 'product-details-'.($data['block_id'] ?: ($product?->id ?? 'empty'));
    $hasContent = fn ($html) => filled(preg_replace('/[\s\x{00A0}\x{200B}]+/u', '', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) || preg_match('/<(img|video|iframe)\b/i', (string) $html);
    $panels = [];
    if ($hasContent($product?->content)) $panels['description'] = 'توضیحات';
    if (count($context['specifications'] ?? [])) $panels['specifications'] = 'مشخصات فنی';
    if (count($context['globalAttributes'] ?? [])) $panels['attributes'] = 'ویژگی‌های عمومی';
    if (collect(data_get($context, 'media.documents', []))->contains(fn ($document) => filled($document['url'] ?? null))) $panels['documents'] = 'اسناد و فایل‌ها';
    if ($hasContent($data['content']['buying_guide'])) $panels['guide'] = 'راهنمای خرید';
@endphp
@if ($panels)
    <section class="product-details" dir="rtl" aria-label="اطلاعات محصول" data-product-tabs data-default-tab="{{ $data['settings']['default_tab'] }}">
        <nav class="product-details__tabs" aria-label="بخش‌های اطلاعات محصول">
            @foreach ($panels as $key => $label)<a id="{{ $uid }}-tab-{{ $key }}" href="#{{ $uid }}-{{ $key }}" data-product-tab="{{ $key }}">{{ $label }}</a>@endforeach
        </nav>
        @foreach ($panels as $key => $label)
            <section class="product-details__panel" id="{{ $uid }}-{{ $key }}" aria-labelledby="{{ $uid }}-tab-{{ $key }}" data-product-panel="{{ $key }}">
                @if ($key === 'description')
                    @include('partials.blocks.product_overview', ['data' => ['content' => ['title' => 'توضیحات محصول']]])
                @elseif ($key === 'specifications')
                    @include('partials.blocks.product_specifications', ['data' => ['content' => ['title' => 'مشخصات فنی']]])
                @elseif ($key === 'documents')
                    @include('partials.blocks.product_documents', ['data' => ['content' => ['title' => 'اسناد و فایل‌ها']]])
                @elseif ($key === 'attributes')
                    <h2>ویژگی‌های عمومی</h2>
                    <dl class="product-details__attributes">@foreach ($context['globalAttributes'] as $attribute)
                        <div><dt>{{ $attribute['name'] }}</dt><dd>{{ implode('، ', $attribute['values']) }} @if ($attribute['unit'])<span>{{ $attribute['unit'] }}</span>@endif</dd></div>
                    @endforeach</dl>
                @elseif ($key === 'guide')
                    <h2>راهنمای خرید</h2>@include('partials.blocks._rich_text', ['content' => $data['content']['buying_guide']])
                @endif
            </section>
        @endforeach
    </section>
@endif
