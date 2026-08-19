<div class="page-history" x-data="{ tab: 'operations' }" dir="rtl">
    <nav class="page-history__tabs" aria-label="بخش‌های تاریخچه">
        <button type="button" x-on:click="tab = 'operations'" x-bind:class="{ 'is-active': tab === 'operations' }">عملیات</button>
        <button type="button" x-on:click="tab = 'revisions'" x-bind:class="{ 'is-active': tab === 'revisions' }">رونوشت‌ها</button>
    </nav>

    <section x-show="tab === 'operations'" class="page-history__panel">
        @if ($this->sessionHistoryNotice)
            <p class="page-history__empty">{{ $this->sessionHistoryNotice }}</p>
        @endif
        <div class="page-history__toolbar">
            <button type="button" wire:click="undoEditorHistory" @disabled(! $this->canUndoEditorHistory())>واگرد</button>
            <button type="button" wire:click="redoEditorHistory" @disabled(! $this->canRedoEditorHistory())>ازنو</button>
        </div>

        <div class="page-history__list">
            @forelse ($this->sessionHistoryEntries as $index => $entry)
                <button
                    type="button"
                    wire:click="applyEditorHistoryCheckpoint({{ $index }})"
                    @class(['page-history__entry', 'is-selected' => $this->sessionHistoryPointer === $index])
                >
                    <span>{{ $entry['label'] }}</span>
                    <time>{{ \Illuminate\Support\Carbon::createFromTimestampMs($entry['at'])->format('H:i:s') }}</time>
                </button>
            @empty
                <p class="page-history__empty">هیچ عملیاتی ثبت نشده است</p>
            @endforelse
        </div>
    </section>

    <section x-show="tab === 'revisions'" x-cloak class="page-history__panel">
        <div class="page-history__list">
            @forelse ($this->revisionRows() as $revision)
                <button
                    type="button"
                    wire:click="selectPageRevision({{ $revision['id'] }})"
                    @class(['page-history__entry', 'is-selected' => $this->selectedRevisionId === $revision['id']])
                >
                    <span>رونوشت شماره {{ $revision['number'] }}</span>
                    <small>{{ $revision['actor'] }} — {{ $revision['relative'] }}</small>
                    <time>{{ $revision['date'] }}</time>
                    @if ($revision['current'])<strong>✓ نسخه فعلی</strong>@endif
                    @if ($revision['restored_from'])<small>بازگردانی از نسخه {{ $revision['restored_from'] }}</small>@endif
                </button>
            @empty
                <p class="page-history__empty">هنوز رونوشت ذخیره‌شده‌ای وجود ندارد</p>
            @endforelse

            @if ($this->hasMoreRevisions())
                <button type="button" class="page-history__load-more" wire:click="loadMoreRevisions">نمایش رونوشت‌های بیشتر</button>
            @endif
        </div>

        <button
            type="button"
            class="page-history__apply"
            wire:click="applySelectedPageRevision"
            wire:confirm="این رونوشت در ویرایشگر بارگذاری می‌شود و تغییرات ذخیره‌نشده فعلی جایگزین خواهند شد."
            @disabled(! $this->selectedRevisionId)
        >اعمال رونوشت</button>
    </section>
</div>
