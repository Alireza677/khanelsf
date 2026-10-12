@extends('layouts.account')

@section('title', 'خدمات و پروژه‌های من | حساب کاربری')

@section('account-content')
    <div class="portal-page-heading services-heading">
        <div><p class="portal-eyebrow">بخش خدمات حساب کاربری</p><h1>خدمات و پروژه‌های من</h1></div>
        @include('client.partials.customer-selector', ['action' => route($serviceRoutes['home'])])
    </div>

    @if ($customer)
        @php($currentCycles = $servicesDashboard['current_cycles'])
        <div class="services-dashboard">
            <section class="services-project-list" aria-labelledby="customer-projects-title">
                <div class="services-section-heading">
                    <div><span class="services-kicker">پروژه‌های مشتری</span><h2 id="customer-projects-title">پروژه‌های من</h2></div>
                    <div class="services-project-list__actions"><span class="services-project-count">{{ \App\Support\PersianDate::digits($servicesDashboard['projects']->count()) }} پروژه</span><a href="{{ route($serviceRoutes['projects'], ['customer' => $customer->id]) }}">مشاهده همه پروژه‌ها ←</a></div>
                </div>
                @if ($servicesDashboard['projects']->isEmpty())
                    <x-client.empty-state title="پروژه‌ها" message="هنوز پروژه‌ای برای شما ثبت نشده است." />
                @else
                    <div class="portal-project-grid">
                        @foreach ($servicesDashboard['projects'] as $project)
                            @include('client.projects.card', ['projectUrl' => route($serviceRoutes['project_show'], ['project' => $project['id'], 'customer' => $customer->id])])
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($servicesDashboard['has_hourly_projects'])
            <section class="services-hero-card" aria-labelledby="monthly-time-title">
                <div class="services-hero-card__heading">
                    <div><span class="services-kicker">وضعیت دوره‌های قراردادی جاری</span><h2 id="monthly-time-title">زمان مصرف‌شده پروژه‌ها در دوره جاری</h2></div>
                </div>
                <div class="services-time-layout">
                    <div class="services-donut {{ $currentCycles['has_limit'] ? '' : 'is-neutral' }}" style="--usage: {{ $currentCycles['chart_percentage'] }}" role="img" aria-label="زمان مصرف‌شده {{ $currentCycles['used_time'] }}{{ $currentCycles['has_limit'] ? '، باقی‌مانده '.$currentCycles['remaining_time'] : '، دوره جاری تعیین نشده' }}">
                        <div><strong>{{ $currentCycles['used_time'] }}</strong><span>مصرف‌شده</span></div>
                    </div>
                    <dl class="services-time-legend">
                        <div><dt><i class="is-used"></i>مصرف‌شده</dt><dd>{{ $currentCycles['used_time'] }}</dd></div>
                        @if ($currentCycles['has_limit'])
                            <div><dt><i class="is-remaining"></i>باقی‌مانده</dt><dd>{{ $currentCycles['remaining_time'] }}</dd></div>
                            <div><dt>درصد مصرف</dt><dd>{{ $currentCycles['percentage'] }}٪</dd></div>
                            <div><dt>سهم دوره‌های جاری</dt><dd>{{ $currentCycles['limit_time'] }}</dd></div>
                            @if ($currentCycles['overage_time'])<div class="is-warning"><dt>مازاد</dt><dd>{{ $currentCycles['overage_time'] }}</dd></div>@endif
                        @else
                            <div class="services-no-limit"><dt>دوره جاری</dt><dd>برای مجموعه پروژه‌ها تعیین نشده است.</dd></div>
                        @endif
                    </dl>
                </div>
            </section>
            @endif

            <section class="services-kpis" aria-label="آمار خدمات">
                @foreach ([['پروژه‌های فعال', $dashboardStats['active_projects'], '◫'], ['فعالیت‌های قابل‌نمایش این ماه', $dashboardStats['published_activities'], '✓'], $servicesDashboard['has_hourly_projects'] ? ['زمان مصرف‌شده دوره جاری', $currentCycles['used_time'], '◷'] : ['منتظر تأیید مشتری', $servicesDashboard['awaiting_approval_count'], '✓']] as [$label, $value, $icon])
                    <article class="services-kpi"><span class="services-kpi__icon">{{ $icon }}</span><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></article>
                @endforeach
            </section>

            <div class="services-content-grid services-content-grid--single">
                <main class="services-main-column">
                    <section class="services-panel" id="recent-activities">
                        <div class="services-section-heading"><div><span class="services-kicker">گزارش کار</span><h2>فعالیت‌های اخیر</h2></div></div>
                        <form class="services-filters" method="get" action="{{ route($serviceRoutes['home']) }}">
                            <input type="hidden" name="customer" value="{{ $customer->id }}">
                            <label>پروژه<select name="project"><option value="">همه پروژه‌ها</option>@foreach($servicesDashboard['projects'] as $project)<option value="{{ $project['id'] }}" @selected($activityFilters['project'] === $project['id'])>{{ $project['title'] }}</option>@endforeach</select></label>
                            <label>بازه<select name="range"><option value="current" @selected($activityFilters['range'] === 'current')>این ماه</option><option value="previous" @selected($activityFilters['range'] === 'previous')>ماه قبل</option><option value="all" @selected($activityFilters['range'] === 'all')>همه</option></select></label>
                            <button class="portal-button" type="submit">اعمال فیلتر</button>
                        </form>
                        @if ($recentActivities->isEmpty())
                            <x-client.empty-state title="فعالیت‌ها" message="هنوز فعالیت قابل‌نمایشی برای این پروژه ثبت نشده است." icon="reports" />
                        @else
                            <div class="services-activity-list">
                                @foreach ($recentActivities as $activity)
                                    <button class="services-activity-row" type="button" data-activity-open="activity-{{ $activity['id'] }}">
                                        <div><strong>{{ $activity['title'] }}</strong>@if($activity['is_overage'])<span class="portal-badge">{{ $activity['overage_label'] }}</span>@endif @if($activity['description'])<span>{{ Str::limit($activity['description'], 90) }}</span>@endif</div>
                                        <span>{{ $activity['project_title'] }}</span><time>{{ $activity['activity_date'] }}</time><span>{{ $activity['duration'] }}</span><em>{{ $activity['status_label'] }}</em>
                                    </button>
                                    <dialog class="services-activity-dialog" id="activity-{{ $activity['id'] }}" aria-labelledby="activity-title-{{ $activity['id'] }}">
                                        <form method="dialog"><button class="services-dialog-close" aria-label="بستن">×</button></form>
                                        <span class="portal-badge">{{ $activity['status_label'] }}</span>@if($activity['is_overage']) <span class="portal-badge">{{ $activity['overage_label'] }}</span>@endif<h2 id="activity-title-{{ $activity['id'] }}">{{ $activity['title'] }}</h2>
                                        <dl><div><dt>پروژه</dt><dd>{{ $activity['project_title'] }}</dd></div><div><dt>تاریخ انجام</dt><dd>{{ $activity['activity_date'] }}</dd></div><div><dt>مدت زمان</dt><dd>{{ $activity['duration'] }}</dd></div></dl>
                                        @if($activity['description'])<div class="services-dialog-description"><h3>توضیحات</h3><p>{{ $activity['description'] }}</p></div>@endif
                                    </dialog>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </main>

            </div>
        </div>
        <script>document.querySelectorAll('[data-activity-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.activityOpen)?.showModal()));</script>
    @else
        <x-client.empty-state title="حساب مشتری در دسترس نیست" message="ورود شما فعال است، اما هنوز به یک حساب مشتری فعال متصل نشده‌اید. لطفاً با پشتیبانی تماس بگیرید." />
    @endif
@endsection
