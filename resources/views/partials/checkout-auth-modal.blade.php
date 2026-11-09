@if (! app(\App\Services\PublicAccountNavigation::class)->present()['authenticated'] && app(\App\Services\ModuleService::class)->shopEnabled())
    <section class="header-overlay checkout-auth" id="checkout-auth" role="dialog" aria-modal="true" aria-labelledby="checkout-auth-title" aria-describedby="checkout-auth-description" data-header-overlay data-checkout-auth data-checkout-url="{{ route('checkout.create') }}" hidden dir="rtl">
        <button class="header-overlay__backdrop" type="button" aria-label="بستن ورود به حساب" data-header-overlay-close tabindex="-1"></button>
        <div class="checkout-auth__panel" data-header-overlay-panel tabindex="-1">
            <header class="header-overlay__header">
                <div><p class="checkout-auth__eyebrow">ادامه خرید</p><h2 id="checkout-auth-title">وارد حساب خود شوید</h2></div>
                <button class="header-overlay__close" type="button" aria-label="بستن ورود به حساب" data-header-overlay-close>×</button>
            </header>
            <div class="checkout-auth__body">
                <p id="checkout-auth-description">برای ثبت و پیگیری سفارش وارد شوید یا حساب بسازید. سبد خرید شما حفظ می‌شود.</p>
                <nav class="checkout-auth__modes" aria-label="ورود یا ثبت‌نام">
                    <a href="{{ route('login', ['continue' => 'checkout']) }}" data-auth-mode="login" aria-current="page">ورود</a>
                    <a href="{{ route('register', ['continue' => 'checkout']) }}" data-auth-mode="register">ثبت‌نام</a>
                </nav>
                <div class="checkout-auth__errors" data-auth-errors role="alert" tabindex="-1" hidden></div>
                @foreach (['login' => 'ورود و ادامه خرید', 'register' => 'ایجاد حساب و ادامه خرید'] as $mode => $buttonLabel)
                    <form method="post" action="{{ route('client.'.$mode.'.store') }}" class="portal-stack" data-public-auth-form="{{ $mode }}" @if ($mode === 'register') hidden @endif aria-label="{{ $mode === 'login' ? 'ورود به حساب' : 'ایجاد حساب' }}">
                        @csrf
                        <input type="hidden" name="continue" value="checkout">
                        @include('client.auth.partials.fields', ['mode' => $mode, 'prefix' => 'checkout-'.$mode, 'modal' => true])
                        <button class="button checkout-auth__submit" type="submit" data-auth-submit>{{ $buttonLabel }}</button>
                    </form>
                @endforeach
                <p class="checkout-auth__status" data-auth-status role="status" aria-live="polite"></p>
                <p class="checkout-auth__fallback"><a href="{{ route('login', ['continue' => 'checkout']) }}" data-auth-fallback>باز کردن صفحه ورود</a></p>
            </div>
        </div>
    </section>
@endif
