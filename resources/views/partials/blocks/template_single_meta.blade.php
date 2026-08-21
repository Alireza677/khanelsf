@php
    $model = $context['model'] ?? null;
    $type = $context['type'] ?? null;
@endphp

@if ($model)
    <section class="content-block">
        <dl class="project-meta">
            @if ($type === 'product')
                @if ($model->category)
                    <div><dt>Category</dt><dd><a href="{{ route('shop.category', $model->category->slug) }}">{{ $model->category->name }}</a></dd></div>
                @endif
                @if ($model->sku)
                    <div><dt>SKU</dt><dd>{{ $model->sku }}</dd></div>
                @endif
                <div><dt>Availability</dt><dd>{{ $model->isPurchasable() ? 'In stock' : 'Out of stock' }}</dd></div>
                <div>
                    <dt>Price</dt>
                    <dd>
                        @if ($model->hasSalePrice())
                            <span class="product-price__sale">${{ number_format($model->currentPrice(), 2) }}</span>
                            <span class="product-price__regular">${{ number_format((float) $model->price, 2) }}</span>
                        @else
                            ${{ number_format($model->currentPrice(), 2) }}
                        @endif
                    </dd>
                </div>
            @elseif ($type === 'project')
                @if ($model->category)
                    <div><dt>Category</dt><dd><a href="{{ route('projects.category', $model->category->slug) }}">{{ $model->category->name }}</a></dd></div>
                @endif
                @if ($model->client_name)
                    <div><dt>Client</dt><dd>{{ $model->client_name }}</dd></div>
                @endif
                @if ($model->location)
                    <div><dt>Location</dt><dd>{{ $model->location }}</dd></div>
                @endif
                @if ($model->project_date)
                    <div><dt>Date</dt><dd><x-persian-date :value="$model->project_date" format="weekday" /></dd></div>
                @endif
                @if (collect($model->services)->isNotEmpty())
                    <div><dt>Services</dt><dd>{{ collect($model->services)->map(fn ($service) => $service['name'] ?? $service)->filter()->implode(', ') }}</dd></div>
                @endif
            @elseif ($type === 'post')
                @if ($model->category)
                    <div><dt>Category</dt><dd><a href="{{ route('blog.category', $model->category->slug) }}">{{ $model->category->title }}</a></dd></div>
                @endif
                @if ($model->published_at)
                    <div><dt>Published</dt><dd><x-persian-date :value="$model->published_at" format="weekday" /></dd></div>
                @endif
            @elseif ($type === 'gallery')
                <div><dt>Type</dt><dd>{{ ucfirst($model->type) }}</dd></div>
                @if ($model->category)
                    <div><dt>Category</dt><dd><a href="{{ route('galleries.category', $model->category->slug) }}">{{ $model->category->name }}</a></dd></div>
                @endif
                @if ($model->project)
                    <div><dt>Related Project</dt><dd><a href="{{ route('projects.show', $model->project->slug) }}">{{ $model->project->title }}</a></dd></div>
                @endif
            @endif
        </dl>
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">Template Single Meta needs a single item context.</p>
@endif
