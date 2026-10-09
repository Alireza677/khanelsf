@php
    $content = $pricing['content'];
    $settings = $pricing['settings'];
    $cards = $pricing['cards'];
@endphp

@if ($cards !== [])
    <section class="content-block block-pricing-cards" dir="rtl">
        @if ($content['title'] || $content['description'])
            <div class="block-pricing-cards__heading">
                @if ($content['title'])
                    @include('partials.blocks._heading', ['title' => $content['title'], 'tag' => $settings['heading_tag']])
                @endif
                @include('partials.blocks._rich_text', ['content' => $content['description']])
            </div>
        @endif

        <div class="block-pricing-cards__grid">
            @foreach ($cards as $card)
                <article @class(['block-pricing-cards__card', 'block-pricing-cards__card--featured' => $card['featured']])>
                    @if ($card['badge'])
                        <span class="block-pricing-cards__badge">{{ $card['badge'] }}</span>
                    @endif
                    <h3 class="block-pricing-cards__title">{{ $card['title'] }}</h3>
                    @if ($card['description'])
                        <p class="block-pricing-cards__description">{{ $card['description'] }}</p>
                    @endif

                    <div class="block-pricing-cards__price">
                        @if ($card['formatted_amount'] !== null)
                            <span class="block-pricing-cards__amount" dir="ltr">{{ $card['formatted_amount'] }}</span>
                            <span class="block-pricing-cards__currency">{{ $card['currency'] }}</span>
                            @if ($card['period_text'])
                                <span class="block-pricing-cards__period">{{ $card['period_text'] }}</span>
                            @endif
                        @else
                            <span class="block-pricing-cards__enquiry">استعلام قیمت</span>
                        @endif
                    </div>

                    @if ($card['outputs'])
                        <p class="block-pricing-cards__outputs">{{ $card['outputs'] }}</p>
                    @endif
                    @if ($card['features'] !== [])
                        <ul class="block-pricing-cards__features">
                            @foreach ($card['features'] as $feature)
                                <li>{{ $feature['text'] }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($card['button_label'] && $card['presentation'])
                        <div class="block-pricing-cards__action">
                            @include('partials.actions.render', [
                                'label' => $card['button_label'],
                                'class' => 'button block-pricing-cards__button',
                                'presentation' => $card['presentation'],
                            ])
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif
