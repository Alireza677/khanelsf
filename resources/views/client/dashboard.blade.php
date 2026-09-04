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
                    <div class="services-project-table" role="table" aria-label="فهرست پروژه‌ها">
                        <div class="services-project-table__head" role="row">
                            <span role="columnheader">نام پروژه</span><span role="columnheader">نوع پروژه</span><span role="columnheader">وضعیت پروژه</span><span role="columnheader">سهم دوره جاری</span><span role="columnheader">مصرف دوره جاری</span><span role="columnheader">وضعیت پرداخت</span>
                        </div>
                        <div class="services-project-table__body">
                            @foreach ($servicesDashboard['projects'] as $project)
                                <a class="services-project-row services-project-row--{{ $project['timeline']['state'] }}" role="row" href="{{ route($serviceRoutes['project_show'], ['project' => $project['id'], 'customer' => $customer->id]) }}" aria-label="مشاهده پروژه {{ $project['title'] }}">
                                    <div class="services-project-row__summary">
                                        <div class="services-project-name" role="cell"><strong>{{ $project['title'] }}</strong><small>مشاهده جزئیات پروژه ←</small></div>
                                        <div role="cell"><small>نوع پروژه</small><span>{{ $project['type'] ?: 'پروژه خدماتی' }}</span></div>
                                        <div role="cell"><small>وضعیت پروژه</small><span class="portal-badge">{{ $project['status_label'] }}</span></div>
                                        <div role="cell"><small>سهم دوره جاری</small><strong>{{ $project['limit_time'] ?? 'دوره جاری ندارد' }}</strong></div>
                                        <div role="cell"><small>مصرف دوره جاری</small><strong>{{ $project['used_time'] }}</strong>@if($project['cycle_start'])<small>دوره جاری: {{ $project['cycle_start'] }} تا {{ $project['cycle_end'] }}</small>@endif</div>
                                        <div role="cell"><small>وضعیت پرداخت</small><span class="services-payment services-payment--{{ $project['payment']['state'] }}">{{ $project['payment']['label'] }}</span></div>
                                    </div>
                                    <div class="services-project-timeline">
                                        <div class="services-timeline-date"><small>{{ $project['timeline']['start_label'] }}</small><strong>{{ $project['timeline']['start_date'] ?: 'تعیین نشده' }}</strong></div>
                                        <div class="services-timeline-track" style="--timeline-progress: {{ $project['timeline']['percentage'] }}%; --today-position: {{ $project['timeline']['today_percentage'] ?? 0 }}%">
                                            <span class="services-timeline-track__base"></span><span class="services-timeline-track__progress"></span><i class="is-start"></i><i class="is-end"></i>
                                            @if ($project['timeline']['today_percentage'] !== null && $project['timeline']['state'] === 'active')<b class="services-timeline-today"><span>امروز</span></b>@endif
                                        </div>
                                        <div class="services-timeline-date services-timeline-date--end"><small>{{ $project['timeline']['end_label'] }}</small><strong>{{ $project['timeline']['end_date'] ?: 'تعیین نشده' }}</strong></div>
                                        <p class="services-timeline-status"><span>{{ $project['timeline']['label'] }}</span>{{ $project['timeline']['detail'] }}</p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

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

            <section class="services-kpis" aria-label="آمار خدمات">
                @foreach ([['پروژه‌های فعال', $dashboardStats['active_projects'], '◫'], ['فعالیت‌های قابل‌نمایش این ماه', $dashboardStats['published_activities'], '✓'], ['زمان مصرف‌شده دوره جاری', $currentCycles['used_time'], '◷']] as [$label, $value, $icon])
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

            </div>
        </div>
        <script>document.querySelectorAll('[data-activity-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.activityOpen)?.showModal()));</script>
    @else
        <x-client.empty-state title="حساب مشتری در دسترس نیست" message="ورود شما فعال است، اما هنوز به یک حساب مشتری فعال متصل نشده‌اید. لطفاً با پشتیبانی تماس بگیرید." />
    @endif
@endsection
