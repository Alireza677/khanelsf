<nav class="public-account-navigation" aria-label="ناوبری حساب کاربری">
    <a href="{{ $account['account_url'] }}" @class(['is-active' => request()->routeIs('account.home')])>حساب کاربری</a>
    <a href="{{ $account['profile_url'] }}" @class(['is-active' => request()->routeIs('account.profile.*')])>پروفایل من</a>
    <a href="{{ $account['orders_url'] }}" @class(['is-active' => request()->routeIs('account.orders.*')])>سفارش‌های من</a>
    @if ($account['has_customer_capability'])
        <a href="{{ $account['services_url'] }}" @class(['is-active' => request()->routeIs('account.services.*', 'account.projects.*', 'client.dashboard', 'client.projects.*')])>خدمات و پروژه‌های من</a>
    @endif
    <form method="POST" action="{{ $account['logout_url'] }}">
        @csrf
        <button type="submit">خروج</button>
    </form>
</nav>
