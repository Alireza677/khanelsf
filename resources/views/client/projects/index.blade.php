@extends('layouts.account')

@section('title', 'پروژه‌ها | پرتال مشتریان')

@section('account-content')
    <div class="portal-page-heading">
        <div><p class="portal-eyebrow">مدیریت پروژه</p><h1>پروژه‌ها</h1></div>
        @include('client.partials.customer-selector', ['action' => route($serviceRoutes['projects'])])
    </div>

    @if (! $portalCustomer)
        <x-client.empty-state title="حساب مشتری در دسترس نیست" message="برای مشاهده پروژه‌ها باید به یک حساب مشتری فعال متصل باشید." />
    @elseif ($projects->isEmpty())
        <x-client.empty-state title="پروژه‌ها" message="هنوز پروژه‌ای برای شما ثبت نشده است." icon="projects" />
    @else
        <div class="portal-project-grid">
            @foreach ($projects as $project)
                <x-client.card class="portal-project-card">
                    <div class="portal-project-card__header">
                        <div>
                            @if ($project['type'])<span class="portal-eyebrow">{{ $project['type'] }}</span>@endif
                            <h2>{{ $project['title'] }}</h2>
                        </div>
                        <span class="portal-badge">{{ $project['status_label'] }}</span>
                    </div>
                    <div class="portal-progress">
                        <div><span>پیشرفت</span><strong>{{ $project['progress'] }}٪</strong></div>
                        <progress value="{{ $project['progress'] }}" max="100">{{ $project['progress'] }}٪</progress>
                    </div>
                    <dl class="portal-project-dates">
                        <div><dt>تاریخ شروع</dt><dd>{{ $project['start_date'] ?: '—' }}</dd></div>
                        <div><dt>تاریخ پایان</dt><dd>{{ $project['end_date'] ?: '—' }}</dd></div>
                    </dl>
                    <a class="portal-button portal-button--secondary" href="{{ route($serviceRoutes['project'], ['project' => $project['id'], 'customer' => $portalCustomer->id]) }}">مشاهده پروژه</a>
                </x-client.card>
            @endforeach
        </div>
        <div class="portal-pagination">{{ $projects->links() }}</div>
    @endif
@endsection
