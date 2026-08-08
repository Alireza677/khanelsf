@extends('client.layout')

@section('title', $project['title'].' | پرتال مشتریان')

@section('content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">جزئیات پروژه</p><h1>{{ $project['title'] }}</h1></div>
        <div class="portal-actions">
            <form method="GET" action="{{ route('client.projects.show', ['project' => $project['id']]) }}" class="portal-field">
                <input type="hidden" name="customer" value="{{ $portalCustomer->id }}">
                <label for="month">ماه</label>
                <input id="month" type="month" name="month" value="{{ $summary['month'] }}" min="2000-01" max="2100-12" onchange="this.form.submit()">
            </form>
            <a class="portal-button portal-button--secondary" href="{{ route('client.projects.index', ['customer' => $portalCustomer->id]) }}">بازگشت به پروژه‌ها</a>
        </div>
    </div>

    <div class="portal-stack">
        <x-client.card title="اطلاعات پروژه">
            @if ($project['description'])<p class="portal-project-description">{{ $project['description'] }}</p>@endif
            <div class="portal-info-grid">
                <div class="portal-info-item"><small>وضعیت</small><span class="portal-badge">{{ $project['status_label'] }}</span></div>
                <div class="portal-info-item"><small>نوع پروژه</small><strong>{{ $project['type'] ?: '—' }}</strong></div>
                <div class="portal-info-item"><small>تاریخ شروع</small><strong>{{ $project['start_date'] ?: '—' }}</strong></div>
                <div class="portal-info-item"><small>تاریخ پایان</small><strong>{{ $project['end_date'] ?: '—' }}</strong></div>
            </div>
            <div class="portal-progress portal-progress--detail">
                <div><span>پیشرفت پروژه</span><strong>{{ $project['progress'] }}٪</strong></div>
                <progress value="{{ $project['progress'] }}" max="100">{{ $project['progress'] }}٪</progress>
            </div>
        </x-client.card>

        @if ($project['monthly_hour_limit_minutes'] !== null)
            <x-client.card title="خلاصه زمان ماهانه">
                <div class="portal-time-summary">
                    <div><small>سهم ماهانه</small><strong>{{ $summary['allocated'] }}</strong></div>
                    <div><small>زمان ثبت‌شده</small><strong>{{ $summary['used'] }}</strong></div>
                    @if ($summary['is_exceeded'])
                        <div class="is-over"><small>مازاد</small><strong>{{ $summary['overage'] }}</strong></div>
                    @else
                        <div><small>زمان باقی‌مانده</small><strong>{{ $summary['remaining'] }}</strong></div>
                    @endif
                    <div><small>مصرف</small><strong>{{ $summary['usage_percentage'] }}٪</strong></div>
                </div>
                <p class="portal-privacy-note">مجموع زمان شامل تمام کار ثبت‌شده غیرلغوشده است؛ جزئیات فعالیت‌های داخلی و پیش‌نویس خصوصی باقی می‌ماند.</p>
            </x-client.card>
        @endif

        <x-client.card title="فعالیت‌های خدماتی">
            @if ($activities->isEmpty())
                <x-client.empty-state title="فعالیت‌ها" message="فعالیت قابل نمایشی برای این ماه ثبت نشده است." icon="reports" />
            @else
                <div class="portal-activity-list">
                    @foreach ($activities as $activity)
                        <article class="portal-activity-item">
                            <div><h3>{{ $activity['title'] }}</h3><time>{{ $activity['activity_date'] }}</time></div>
                            <strong>{{ $activity['duration'] }}</strong>
                            @if ($activity['description'])<p>{{ $activity['description'] }}</p>@endif
                        </article>
                    @endforeach
                </div>
                <div class="portal-pagination">{{ $activities->links() }}</div>
            @endif
        </x-client.card>

        <section class="portal-grid" aria-label="بخش‌های آینده پروژه">
            @foreach ([['فایل‌ها', 'فایلی برای این پروژه موجود نیست.', 'files'], ['فاکتورها', 'فاکتوری موجود نیست.', 'invoices'], ['خط زمانی', 'رویدادی ثبت نشده است.', 'empty']] as [$title, $message, $icon])
                <x-client.card><x-client.empty-state :title="$title" :message="$message" :icon="$icon" /></x-client.card>
            @endforeach
        </section>
    </div>
@endsection
