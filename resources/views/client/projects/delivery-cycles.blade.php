<x-client.card title="دوره جاری و دوره‌های تاریخی">
    <form method="GET" action="{{ route($serviceRoutes['project'], ['project' => $project['id']]) }}" class="portal-actions">
        <input type="hidden" name="customer" value="{{ $portalCustomer->id }}">
        @if ($project['delivery_mode'] === 'hybrid')<input type="hidden" name="month" value="{{ $summary['month'] }}">@endif
        <label for="delivery-cycle">دوره خدماتی</label>
        <select name="cycle" id="delivery-cycle" class="portal-select">
            @foreach ($delivery['cycles'] as $period)
                <option value="{{ $period['id'] }}" @selected(($delivery['cycle']['id'] ?? null) === $period['id'])>{{ $period['start'] }} تا {{ $period['end'] }} (پایان غیرشامل)</option>
            @endforeach
        </select>
        <button class="portal-button" type="submit">نمایش دوره</button>
    </form>
</x-client.card>
    @if ($cycle = $delivery['cycle'])
    <x-client.card title="تعهدات و برنامه دوره انتخاب‌شده">
        <p>{{ $cycle['agreement']['service']['name'] ?? 'خدمت' }} / {{ $cycle['agreement']['offering']['name'] ?? 'بسته' }}</p>
        <p>دوره: {{ $cycle['start'] }} تا {{ $cycle['end'] }} (پایان غیرشامل)</p>
        <p class="portal-privacy-note">تعهدات این دوره از قرارداد تاریخی خوانده می‌شوند. تکمیل هر نوع تحویل مستقل محاسبه می‌شود.</p>
        <div class="portal-activity-list">
            @foreach ($cycle['commitments'] as $line)
                <article class="portal-activity-item"><h3>{{ $line['name'] }}</h3><p>تعهد: {{ \App\Support\ServiceDeliveryNumber::format($line['committed']) }} {{ $line['unit'] }}؛ تکمیل: {{ \App\Support\ServiceDeliveryNumber::format($line['completed']) }}؛ باقی‌مانده: {{ \App\Support\ServiceDeliveryNumber::format($line['remaining']) }}</p></article>
            @endforeach
        </div>
    </x-client.card>
    <x-client.card title="کارها، موعدها و تحویل‌ها">
        <div class="portal-activity-list">
            @forelse ($cycle['items'] as $work)
                <article class="portal-activity-item">
                    <h3><a href="{{ route('account.delivery.show', ['project' => $project['id'], 'workItem' => $work['id'], 'customer' => $portalCustomer->id]) }}">{{ $work['title'] }}</a></h3>
                    <span class="portal-badge">{{ $work['status_label'] }}</span>
                    <p>برنامه: {{ $work['scheduled_start'] ?: '—' }} تا {{ $work['scheduled_end'] ?: '—' }}؛ موعد: {{ $work['due'] ?: '—' }}</p>
                    <p>مسئولان: {{ implode('، ', $work['assignees']) ?: '—' }}</p>
                    @foreach ($work['deliveries'] as $output)<p>تحویل نسخه {{ $output['version'] }}: {{ $output['date'] }}</p>@endforeach
                </article>
            @empty
                <p>کار قابل نمایشی برای این دوره ثبت نشده است.</p>
            @endforelse
        </div>
    </x-client.card>
    <x-client.card title="گزارش‌های منتشرشده و بایگانی">
        @forelse ($cycle['reports'] as $report)
            <details class="portal-activity-item"><summary>نسخه {{ $report['version'] }} — {{ $report['date'] }} — {{ $report['outcome'] === 'successful' ? 'تعهدات تکمیل‌شده' : 'گزارش ناقص' }}</summary>
                <p>دوره محفوظ: {{ \App\Support\PersianDate::date($report['snapshot']['starts_at']) }} تا {{ \App\Support\PersianDate::date($report['snapshot']['ends_at']) }}</p>
                <p>{{ $report['snapshot']['agreement']['offering']['name'] ?? '' }} — {{ $report['snapshot']['agreement']['amount'] ?? '' }} {{ $report['snapshot']['agreement']['currency_code'] ?? '' }}</p>
                <p>{{ $report['snapshot']['message'] }}</p>
                @foreach ($report['snapshot']['commitments'] as $line)<p>{{ $line['name'] }}: {{ \App\Support\ServiceDeliveryNumber::format($line['completed']) }} از {{ \App\Support\ServiceDeliveryNumber::format($line['committed']) }} {{ $line['unit'] }}؛ باقی‌مانده {{ \App\Support\ServiceDeliveryNumber::format($line['remaining']) }}</p>@endforeach
                @foreach ($report['snapshot']['items'] as $work)
                    <details><summary>{{ $work['title'] }} — {{ $work['status_label'] }}</summary>
                        <p>موعد محفوظ: {{ $work['due'] ?: '—' }}</p>
                        @foreach ($work['deliveries'] as $output)
                            <p>تحویل نسخه {{ $output['version'] }} — {{ $output['date'] }} — {{ ['pending' => 'منتظر بررسی', 'accepted' => 'تأییدشده', 'revision_requested' => 'اصلاح درخواست‌شده', 'not_required' => 'بدون نیاز به تأیید'][$output['review_state']] }}</p>
                            <p>{{ $output['message'] }}</p>
                            @if ($output['review_reason'])<p>نظر مشتری: {{ $output['review_reason'] }}</p>@endif
                            @foreach ($output['files'] as $file)
                                <p><a href="{{ route('account.delivery.download', ['project' => $project['id'], 'workItem' => $work['id'], 'delivery' => $output['id'], 'file' => $file['id'], 'customer' => $portalCustomer->id]) }}">دریافت {{ $file['name'] }}</a></p>
                            @endforeach
                        @endforeach
                        @foreach ($work['timeline'] as $event)<p>{{ $event['label'] }} — {{ $event['date'] }}</p>@endforeach
                    </details>
                @endforeach
            </details>
        @empty
            <p>گزارش منتشرشده‌ای برای این دوره وجود ندارد.</p>
        @endforelse
    </x-client.card>
    @include('client.projects.contract', ['agreement' => $cycle['agreement']])
    @else
        <x-client.card title="دوره جاری"><p>دوره خدماتی ثبت نشده است.</p></x-client.card>
    @endif
