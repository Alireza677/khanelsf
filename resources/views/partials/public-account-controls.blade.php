@php($iconOnlyGuest = $iconOnlyGuest ?? false)
<div @class([
    'public-account-controls',
    'public-account-controls--icon-only' => $iconOnlyGuest && ! $account['authenticated'],
]) data-public-account-controls>
    @if ($account['authenticated'])
        <details class="public-account-menu">
            <summary class="public-account-menu__trigger">
                <span class="public-account-menu__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" />
                    </svg>
                </span>
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
            @if ($iconOnlyGuest)
                <a
                    class="public-account-controls__guest-icon"
                    href="{{ $account['login_url'] }}"
                    aria-label="ورود به حساب کاربری"
                    title="ورود به حساب کاربری"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                        <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" />
                    </svg>
                </a>
            @else
                <a class="public-account-controls__login" href="{{ $account['login_url'] }}">ورود</a>
                <a class="public-account-controls__register" href="{{ $account['register_url'] }}">ثبت‌نام</a>
            @endif
        </div>
    @endif
</div>
