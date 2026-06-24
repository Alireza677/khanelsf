@php
    $settings = app(\App\Services\SettingsService::class);
    $themeVariables = $settings->themeVariables();
    $customFontUrl = $settings->customFontUrl();
    $customFontName = $settings->customFontName();
    $customFontFormat = $settings->customFontFormat();
@endphp

<style>
    @if ($customFontUrl)
        @font-face {
            font-family: "{{ addslashes($customFontName) }}";
            src: url("{{ $customFontUrl }}") format("{{ $customFontFormat }}");
            font-display: swap;
            font-style: normal;
            font-weight: 400;
        }
    @endif

    :root {
        @foreach ($themeVariables as $name => $value)
            {{ $name }}: {!! $value !!};
        @endforeach
    }
</style>
