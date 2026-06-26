@php
    $items = $context['items'] ?? collect();
    $categories = $context['categories'] ?? collect();
    $filters = $context['filters'] ?? [];
    $favoriteProductIds = $context['favoriteProductIds'] ?? [];
    $activeCategory = $context['activeCategory'] ?? null;
    $heading = $data['title'] ?? $context['heading'] ?? 'فروشگاه';
    $description = $data['description'] ?? $context['description'] ?? null;
    $backgroundImage = $data['background_image'] ?? null;
    $allCategoriesImage = $data['all_categories_image'] ?? null;
    $thicknessOptions = ['0.75' => '۰٫۷۵', '0.9' => '۰٫۹', '1.25' => '۱٫۲۵', '1.5' => '۱٫۵'];
    $applicationOptions = [
        'wall' => 'دیوار',
        'roof' => 'سقف',
        'truss' => 'خرپا',
        'connections' => 'اتصالات',
        'insulation' => 'عایق‌کاری',
    ];
    $overlayOpacity = (int) ($data['overlay_opacity'] ?? 20);
    $overlayOpacity = max(0, min(90, $overlayOpacity));
    $overlayColor = "color-mix(in srgb, var(--theme-secondary, #111827) {$overlayOpacity}%, transparent)";
    $style = filled($backgroundImage)
        ? "background-image: linear-gradient({$overlayColor}, {$overlayColor}), url('".e($backgroundImage)."');"
        : null;
    $query = [
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? $activeCategory?->slug ?? '',
        'min_price' => $filters['min_price'] ?? '',
        'max_price' => $filters['max_price'] ?? '',
        'stock' => $filters['stock'] ?? '',
        'featured' => ! empty($filters['featured']) ? '1' : '',
        'favorites' => ! empty($filters['favorites']) ? '1' : '',
        'thickness' => $filters['thickness'] ?? [],
        'application' => $filters['application'] ?? [],
    ];
    $cleanQuery = fn (array $values): array => collect($values)
        ->reject(fn ($value): bool => $value === '' || $value === null || $value === false || $value === [])
        ->all();
@endphp

