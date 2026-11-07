@props(['order', 'payment' => false])
@php
    $color = $payment ? $order->paymentStatusColor() : $order->statusColor();
    $icon = $payment ? $order->paymentStatusIcon() : $order->statusIcon();
@endphp
<span class="order-badge order-badge--{{ $color }}">
    @svg($icon, 'order-icon', ['aria-hidden' => 'true'])
    {{ $payment ? $order->paymentStatusLabel() : $order->statusLabel() }}
</span>
