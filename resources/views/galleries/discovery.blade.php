@extends('layouts.app')

@section('content')
    <section class="gallery-discovery">
        <header class="blog-index__header">
            <h1>{{ $heading ?? 'گالری پروژه‌ها' }}</h1>

            @if (! empty($description))
                <p>{{ $description }}</p>
            @endif
        </header>

        @if ($vocabularies->isNotEmpty())
            <details class="gallery-discovery__filters">
                <summary>فیلتر پروژه‌ها</summary>

                <form class="gallery-discovery__filter-panel" method="GET" action="{{ route('galleries.index') }}">
                    @foreach ($vocabularies as $vocabulary)
                        <fieldset>
                            <legend>{{ $vocabulary->name }}</legend>

                            <div class="gallery-discovery__filter-options">
                                @foreach ($vocabulary->terms as $term)
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="filters[{{ $vocabulary->slug }}][]"
                                            value="{{ $term->slug }}"
                                            @checked(in_array($term->slug, $active_filters[$vocabulary->slug] ?? [], true))
                                        >
                                        <span>{{ $term->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="gallery-discovery__filter-actions">
                        <button class="button" type="submit">اعمال فیلترها</button>
                        @if ($active_filters !== [])
                            <a href="{{ route('galleries.index') }}">پاک‌کردن فیلترها</a>
                        @endif
                    </div>
                </form>
            </details>
        @endif

        <div class="gallery-discovery__grid">
            @forelse ($projects as $project)
                @include('galleries.partials.project-discovery-card', ['project' => $project])
            @empty
                <p class="blog-index__empty">پروژه‌ای مطابق فیلترهای انتخاب‌شده پیدا نشد.</p>
            @endforelse
        </div>

        <div class="blog-index__pagination">
            {{ $projects->links() }}
        </div>
    </section>
@endsection
