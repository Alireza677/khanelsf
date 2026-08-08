@extends('client.layout')

@section('title', 'داشبورد | پرتال مشتریان')

@section('content')
    <div class="portal-page-heading">
        <div>
            <p class="portal-eyebrow">نمای کلی حساب</p>
            <h1>سلام، {{ $portalUser->name }}</h1>
        </div>
        @include('client.partials.customer-selector', ['action' => route('client.dashboard')])
    </div>

    @if ($customer)
        <div class="portal-stack">
            <x-client.card title="اطلاعات مشتری">
                <div class="portal-info-grid">
                    <div class="portal-info-item"><small>نام کاربر</small><strong>{{ $portalUser->name }}</strong></div>
                    <div class="portal-info-item"><small>شماره موبایل</small><strong dir="ltr">{{ $portalUser->mobile }}</strong></div>
                    <div class="portal-info-item"><small>نام مشتری</small><strong>{{ $customer->display_name }}</strong></div>
                    <div class="portal-info-item"><small>نام شرکت</small><strong>{{ $customer->company_name ?: '—' }}</strong></div>
                    <div class="portal-info-item"><small>وضعیت حساب</small><span class="portal-badge">فعال</span></div>
                    @if ($primaryContact)
                        <div class="portal-info-item"><small>مخاطب اصلی</small><strong>{{ $primaryContact->name }}</strong></div>
                    @endif
                </div>
            </x-client.card>

            <section class="portal-grid" aria-label="آمار حساب">
                @foreach ([['پروژه‌های فعال', $dashboardStats['active_projects']], ['فعالیت‌های قابل نمایش این ماه', $dashboardStats['published_activities']], ['زمان ثبت‌شده این ماه', $dashboardStats['worked_time']], ['گزارش‌ها', '0'], ['فاکتورها', '0']] as [$label, $value])
                    <x-client.card class="portal-stat">
                        <span class="portal-stat__label">{{ $label }}</span>
                        <strong class="portal-stat__value">{{ $value }}</strong>
                    </x-client.card>
                @endforeach
            </section>

            <x-client.card title="دسترسی سریع">
                <div class="portal-actions">
                    <a class="portal-button" href="{{ route('client.projects.index', ['customer' => $customer->id]) }}">مشاهده پروژه‌ها</a>
                    <a class="portal-button portal-button--secondary" href="{{ route('client.placeholder.reports', ['customer' => $customer->id]) }}">مشاهده گزارش‌ها</a>
                    <a class="portal-button portal-button--secondary" href="{{ route('client.placeholder.invoices', ['customer' => $customer->id]) }}">مشاهده فاکتورها</a>
                    <a class="portal-button portal-button--secondary" href="{{ route('client.profile.edit', ['customer' => $customer->id]) }}">ویرایش پروفایل</a>
                </div>
            </x-client.card>
        </div>
    @else
        <x-client.empty-state title="حساب مشتری در دسترس نیست" message="ورود شما فعال است، اما هنوز به یک حساب مشتری فعال متصل نشده‌اید. لطفاً با پشتیبانی تماس بگیرید." />
    @endif
@endsection
