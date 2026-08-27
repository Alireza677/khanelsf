@extends('layouts.account')

@section('account-content')
<section class="account-orders account-order-detail">
    <header class="account-orders__header"><div><p class="public-account-home__eyebrow">فاکتور</p><h1 dir="ltr">{{ $invoice->invoice_number }}</h1></div><a class="button button-secondary" href="{{ route('account.invoices.index') }}">بازگشت</a></header>
    @if($invoice->pdf_status === 'ready')<p><a class="button" href="{{ route('account.invoices.download',$invoice) }}">دانلود فاکتور PDF</a></p>@else<p>فاکتور در حال آماده‌سازی است.</p>@endif
    <div class="account-order-detail__summary">
        <div><span>دوره</span><strong>{{ \App\Support\PersianDate::date($invoice->period_start) }} تا {{ \App\Support\PersianDate::date($invoice->period_end) }}</strong></div>
        <div><span>تاریخ صدور</span><strong>{{ \App\Support\PersianDate::date($invoice->issued_at) }}</strong></div>
        <div><span>سررسید</span><strong>{{ \App\Support\PersianDate::date($invoice->due_at) ?? '—' }}</strong></div>
        <div><span>وضعیت</span><strong>{{ $invoice->status->label() }}</strong></div>
    </div>
    <div class="account-order-detail__items">
        @foreach($invoice->items as $item)
            <div class="account-order-detail__item"><div><strong>{{ $item->activity_title_snapshot }}</strong><p>{{ $item->project_title_snapshot }} · {{ $item->service_name_snapshot }}</p><p>{{ app(\App\Services\InvoiceItemPresenter::class)->commercialLine($item) }}</p></div><strong>{{ number_format((float) $item->total_amount) }} {{ $item->currency }}</strong></div>
        @endforeach
    </div>
    <div class="account-order-detail__summary">
        <div><span>جمع</span><strong>{{ number_format((float) $invoice->subtotal) }}</strong></div><div><span>تخفیف</span><strong>{{ number_format((float) $invoice->discount_amount) }}</strong></div><div><span>مالیات</span><strong>{{ number_format((float) $invoice->tax_amount) }}</strong></div><div><span>مبلغ نهایی</span><strong>{{ number_format((float) $invoice->total_amount) }} {{ $invoice->currency }}</strong></div>
    </div>
</section>
@endsection
