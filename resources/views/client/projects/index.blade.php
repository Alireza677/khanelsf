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
                @include('client.projects.card', ['projectUrl' => route($serviceRoutes['project'], ['project' => $project['id'], 'customer' => $portalCustomer->id])])
            @endforeach
        </div>
        <div class="portal-pagination">{{ $projects->links() }}</div>
    @endif
@endsection
