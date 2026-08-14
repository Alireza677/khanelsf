@extends('layouts.account')

@section('account-content')
    <section class="public-account-home">
        <header class="public-account-home__header">
            <div>
                <p class="public-account-home__eyebrow">فضای شخصی شما</p>
                <div class="public-account-home__title">
                    <h1>حساب کاربری</h1>
                    @if ($account['status'] === 'active')
                        <span class="public-account-status-badge">{{ $account['status_label'] }}</span>
                    @endif
                </div>
            </div>
            <dl class="public-account-home__identity">
                <div><dt>نام</dt><dd>{{ $account['name'] }}</dd></div>
                <div><dt>شماره موبایل</dt><dd dir="ltr">{{ $account['mobile'] }}</dd></div>
            </dl>
        </header>

        <div class="public-account-home__sections">
            <a class="public-account-card" href="{{ $account['profile_url'] }}">
                <span class="public-account-card__icon" aria-hidden="true">👤</span>
                <span><strong>پروفایل من</strong><small>مشاهده و ویرایش اطلاعات حساب</small></span>
            </a>

            <a class="public-account-card" href="{{ $account['orders_url'] }}">
                <span class="public-account-card__icon" aria-hidden="true">▤</span>
                <span>
                    <strong>سفارش‌های من</strong>
                    <small>{{ $hasOrders ? 'مشاهده سوابق سفارش‌ها' : 'هنوز سفارشی ثبت نکرده‌اید.' }}</small>
                </span>
            </a>

            @if ($account['has_customer_capability'])
                <a class="public-account-card" href="{{ $account['services_url'] }}">
                    <span class="public-account-card__icon" aria-hidden="true">◫</span>
                    <span><strong>خدمات و پروژه‌های من</strong><small>مشاهده پروژه‌ها، فعالیت‌ها و وضعیت زمان</small></span>
                </a>
            @endif
        </div>
    </section>
@endsection
