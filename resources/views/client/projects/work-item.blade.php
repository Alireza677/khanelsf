@extends('layouts.account')
@section('title', $item['title'].' | پرتال مشتریان')
@section('account-content')
<div class="portal-stack" dir="rtl">
    <a class="portal-button portal-button--secondary" href="{{ route('account.projects.show', ['project' => $projectId, 'customer' => $portalCustomer->id]) }}">بازگشت به پروژه</a>
    <x-client.card :title="$item['title']">
        <p>{{ $item['description'] }}</p><span class="portal-badge">{{ $item['status_label'] }}</span>
        @if ($item['quantity'] !== null)<p>مقدار: {{ \App\Support\ServiceDeliveryNumber::format($item['quantity']) }} {{ $item['unit'] }}</p>@endif
        <p>برنامه: {{ $item['scheduled_start'] ?: '—' }} تا {{ $item['scheduled_end'] ?: '—' }}؛ موعد: {{ $item['due'] ?: '—' }}</p>
        <p>مسئولان: {{ implode('، ', $item['assignees']) ?: '—' }}</p>
    </x-client.card>
    @if (session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())<ul role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <x-client.card title="نسخه‌های تحویل و بررسی">
        @forelse ($item['deliveries'] as $delivery)
            <article class="portal-activity-item">
                <h3>نسخه {{ $delivery['version'] }} — {{ $delivery['date'] }}</h3>
                <p>{{ $delivery['message'] }}</p>
                <p>{{ ['pending' => 'منتظر بررسی مالک مشتری', 'accepted' => 'تأییدشده', 'revision_requested' => 'اصلاح درخواست‌شده', 'not_required' => 'بدون نیاز به تأیید مشتری'][$delivery['review_state']] }}</p>
                @if ($delivery['review_reason'])<p>نظر مشتری: {{ $delivery['review_reason'] }} — {{ $delivery['review_date'] }}</p>@endif
                @foreach ($delivery['files'] as $file)
                    <p><a href="{{ route('account.delivery.download', ['project' => $projectId, 'workItem' => $item['id'], 'delivery' => $delivery['id'], 'file' => $file['id'], 'customer' => $portalCustomer->id]) }}">دریافت {{ $file['name'] }}</a></p>
                @endforeach
                @if ($delivery['can_review'])
                    <form method="POST" action="{{ route('account.delivery.review', ['project' => $projectId, 'workItem' => $item['id'], 'delivery' => $delivery['id'], 'customer' => $portalCustomer->id]) }}" class="portal-stack">
                        @csrf
                        <input type="hidden" name="lock_version" value="{{ $item['lock_version'] }}">
                        <label>نظر یا دلیل اصلاح <textarea name="reason" class="portal-input" maxlength="10000">{{ old('reason') }}</textarea></label>
                        <div class="portal-actions"><button class="portal-button" name="decision" value="accepted">تأیید این نسخه</button>
                        @if ($delivery['can_request_revision'])<button class="portal-button portal-button--secondary" name="decision" value="revision_requested">درخواست اصلاح با دلیل</button>@endif</div>
                    </form>
                @endif
            </article>
        @empty
            <p>نسخه‌ای تحویل داده نشده است.</p>
        @endforelse
    </x-client.card>
    <x-client.card title="تاریخچه خدمات">
        @if ($item['scheduled_start'] || $item['due'])<p>برنامه ثبت‌شده: {{ $item['scheduled_start'] ?: $item['due'] }}</p>@endif
        @foreach ($item['timeline'] as $event)<p>{{ $event['label'] }} — {{ $event['date'] }}</p>@endforeach
    </x-client.card>
</div>
@endsection
