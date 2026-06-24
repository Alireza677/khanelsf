@extends('layouts.app')

@section('content')
    <section class="cart-page">
        <h1>سبد خرید</h1>

        @if (session('success'))
            <p class="form-status" role="status">{{ session('success') }}</p>
        @endif

        @if ($cartItems->isEmpty())
            <p class="empty-state">سبد خرید شما خالی است.</p>
            <p><a class="button" href="{{ route('shop.index') }}">مشاهده محصولات</a></p>
        @else
            <form method="post" action="{{ route('cart.update') }}">
                @csrf
                @method('PATCH')

                <div class="cart-table">
                    @foreach ($cartItems as $item)
                        <div class="cart-row">
                            <div>
                                <strong><a href="{{ route('shop.show', $item['slug']) }}">{{ $item['title'] }}</a></strong>
                                @if (! empty($item['sku']))
                                    <span>شناسه محصول: {{ $item['sku'] }}</span>
                                @endif
                            </div>
                            <div>{{ number_format($item['unit_price']) }} تومان</div>
                            <div>
                                <label for="quantity-{{ $item['product_id'] }}">تعداد</label>
                                <input id="quantity-{{ $item['product_id'] }}" name="quantities[{{ $item['product_id'] }}]" type="number" min="0" max="99" value="{{ $item['quantity'] }}">
                            </div>
                            <div>{{ number_format($item['total']) }} تومان</div>
                            <div>
                                <button class="button button-secondary" type="submit" form="remove-{{ $item['product_id'] }}">حذف</button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="cart-summary">
                    <strong>جمع کل: {{ number_format($subtotal) }} تومان</strong>
                    <div class="block-actions">
                        <button class="button button-secondary" type="submit">به‌روزرسانی سبد</button>
                        <a class="button" href="{{ route('checkout.create') }}">تسویه حساب</a>
                    </div>
                </div>
            </form>

            @foreach ($cartItems as $item)
                <form id="remove-{{ $item['product_id'] }}" method="post" action="{{ route('cart.remove') }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                </form>
            @endforeach
        @endif
    </section>
@endsection
