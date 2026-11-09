<p role="status">{{ method_exists($items, 'total') ? $items->total() : $items->count() }} محصول پیدا شد.</p>
@if (! empty($filterUi['active']))
    <div class="shop-template__active-filters">
        @foreach ($filterUi['active'] as $activeFilter)
            <a rel="nofollow" href="{{ $activeFilter['url'] }}" aria-label="حذف {{ $activeFilter['label'] }}">{{ $activeFilter['label'] }} <span aria-hidden="true">×</span></a>
        @endforeach
        <a rel="nofollow" href="{{ $filterUi['clearUrl'] }}">پاک کردن همه</a>
    </div>
@endif
