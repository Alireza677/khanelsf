@extends('layouts.account')

@section('title', 'خدمات و پروژه‌های من | حساب کاربری')

@section('account-content')
    <div class="portal-page-heading services-heading">
        <div><p class="portal-eyebrow">بخش خدمات حساب کاربری</p><h1>خدمات و پروژه‌های من</h1></div>
        @include('client.partials.customer-selector', ['action' => route($serviceRoutes['home'])])
    </div>

    @if ($customer)
        @php($monthly = $servicesDashboard['monthly'])
        <div class="services-dashboard">
            <section class="services-hero-card" aria-labelledby="monthly-time-title">
                <div class="services-hero-card__heading">
                    <div><span class="services-kicker">وضعیت خدمات این ماه</span><h2 id="monthly-time-title">زمان مصرف‌شده پروژه در این ماه</h2></div>
                    <span class="services-month"><x-persian-date :value="now()" format="month-year" /></span>
                </div>
                <div class="services-time-layout">
                    <div class="services-donut {{ $monthly['has_limit'] ? '' : 'is-neutral' }}" style="--usage: {{ $monthly['chart_percentage'] }}" role="img" aria-label="زمان مصرف‌شده {{ $monthly['used_time'] }}{{ $monthly['has_limit'] ? '، باقی‌مانده '.$monthly['remaining_time'] : '، سقف ماهانه تعیین نشده' }}">
                        <div><strong>{{ $monthly['used_time'] }}</strong><span>مصرف‌شده</span></div>
                    </div>
                    <dl class="services-time-legend">
                        <div><dt><i class="is-used"></i>مصرف‌شده</dt><dd>{{ $monthly['used_time'] }}</dd></div>
                        @if ($monthly['has_limit'])
                            <div><dt><i class="is-remaining"></i>باقی‌مانده</dt><dd>{{ $monthly['remaining_time'] }}</dd></div>
                            <div><dt>درصد مصرف</dt><dd>{{ $monthly['percentage'] }}٪</dd></div>
                            <div><dt>سقف ماهانه</dt><dd>{{ $monthly['limit_time'] }}</dd></div>
                            @if ($monthly['overage_time'])<div class="is-warning"><dt>مازاد</dt><dd>{{ $monthly['overage_time'] }}</dd></div>@endif
                        @else
                            <div class="services-no-limit"><dt>سقف ماهانه</dt><dd>برای مجموعه پروژه‌ها تعیین نشده است.</dd></div>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="services-kpis" aria-label="آمار خدمات">
                @foreach ([['پروژه‌های فعال', $dashboardStats['active_projects'], '◫'], ['فعالیت‌های قابل‌نمایش این ماه', $dashboardStats['published_activities'], '✓'], ['زمان مصرف‌شده این ماه', $monthly['used_time'], '◷']] as [$label, $value, $icon])
                    <article class="services-kpi"><span class="services-kpi__icon">{{ $icon }}</span><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></article>
                @endforeach
            </section>

            <div class="services-content-grid">
                <main class="services-main-column">
                    <section class="services-panel">
                        <div class="services-section-heading"><div><span class="services-kicker">نمای کلی</span><h2>پروژه‌های من</h2></div><a href="{{ route($serviceRoutes['projects'], ['customer' => $customer->id]) }}">مشاهده همه پروژه‌ها ←</a></div>
                        @if ($servicesDashboard['projects']->isEmpty())
                            <x-client.empty-state title="پروژه‌ها" message="هنوز پروژه‌ای برای شما ثبت نشده است." />
                        @else
                            <div class="services-project-grid">
                                @foreach ($servicesDashboard['projects']->take(4) as $project)
                                    <a class="services-project-card" href="{{ route($serviceRoutes['project_show'], ['project' => $project['id'], 'customer' => $customer->id]) }}">
                                        <div><span class="portal-badge">{{ $project['status_label'] }}</span><small>{{ $project['type'] ?: 'پروژه خدماتی' }}</small></div>
                                        <h3>{{ $project['title'] }}</h3>
                                        <dl><div><dt>مصرف این ماه</dt><dd>{{ $project['used_time'] }}</dd></div><div><dt>سقف ماهانه</dt><dd>{{ $project['limit_time'] ?? 'تعیین نشده' }}</dd></div></dl>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </section>

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
                                        <div><strong>{{ $activity['title'] }}</strong>@if($activity['description'])<span>{{ Str::limit($activity['description'], 90) }}</span>@endif</div>
                                        <span>{{ $activity['project_title'] }}</span><time>{{ $activity['activity_date'] }}</time><span>{{ $activity['duration'] }}</span><em>{{ $activity['status_label'] }}</em>
                                    </button>
                                    <dialog class="services-activity-dialog" id="activity-{{ $activity['id'] }}" aria-labelledby="activity-title-{{ $activity['id'] }}">
                                        <form method="dialog"><button class="services-dialog-close" aria-label="بستن">×</button></form>
                                        <span class="portal-badge">{{ $activity['status_label'] }}</span><h2 id="activity-title-{{ $activity['id'] }}">{{ $activity['title'] }}</h2>
                                        <dl><div><dt>پروژه</dt><dd>{{ $activity['project_title'] }}</dd></div><div><dt>تاریخ انجام</dt><dd>{{ $activity['activity_date'] }}</dd></div><div><dt>مدت زمان</dt><dd>{{ $activity['duration'] }}</dd></div></dl>
                                        @if($activity['description'])<div class="services-dialog-description"><h3>توضیحات</h3><p>{{ $activity['description'] }}</p></div>@endif
                                    </dialog>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </main>

                <aside class="services-sidebar">
                    <section class="services-customer-card"><span class="services-avatar">{{ Str::upper(Str::substr($portalUser->name, 0, 1)) }}</span><div><small>حساب مشتری</small><h2>{{ $customer->display_name }}</h2>@if($customer->company_name)<p>{{ $customer->company_name }}</p>@endif<span class="portal-badge">فعال</span></div></section>
                    <section class="services-panel"><h2>دسترسی سریع</h2><div class="services-quick-links"><a href="{{ route($serviceRoutes['projects'], ['customer' => $customer->id]) }}">مشاهده همه پروژه‌ها <span>←</span></a><a href="{{ route('account.profile.edit') }}">ویرایش پروفایل <span>←</span></a></div></section>
                </aside>
            </div>
        </div>
        <script>document.querySelectorAll('[data-activity-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.activityOpen)?.showModal()));</script>
    @else
        <x-client.empty-state title="حساب مشتری در دسترس نیست" message="ورود شما فعال است، اما هنوز به یک حساب مشتری فعال متصل نشده‌اید. لطفاً با پشتیبانی تماس بگیرید." />
    @endif
@endsection
