<div class="public-account-controls" data-public-account-controls>
    @if ($account['authenticated'])
        <details class="public-account-menu">
            <summary class="public-account-menu__trigger">
                <span class="public-account-menu__icon" aria-hidden="true">👤</span>
                <span>{{ $account['name'] }}</span>
            </summary>
            <div class="public-account-menu__dropdown">
                <a href="{{ $account['account_url'] }}">حساب کاربری</a>
                <a href="{{ $account['profile_url'] }}">پروفایل من</a>
                <a href="{{ $account['orders_url'] }}">سفارش‌های من</a>
                @if ($account['has_customer_capability'])
                    <a href="{{ $account['services_url'] }}">خدمات و پروژه‌های من</a>
                @endif
                <form method="POST" action="{{ $account['logout_url'] }}">
                    @csrf
                    <button type="submit">خروج</button>
                </form>
            </div>
        </details>
    @else
        <div class="public-account-controls__guest">
            <a class="public-account-controls__login" href="{{ $account['login_url'] }}">ورود</a>
            <a class="public-account-controls__register" href="{{ $account['register_url'] }}">ثبت‌نام</a>
        </div>
    @endif
</div>