@if (($context['type'] ?? null) === 'products')
    <section class="content-block shop-template" dir="rtl">
        <header class="shop-template__hero" @if ($style) style="{!! $style !!}" @endif>
            <div class="shop-template__hero-inner">
                <div class="shop-template__copy">
                    @if (! empty($data['eyebrow']))
                        <p class="shop-template__eyebrow">{{ $data['eyebrow'] }}</p>
                    @endif

                    <h1>{{ $heading }}</h1>

                    @if ($description)
                        <p>{{ $description }}</p>
                    @endif
                </div>

                <form class="shop-template__search" action="{{ route('shop.index') }}" method="get">
                    @if ($query['favorites'])
                        <input type="hidden" name="favorites" value="1">
                    @endif
                    @if ($query['min_price'] !== '')
                        <input type="hidden" name="min_price" value="{{ $query['min_price'] }}">
                    @endif
                    @if ($query['max_price'] !== '')
                        <input type="hidden" name="max_price" value="{{ $query['max_price'] }}">
                    @endif
                    @if ($query['stock'])
                        <input type="hidden" name="stock" value="{{ $query['stock'] }}">
                    @endif
                    @if ($query['featured'])
                        <input type="hidden" name="featured" value="1">
                    @endif
                    @foreach ($query['thickness'] as $thickness)
                        <input type="hidden" name="thickness[]" value="{{ $thickness }}">
                    @endforeach
                    @foreach ($query['application'] as $application)
                        <input type="hidden" name="application[]" value="{{ $application }}">
                    @endforeach
                    <label class="sr-only" for="shop-template-q">جستجوی محصولات</label>
                    <span class="shop-template__search-field">
                        <i class="shop-template__search-icon icon-search-normal" aria-hidden="true"></i>
                        <input
                            id="shop-template-q"
                            name="q"
                            type="search"
                            value="{{ $query['q'] }}"
                            placeholder="{{ $data['search_placeholder'] ?? 'جستجو در بین هزاران محصول...' }}"
                        >
                    </span>

                    <label class="sr-only" for="shop-template-category">دسته‌بندی محصول</label>
                    <span class="shop-template__select-field">
                        <select id="shop-template-category" name="category">
                            <option value="">{{ $data['category_label'] ?? 'انتخاب دسته‌بندی' }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->slug }}" @selected($query['category'] === $category->slug)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <i class="shop-template__select-icon icon-arrow-down-1" aria-hidden="true"></i>
                    </span>

                    <button type="submit" aria-label="جستجوی محصولات">
                        <span>جستجو</span>
                        <i class="icon-search-normal" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </header>

        @if ($categories->isNotEmpty())
            <section class="shop-template__categories" aria-labelledby="shop-template-categories-title" data-shop-category-slider>
                <div class="shop-template__section-heading shop-template__section-heading--center">
                    @php
                        $requestedCategoryHeadingTag = $data['category_heading_tag'] ?? 'h2';
                        $categoryHeadingTag = in_array($requestedCategoryHeadingTag, ['h1', 'h2'], true) ? $requestedCategoryHeadingTag : 'h2';
                    @endphp
                    <{{ $categoryHeadingTag }} id="shop-template-categories-title" class="block-title">{{ $data['category_section_title'] ?? 'خرید بر اساس دسته‌بندی' }}</{{ $categoryHeadingTag }}>
                    <span class="shop-template__heading-rule" aria-hidden="true"></span>
                    <div class="shop-template__category-controls">
                        <button class="shop-template__category-control shop-template__category-control--prev" type="button" aria-label="دسته‌بندی‌های قبلی" data-shop-category-prev>
                            <i class="icon-arrow-left-2" aria-hidden="true"></i>
                        </button>

                        <button class="shop-template__category-control shop-template__category-control--next" type="button" aria-label="دسته‌بندی‌های بعدی" data-shop-category-next>
                            <i class="icon-arrow-right-3" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div class="shop-template__category-slider">
                    <div class="shop-template__category-viewport" data-shop-category-viewport>
                        <div class="shop-template__category-row" data-shop-category-track>
                            <a @class(['shop-template__category', 'shop-template__category--all', 'is-active' => ! $activeCategory]) href="{{ route('shop.index', $cleanQuery([...$query, 'category' => ''])) }}">
                                <span class="shop-template__category-media">
                                    @if (filled($allCategoriesImage))
                                        <img src="{{ $allCategoriesImage }}" alt="همه محصولات">
                                    @else
                                        <span class="shop-template__category-grid-icon" aria-hidden="true">
                                            <span></span><span></span><span></span><span></span>
                                        </span>
                                    @endif
                                </span>
                                <span>همه محصولات</span>
                            </a>

                            @foreach ($categories as $category)
                                @php
                                    $categoryQuery = $cleanQuery([
                                        ...$query,
                                        'category' => $category->slug,
                                    ]);
                                @endphp

                                <a @class(['shop-template__category', 'is-active' => $activeCategory?->is($category)]) href="{{ route('shop.index', $categoryQuery) }}">
                                    <span class="shop-template__category-media">
                                        @if (! empty($category->seo_image))
                                            <img src="{{ $category->seo_image }}" alt="{{ $category->name }}">
                                        @else
                                            <span>{{ mb_substr($category->name, 0, 1) }}</span>
                                        @endif
                                    </span>
                                    <span>{{ $category->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                </div>
            </section>
        @endif

        <section class="shop-template__catalog" aria-labelledby="shop-template-products-title">
            <aside class="shop-template__filters" aria-label="فیلترهای محصول">
                <form action="{{ route('shop.index') }}" method="get">
                    @if ($query['favorites'])
                        <input type="hidden" name="favorites" value="1">
                    @endif

                    <label class="sr-only" for="shop-sidebar-q">جستجوی محصول</label>
                    <span class="shop-template__sidebar-search">
                        <input id="shop-sidebar-q" name="q" type="search" value="{{ $query['q'] }}" placeholder="جستجوی محصول...">
                        <i class="icon-search-normal" aria-hidden="true"></i>
                    </span>

                    <details class="shop-template__filter-group" open>
                        <summary>دسته‌بندی</summary>
                        <div class="shop-template__filter-options">
                            <label>
                                <input name="category" type="radio" value="" @checked($query['category'] === '')>
                                همه دسته‌بندی‌ها
                            </label>
                            @foreach ($categories as $category)
                                <label>
                                    <input name="category" type="radio" value="{{ $category->slug }}" @checked($query['category'] === $category->slug)>
                                    {{ $category->name }}
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <details class="shop-template__filter-group" @if ($query['min_price'] !== '' || $query['max_price'] !== '') open @endif>
                        <summary>محدوده قیمت</summary>
                        <div class="shop-template__price-fields">
                            <label>
                                از
                                <input name="min_price" type="number" min="0" step="1" value="{{ $query['min_price'] }}" placeholder="حداقل قیمت">
                            </label>
                            <label>
                                تا
                                <input name="max_price" type="number" min="0" step="1" value="{{ $query['max_price'] }}" placeholder="حداکثر قیمت">
                            </label>
                        </div>
                    </details>

                    <details class="shop-template__filter-group" @if ($query['thickness']) open @endif>
                        <summary>ضخامت ورق</summary>
                        <div class="shop-template__filter-options">
                            @foreach ($thicknessOptions as $value => $label)
                                <label>
                                    <input name="thickness[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, $query['thickness'], true))>
                                    {{ $label }} میلی‌متر
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <details class="shop-template__filter-group" @if ($query['application']) open @endif>
                        <summary>نوع کاربرد</summary>
                        <div class="shop-template__filter-options">
                            @foreach ($applicationOptions as $value => $label)
                                <label>
                                    <input name="application[]" type="checkbox" value="{{ $value }}" @checked(in_array($value, $query['application'], true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <details class="shop-template__filter-group" @if ($query['stock']) open @endif>
                        <summary>وضعیت موجودی</summary>
                        <div class="shop-template__filter-options">
                            <label><input name="stock" type="radio" value="" @checked($query['stock'] === '')> همه محصولات</label>
                            <label><input name="stock" type="radio" value="in_stock" @checked($query['stock'] === 'in_stock')> موجود</label>
                            <label><input name="stock" type="radio" value="out_of_stock" @checked($query['stock'] === 'out_of_stock')> ناموجود</label>
                        </div>
                    </details>

                    <details class="shop-template__filter-group" @if ($query['featured']) open @endif>
                        <summary>محصولات ویژه</summary>
                        <div class="shop-template__filter-options">
                            <label>
                                <input name="featured" type="checkbox" value="1" @checked($query['featured'])>
                                فقط محصولات ویژه
                            </label>
                        </div>
                    </details>

                    <div class="shop-template__filter-actions">
                        <button class="button" type="submit">اعمال فیلترها</button>
                        <a class="button button-secondary" href="{{ route('shop.index') }}">حذف فیلترها</a>
                    </div>
                </form>
            </aside>

            <div class="shop-template__products">
                <div class="shop-template__toolbar">
                    <div class="shop-template__toolbar-actions">
                        <div class="shop-template__sort">
                            <span>مرتب‌سازی:</span>
                            <select aria-label="مرتب‌سازی محصولات">
                                <option>جدیدترین</option>
                                <option>پربازدیدترین</option>
                                <option>ارزان‌ترین</option>
                                <option>گران‌ترین</option>
                            </select>
                        </div>

                        <a
                            @class(['shop-template__favorites-filter', 'is-active' => $query['favorites']])
                            href="{{ route('shop.index', $cleanQuery([...$query, 'favorites' => $query['favorites'] ? '' : '1'])) }}"
                            aria-pressed="{{ $query['favorites'] ? 'true' : 'false' }}"
                        >
                            
                            <span>{{ $query['favorites'] ? 'نمایش همه محصولات' : 'مشاهده علاقه‌مندی‌ها' }}</span>
                        </a>
                    </div>

                    

                    
                </div>

                <div class="shop-template__product-scroll">
                    <div class="shop-template__product-grid">
                        @forelse ($items as $product)
                            @php($isFavorite = in_array($product->getKey(), $favoriteProductIds, true))
                            <article @class(['shop-template__product-card', 'is-favorite' => $isFavorite])>
                                <form class="shop-template__favorite-form" action="{{ route('shop.favorites.toggle', $product) }}" method="post">
                                    @csrf
                                    <button
                                        class="shop-template__heart"
                                        type="submit"
                                        aria-label="{{ $isFavorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها' }}"
                                        aria-pressed="{{ $isFavorite ? 'true' : 'false' }}"
                                        title="{{ $isFavorite ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها' }}"
                                    >
                                        <i class="icon-heart" aria-hidden="true"></i>
                                    </button>
                                </form>

                                <a class="shop-template__product-image" href="{{ route('shop.show', $product->slug) }}" aria-label="{{ $product->title }}">
                                    @if ($product->is_featured)
                                        <span class="shop-template__badge">ویژه</span>
                                    @endif

                                    @if ($product->featuredImageUrl('thumb'))
                                        <img src="{{ $product->featuredImageUrl('thumb') }}" alt="{{ $product->title }}">
                                    @else
                                        <span class="shop-template__image-placeholder">{{ $product->title }}</span>
                                    @endif
                                </a>

                                <div class="shop-template__product-body">
                                    <h3><a href="{{ route('shop.show', $product->slug) }}">{{ $product->title }}</a></h3>

                                    <p class="shop-template__price">
                                        {{ number_format($product->currentPrice()) }}
                                        <span>تومان</span>
                                    </p>

                                    <p @class(['shop-template__stock', 'is-out' => ! $product->isPurchasable()])>
                                        <span></span>
                                        {{ $product->isPurchasable() ? 'موجود' : 'ناموجود' }}
                                    </p>
                                </div>
                            </article>
                        @empty
                            <p class="blog-index__empty">{{ $data['empty_message'] ?? $context['emptyMessage'] ?? 'محصولی با فیلترهای انتخاب‌شده پیدا نشد.' }}</p>
                        @endforelse
                    </div>
                </div>

                @if (is_object($items) && method_exists($items, 'links'))
                    <div class="blog-index__pagination">
                        {{ $items->links() }}
                    </div>
                @endif

                @if ($query['q'] || $query['category'] || $query['stock'] || $query['min_price'] || $query['max_price'] || $query['featured'] || $query['favorites'] || $query['thickness'] || $query['application'])
                    <div class="shop-template__active-filters">
                        @if ($query['category'])
                            <span>دسته‌بندی: {{ $activeCategory?->name ?? $query['category'] }}</span>
                        @endif
                        @if ($query['stock'])
                            <span>وضعیت موجودی: {{ $query['stock'] === 'in_stock' ? 'موجود' : 'ناموجود' }}</span>
                        @endif
                        @if ($query['featured'])
                            <span>فقط محصولات ویژه: بله</span>
                        @endif
                        @if ($query['q'])
                            <span>جستجو: {{ $query['q'] }}</span>
                        @endif
                        @if ($query['favorites'])
                            <span>فقط علاقه‌مندی‌ها: بله</span>
                        @endif
                        @if ($query['thickness'])
                            <span>ضخامت: {{ collect($query['thickness'])->map(fn ($value) => $thicknessOptions[$value] ?? $value)->join('، ') }} میلی‌متر</span>
                        @endif
                        @if ($query['application'])
                            <span>کاربرد: {{ collect($query['application'])->map(fn ($value) => $applicationOptions[$value] ?? $value)->join('، ') }}</span>
                        @endif
                        <a href="{{ route('shop.index') }}">پاک کردن همه</a>
                    </div>
                @endif
            </div>
        </section>
    </section>
@elseif (app()->hasDebugModeEnabled())
    <p class="empty-state">قالب کامل فروشگاه فقط برای آرشیو محصولات فروشگاه کار می‌کند.</p>
@endif
