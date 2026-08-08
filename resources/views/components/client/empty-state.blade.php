@props(['title', 'message', 'icon' => 'empty'])

<div {{ $attributes->class(['portal-empty-state']) }}>
    <span class="portal-empty-state__icon" aria-hidden="true">
        @switch($icon)
            @case('projects') ▦ @break
            @case('reports') ▤ @break
            @case('invoices') ◫ @break
            @case('files') ▱ @break
            @default ○
        @endswitch
    </span>
    <h2>{{ $title }}</h2>
    <p>{{ $message }}</p>
    <span class="portal-badge">به‌زودی</span>
</div>
