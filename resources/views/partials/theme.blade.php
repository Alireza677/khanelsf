@php
    $settings = app(\App\Services\SettingsService::class);
    $themeVariables = $settings->themeVariables();
@endphp

@include('partials.site-font-styles')

<style>
    :root {
        @foreach ($themeVariables as $name => $value)
            @if ($name !== '--theme-font-family')
            {{ $name }}: {!! $value !!};
            @endif
        @endforeach
    }
</style>
