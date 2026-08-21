<section
    class="header-overlay header-cart-drawer"
    id="{{ $overlayId }}"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $overlayId }}-title"
    data-header-overlay
    hidden
>
    <button class="header-overlay__backdrop" type="button" aria-label="بستن سبد خرید" data-header-overlay-close></button>
    <div class="header-cart-drawer__panel" data-header-overlay-panel tabindex="-1">
        <header class="header-overlay__header">
            <h2 id="{{ $overlayId }}-title">سبد خرید</h2>
            <button class="header-overlay__close" type="button" aria-label="بستن سبد خرید" data-header-overlay-close>×</button>
        </header>

        <div class="header-cart-drawer__body">
            <p class="header-cart-drawer__error" role="status" data-cart-drawer-error hidden></p>
            <div class="header-cart-drawer__empty" data-cart-empty-state @if ($cart['items'] !== []) hidden @endif>
                <p><strong>سبد خرید شما خالی است.</strong></p>
                <p>هنوز محصولی به سبد خرید اضافه نکرده‌اید.</p>
                @if ($cart['shop_url'])
                    <a class="button" href="{{ $cart['shop_url'] }}">مشاهده محصولات</a>
                @endif
            </div>

            <ul class="header-cart-drawer__items" data-cart-items @if ($cart['items'] === []) hidden @endif>
                @if ($cart['items'] !== [])
                    @foreach ($cart['items'] as $item)
                        <li @class([
                            'header-cart-drawer__item',
                            'header-cart-drawer__item--without-image' => empty($item['image']),
                        ]) data-cart-item="{{ $item['product_id'] }}">
                            @if (! empty($item['image']))
                                <img src="{{ $item['image'] }}" alt="">
                            @endif
                            <div>
                                @if ($item['product_url'])
                                    <a href="{{ $item['product_url'] }}"><strong>{{ $item['title'] }}</strong></a>
                                @else
                                    <strong>{{ $item['title'] }}</strong>
                                @endif
                                <span>{{ $item['quantity'] }} × {{ number_format($item['unit_price']) }} تومان</span>
                                <small>{{ number_format($item['total']) }} تومان</small>
                            </div>
                            @if ($cart['remove_url'])
                                <form
                                    class="header-cart-drawer__remove"
                                    method="post"
                                    action="{{ $cart['remove_url'] }}"
                                    data-cart-drawer-remove
                                >
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                    <button
                                        type="submit"
                                        aria-label="حذف {{ $item['title'] }} از سبد خرید"
                                        title="حذف از سبد خرید"
                                    >
                                        <i class="icon-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>

        <footer class="header-cart-drawer__footer" data-cart-footer @if ($cart['items'] === []) hidden @endif>
            @if ($cart['items'] !== [])
                <div data-cart-subtotal-block><span>جمع کل</span><strong data-cart-subtotal>{{ number_format($cart['subtotal']) }} تومان</strong></div>
                <div class="header-cart-drawer__actions" data-cart-actions>
                    <a class="button button-secondary" href="{{ $cart['url'] }}">مشاهده سبد خرید</a>
                    @if ($cart['checkout_url'])
                        <a class="button" href="{{ $cart['checkout_url'] }}">تسویه حساب</a>
                    @endif
                </div>
            @endif
        </footer>
    </div>
</section>
