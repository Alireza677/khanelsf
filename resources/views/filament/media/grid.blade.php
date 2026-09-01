<div class="media-library-grid" role="list" aria-label="رسانه‌ها">
    @foreach ($records as $record)
        @php
            $recordKey = (string) $record->getKey();
            $url = $record->getUrl();
            $mimeType = (string) ($record->mime_type ?? '');
            $extension = strtoupper(pathinfo($record->file_name, PATHINFO_EXTENSION));
        @endphp

        <article
            class="media-library-grid__item"
            role="listitem"
            wire:key="media-grid-record-{{ $recordKey }}"
        >
            <label class="media-library-grid__checkbox" title="انتخاب {{ $record->displayTitle() }}">
                <input
                    type="checkbox"
                    value="{{ $recordKey }}"
                    x-model="selectedRecords"
                    aria-label="انتخاب {{ $record->displayTitle() }}"
                >
            </label>

            <button
                type="button"
                class="media-library-grid__details"
                wire:click="mountTableAction('details', '{{ $recordKey }}')"
                wire:loading.attr="disabled"
                wire:target="mountTableAction('details', '{{ $recordKey }}')"
                title="جزئیات {{ $record->displayTitle() }}"
            >
                <span class="media-grid-card__preview">
                    @if (str_starts_with($mimeType, 'image/'))
                        <img src="{{ $url }}" alt="{{ $record->altText() }}" loading="lazy">
                    @elseif (str_starts_with($mimeType, 'video/'))
                        <video src="{{ $url }}" muted playsinline preload="metadata"></video>
                        <span class="media-grid-card__type-icon" aria-hidden="true">
                            <x-filament::icon icon="heroicon-s-play" class="h-6 w-6" />
                        </span>
                    @else
                        <x-filament::icon icon="heroicon-o-document" class="media-grid-card__file-icon" />
                        @if ($extension !== '')
                            <span class="media-grid-card__extension">{{ $extension }}</span>
                        @endif
                    @endif
                </span>

                <span class="media-grid-card__title">{{ $record->displayTitle() }}</span>
            </button>
        </article>
    @endforeach
</div>
