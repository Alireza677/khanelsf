@extends('layouts.account')

@section('account-content')
<section class="account-orders">
    <header class="account-orders__header"><div><p class="public-account-home__eyebrow">حساب کاربری</p><h1>فاکتورها</h1></div></header>
    @if($invoices->isEmpty())
        <div class="account-orders__empty">هنوز فاکتور صادرشده‌ای وجود ندارد.</div>
    @else
        <div class="account-orders__list">
            @foreach($invoices as $invoice)
                <article class="account-order-card">
                    <div><strong dir="ltr">{{ $invoice->invoice_number }}</strong><p>{{ \App\Support\PersianDate::date($invoice->period_start) }} تا {{ \App\Support\PersianDate::date($invoice->period_end) }}</p></div>
                    <div>{{ number_format((float) $invoice->total_amount) }} {{ $invoice->currency }}</div>
                    <a class="button button-secondary" href="{{ route('account.invoices.show', $invoice) }}">مشاهده جزئیات</a>
                </article>
            @endforeach
        </div>
        <div class="account-orders__pagination">{{ $invoices->links() }}</div>
    @endif
</section>
@endsection
