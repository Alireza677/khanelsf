<x-client.card class="portal-project-card">
    <div class="portal-project-card__header">
        <div>
            @if ($project['service_name'] || $project['package_name'])
                <p class="portal-eyebrow">{{ implode(' / ', array_filter([$project['service_name'], $project['package_name']])) }}</p>
            @endif
            <h2>{{ $project['title'] }}</h2>
        </div>
        <span class="portal-badge">{{ $project['status_label'] }}</span>
    </div>
    @if ($project['cycle_start'])
        <p class="portal-project-card__period">دوره جاری: {{ $project['cycle_start'] }} تا {{ $project['cycle_end'] }} <small>(پایان غیرشامل)</small></p>
    @endif
    @if ($project['delivery_mode'] !== 'tasks')
        <section class="portal-project-card__section" aria-label="مصرف زمان">
            <h3>مصرف زمان</h3>
            <dl class="portal-project-card__metrics">
                <div><dt>سهم دوره جاری</dt><dd>{{ $project['limit_time'] ?? ($project['has_current_cycle'] ? 'بدون سقف زمانی' : 'دوره جاری ندارد') }}</dd></div>
                <div><dt>مصرف دوره جاری</dt><dd>{{ $project['used_time'] }}</dd></div>
                @if ($project['remaining_time'] !== null)<div><dt>باقی‌مانده</dt><dd>{{ $project['remaining_time'] }}</dd></div>@endif
            </dl>
            @if ($project['overage_time'])<p class="portal-badge">خدمات مازاد: {{ $project['overage_time'] }}</p>@endif
        </section>
    @endif
    @if ($project['delivery_mode'] !== 'time')
        <section class="portal-project-card__section" aria-label="پیشرفت تحویل">
            <h3>تحویل خروجی‌ها</h3>
            @forelse (array_slice($project['commitments'], 0, 3) as $line)
                @php($unitLabel = \App\Enums\ServiceUnit::tryFrom($line['unit'])?->label() ?? $line['unit'])
                <div class="portal-deliverable-progress">
                    <div><strong>{{ $line['name'] }}</strong><span>{{ \App\Support\PersianDate::digits(\App\Support\ServiceDeliveryNumber::format($line['completed'])) }} از {{ \App\Support\PersianDate::digits(\App\Support\ServiceDeliveryNumber::format($line['committed'])) }} {{ $unitLabel }}</span></div>
                    <progress aria-label="{{ $line['name'] }}" value="{{ $line['completed'] }}" max="{{ $line['committed'] }}"></progress>
                    <small>باقی‌مانده: {{ \App\Support\PersianDate::digits(\App\Support\ServiceDeliveryNumber::format($line['remaining'])) }} {{ $unitLabel }}</small>
                </div>
            @empty
                <p class="portal-project-card__period">تعهدات دوره جاری ثبت نشده است.</p>
            @endforelse
            @if (count($project['commitments']) > 3)<small>سایر تعهدات در جزئیات پروژه</small>@endif
            @if ($project['awaiting_approval_count'])<span class="portal-badge">{{ \App\Support\PersianDate::digits($project['awaiting_approval_count']) }} خروجی منتظر تأیید شما</span>@endif
        </section>
    @endif
    <div class="portal-project-card__footer">
        @if ($project['deadline'])<p>موعد: <time>{{ $project['deadline'] }}</time></p>@endif
        <a class="portal-button portal-button--secondary" href="{{ $projectUrl }}" aria-label="مشاهده جزئیات پروژه {{ $project['title'] }}">مشاهده جزئیات پروژه ←</a>
    </div>
</x-client.card>
