<x-client.card title="شرایط قرارداد و ویژگی‌های بسته">
    <p>{{ $agreement['service']['name'] ?? 'خدمت' }} / {{ $agreement['offering']['name'] ?? 'بسته' }}</p>
    <p>قیمت قراردادی: {{ \App\Support\ServiceDeliveryNumber::format($agreement['amount'] ?? null) ?? '—' }} {{ $agreement['currency_code'] ?? '' }}</p>
    <p class="portal-project-description">{{ $agreement['contract_terms'] ?? '' }}</p>
    <dl class="portal-project-dates">
        @foreach ($agreement['features'] ?? [] as $feature)
            <div><dt>{{ $feature['title'] ?? 'ویژگی' }}</dt><dd>{{ match ($feature['type'] ?? null) { 'boolean' => !empty($feature['value']) ? 'دارد' : 'ندارد', 'number' => \App\Support\ServiceDeliveryNumber::format($feature['value'] ?? null) ?? '—', default => $feature['value'] ?? '—' } }} {{ ($feature['type'] ?? null) === 'number' ? ($feature['unit'] ?? '') : '' }}</dd></div>
        @endforeach
    </dl>
</x-client.card>
