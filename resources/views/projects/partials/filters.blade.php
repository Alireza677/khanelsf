@php($selectedCount = array_sum(array_map('count', $projectGalleryFilters['active'])))
<div class="project-filters" data-project-gallery-filters
     data-url="{{ $projectGalleryFilters['url'] }}"
     data-count="{{ $projectGalleryFilters['count'] }}"
     data-active="{{ json_encode($projectGalleryFilters['active']) }}" hidden>
    <button type="button" class="project-filters__tab" data-project-filter-open aria-haspopup="dialog" aria-controls="project-filter-panel" aria-expanded="false">
        <i class="icon-arrow-left-2" aria-hidden="true"></i>
        <span>فیلتر پروژه‌ها</span>
        <span class="project-filters__badge" data-project-filter-badge @if (!$selectedCount) hidden @endif>{{ $selectedCount }}</span>
    </button>

    <div class="project-filters__mobile-bar">
        <button type="button" class="project-filters__mobile-trigger" data-project-filter-open aria-haspopup="dialog" aria-controls="project-filter-panel" aria-expanded="false">
            <i class="icon-filter" aria-hidden="true"></i>
            <span>فیلتر پروژه‌ها</span>
            <span class="project-filters__badge" data-project-filter-badge @if (!$selectedCount) hidden @endif>{{ $selectedCount }}</span>
        </button>
    </div>

    <dialog id="project-filter-panel" class="project-filters__panel" aria-labelledby="project-filter-title">
        <form action="{{ $projectGalleryFilters['url'] }}" method="GET" class="project-filters__form">
            <header class="project-filters__header">
                <button type="button" class="project-filters__close" data-project-filter-close aria-label="بستن فیلترها" autofocus>×</button>
                <h2 id="project-filter-title">فیلتر</h2>
                <button type="button" class="project-filters__reset" data-project-filter-reset>پاک کردن</button>
            </header>

            <div class="project-filters__groups">
                @foreach ($projectGalleryFilters['groups'] as $key => $group)
                    <section class="project-filters__group">
                        <h3>
                            <button type="button" class="project-filters__accordion" aria-expanded="false" aria-controls="project-filter-options-{{ $key }}">
                                <span>{{ $group['label'] }}</span><i class="project-filters__chevron icon-arrow-left-2" aria-hidden="true"></i>
                            </button>
                        </h3>
                        <div id="project-filter-options-{{ $key }}" class="project-filters__options" hidden>
                            @forelse ($group['options'] as $option)
                                <label for="project-filter-{{ $key }}-{{ $option['id'] }}">
                                    <input type="checkbox" id="project-filter-{{ $key }}-{{ $option['id'] }}" name="{{ $key }}[]" value="{{ $option['id'] }}" data-filter-group="{{ $key }}" @checked(in_array($option['id'], $projectGalleryFilters['active'][$key], true))>
                                    <span>{{ $option['label'] }}</span>
                                </label>
                            @empty
                                <p class="project-filters__no-options">گزینه‌ای برای پروژه‌های منتشرشده ثبت نشده است.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>

            <footer class="project-filters__footer">
                <p class="project-filters__message" data-project-filter-panel-message role="status"></p>
                <button type="submit" class="project-filters__apply" data-project-filter-apply>
                    مشاهده <span data-project-filter-count aria-live="polite">{{ $projectGalleryFilters['count'] }}</span> پروژه
                </button>
            </footer>
        </form>
    </dialog>
    <p class="project-filters__notice" data-project-filter-notice role="status" hidden></p>
</div>
