@extends('layouts.account')

@section('title', $project['title'].' | پرتال مشتریان')

@section('account-content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">جزئیات پروژه</p><h1>{{ $project['title'] }}</h1></div>
        <div class="portal-actions">
            <form method="GET" action="{{ route($serviceRoutes['project'], ['project' => $project['id']]) }}" class="portal-field portal-month-filter" data-jalali-month-filter dir="rtl">
                <input type="hidden" name="customer" value="{{ $portalCustomer->id }}">
                <input type="hidden" name="month" value="{{ $summary['month'] }}" data-jalali-month-value>
                <span>ماه</span>
                <div class="portal-month-filter__selects">
                    <label class="sr-only" for="jalali-month">ماه شمسی</label>
                    <select id="jalali-month" class="portal-select" data-jalali-month>
                        @foreach (['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'] as $number => $name)
                            <option value="{{ sprintf('%02d', $number + 1) }}" @selected($summary['jalali_month'] === $number + 1)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <label class="sr-only" for="jalali-year">سال شمسی</label>
                    <select id="jalali-year" class="portal-select" data-jalali-year>
                        @foreach (range(1500, 1350) as $year)
                            <option value="{{ $year }}" @selected($summary['jalali_year'] === $year)>{{ \App\Support\PersianDate::digits($year) }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            <a class="portal-button portal-button--secondary" href="{{ route($serviceRoutes['projects'], ['customer' => $portalCustomer->id]) }}">بازگشت به پروژه‌ها</a>
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

        @if ($currentCycleSummary)
            <x-client.card title="مصرف دوره جاری">
                <div class="portal-time-summary">
                    <div><small>سهم دوره</small><strong>{{ $currentCycleSummary['allocated'] }}</strong></div>
                    <div><small>زمان ثبت‌شده</small><strong>{{ $currentCycleSummary['used'] }}</strong></div>
                    <div><small>زمان باقی‌مانده</small><strong>{{ $currentCycleSummary['remaining'] }}</strong></div>
                    <div><small>مصرف</small><strong>{{ $currentCycleSummary['percentage'] }}٪</strong></div>
                </div>
                <p class="portal-privacy-note">دوره جاری: {{ $currentCycleSummary['starts_at'] }} تا {{ $currentCycleSummary['ends_at'] }}</p>
                <p class="portal-privacy-note">مجموع زمان شامل تمام کار ثبت‌شده غیرلغوشده است؛ جزئیات فعالیت‌های داخلی و پیش‌نویس خصوصی باقی می‌ماند.</p>
            </x-client.card>
        @elseif ($project['monthly_hour_limit_minutes'] !== null)
            <x-client.card title="مصرف دوره جاری">
                <p class="portal-privacy-note">برای تاریخ امروز دوره قراردادی جاری ثبت نشده است.</p>
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

    </div>
@endsection
