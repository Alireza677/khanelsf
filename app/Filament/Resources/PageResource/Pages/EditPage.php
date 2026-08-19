<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\Concerns\LogsHeroV2SaveFailures;
use App\Filament\Resources\Concerns\ManagesBlockEditorIdentity;
use App\Filament\Resources\Concerns\WarnsAboutMultiplePageHeadings;
use App\Filament\Resources\PageResource;
use App\Models\Page;
use App\Models\Revision;
use App\Services\EditorHistory\SessionHistory;
use App\Services\Revisions\PageRevisionSnapshot;
use App\Services\Revisions\RevisionService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EditPage extends EditRecord
{
    use LogsHeroV2SaveFailures;
    use ManagesBlockEditorIdentity;
    use WarnsAboutMultiplePageHeadings;

    protected static string $resource = PageResource::class;

    protected array $pageEditPerfQueries = [];

    protected int $pageEditPerfStartedAt = 0;

    public array $sessionHistoryEntries = [];

    public int $sessionHistoryPointer = 0;

    public string $historySessionId = '';

    public bool $sessionHistoryAvailable = true;

    public ?string $sessionHistoryNotice = null;

    public ?int $selectedRevisionId = null;

    public ?int $restoredFromRevisionId = null;

    public bool $restoringEditorHistory = false;

    public int $revisionListLimit = 30;

    public function boot(): void
    {
        $this->pageEditPerfStartedAt = hrtime(true);
        $this->pageEditPerfQueries = [];

        DB::listen(function ($query): void {
            $this->pageEditPerfQueries[] = [
                'sql' => preg_replace('/\s+/', ' ', $query->sql),
                'bindings' => $query->bindings,
                'time' => $query->time,
            ];
        });
    }

    public function dehydrate(): void
    {
        $this->captureEditorHistory();

        $rawState = $this->form->getRawState();
        $queryTime = array_sum(array_column($this->pageEditPerfQueries, 'time'));
        $duplicates = collect($this->pageEditPerfQueries)
            ->map(fn (array $query): string => $query['sql'].' | '.json_encode($query['bindings']))
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->sortDesc()
            ->take(10)
            ->all();

        Log::info('PERF PageResource edit: query count', [
            'count' => count($this->pageEditPerfQueries),
            'duplicates' => $duplicates,
        ]);

        Log::info('PERF PageResource edit: total query time', [
            'ms' => round($queryTime, 2),
            'request_ms' => $this->pageEditPerfStartedAt > 0
                ? round((hrtime(true) - $this->pageEditPerfStartedAt) / 1_000_000, 2)
                : null,
        ]);

        Log::info('PERF PageResource edit: Livewire state bytes', [
            'raw_state_bytes' => strlen(json_encode($rawState)),
            'content_bytes' => strlen((string) data_get($rawState, 'content')),
            'blocks_json_bytes' => strlen(json_encode(data_get($rawState, 'blocks', []))),
            'block_count' => count(data_get($rawState, 'blocks', []) ?? []),
        ]);
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-page-editor-locked-scroll',
        ];
    }

    protected function afterFill(): void
    {
        $this->historySessionId = (string) Str::uuid();

        try {
            $this->authorizeHistoryAccess();
            $state = app(SessionHistory::class)->initial(...$this->historyArguments(), state: $this->form->getRawState());
            $this->syncHistoryClientState($state);
        } catch (Throwable $exception) {
            $this->disableSessionHistory($exception);
        }
    }

    public function updatedData(mixed $value, ?string $key = null): void
    {
        $this->captureEditorHistory($key);
    }

    public function applyEditorHistoryCheckpoint(int $index): void
    {
        $this->authorizeHistoryAccess();
        $checkpointId = $this->sessionHistoryEntries[$index]['id'] ?? null;

        if (! is_string($checkpointId)) {
            $this->markSessionHistoryExpired();

            return;
        }

        try {
            $state = app(SessionHistory::class)->state(...$this->historyArguments(), checkpointId: $checkpointId);

            if ($state === null) {
                $this->markSessionHistoryExpired();

                return;
            }

            $this->restoringEditorHistory = true;

            try {
                $this->form->fill($state);
                $clientState = app(SessionHistory::class)->movePointer(...$this->historyArguments(), checkpointId: $checkpointId);
                $this->syncHistoryClientState($clientState);
                $this->restoredFromRevisionId = null;
            } finally {
                $this->restoringEditorHistory = false;
            }
        } catch (Throwable $exception) {
            $this->disableSessionHistory($exception);
        }
    }

    public function undoEditorHistory(): void
    {
        if ($this->canUndoEditorHistory()) {
            $this->applyEditorHistoryCheckpoint($this->sessionHistoryPointer - 1);
        }
    }

    public function redoEditorHistory(): void
    {
        if ($this->canRedoEditorHistory()) {
            $this->applyEditorHistoryCheckpoint($this->sessionHistoryPointer + 1);
        }
    }

    public function canUndoEditorHistory(): bool
    {
        return $this->sessionHistoryAvailable && $this->sessionHistoryPointer > 0;
    }

    public function canRedoEditorHistory(): bool
    {
        return $this->sessionHistoryAvailable && $this->sessionHistoryPointer < count($this->sessionHistoryEntries) - 1;
    }

    public function revisionRows(): array
    {
        /** @var Page $page */
        $page = $this->record;
        $currentChecksum = app(PageRevisionSnapshot::class)->checksum(
            app(PageRevisionSnapshot::class)->fromPage($page->fresh()),
        );

        return app(RevisionService::class)->latestForPage($page, $this->revisionListLimit)->map(fn (Revision $revision): array => [
            'id' => $revision->getKey(),
            'number' => $revision->revision_number,
            'actor' => $revision->creator?->name ?? 'سیستم',
            'relative' => $revision->created_at->diffForHumans(),
            'date' => $revision->created_at->translatedFormat('Y/m/d H:i'),
            'current' => hash_equals($revision->checksum, $currentChecksum),
            'restored_from' => $revision->restoredFrom?->revision_number,
        ])->all();
    }

    public function hasMoreRevisions(): bool
    {
        return $this->record->revisions()->count() > $this->revisionListLimit;
    }

    public function loadMoreRevisions(): void
    {
        $this->revisionListLimit = min($this->revisionListLimit + 30, 300);
    }

    public function selectPageRevision(int $revisionId): void
    {
        $this->pageRevision($revisionId, includeSnapshot: false);
        $this->selectedRevisionId = $revisionId;
    }

    public function applySelectedPageRevision(): void
    {
        abort_unless($this->selectedRevisionId, 422);
        $revision = $this->pageRevision($this->selectedRevisionId);
        $current = $this->form->getRawState();
        $snapshot = $this->mutateFormDataBeforeFill($revision->snapshot);

        $this->restoringEditorHistory = true;

        try {
            $this->form->fill(array_replace($current, $snapshot));
            $this->restoredFromRevisionId = $revision->getKey();
        } finally {
            $this->restoringEditorHistory = false;
        }

        $this->captureEditorHistory('revision');

        try {
            $state = app(SessionHistory::class)->relabelLatest(
                ...$this->historyArguments(),
                label: "اعمال رونوشت شماره {$revision->revision_number}",
                kind: 'revision',
            );
            $this->syncHistoryClientState($state);
        } catch (Throwable $exception) {
            $this->disableSessionHistory($exception);
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data): Model {
                /** @var Page $locked */
                $locked = Page::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
                $snapshots = app(PageRevisionSnapshot::class);
                $before = $snapshots->fromPage($locked);

                $locked->update($data);

                app(RevisionService::class)->recordPageSave(
                    $locked,
                    $before,
                    $snapshots->fromPage($locked->fresh()),
                    Auth::id(),
                    $this->restoredFromRevisionId,
                );

                $this->restoredFromRevisionId = null;

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            $this->logHeroV2Failure('update', $exception);

            throw $exception;
        }
    }

    private function pageRevision(int $revisionId, bool $includeSnapshot = true): Revision
    {
        $query = Revision::query()
            ->whereKey($revisionId)
            ->where('revisionable_type', $this->record->getMorphClass())
            ->where('revisionable_id', $this->record->getKey());

        if (! $includeSnapshot) {
            $query->select(['id', 'revisionable_type', 'revisionable_id']);
        }

        return $query->firstOrFail();
    }

    private function captureEditorHistory(?string $field = null): void
    {
        if ($this->restoringEditorHistory || ! $this->sessionHistoryAvailable || $this->sessionHistoryEntries === []) {
            return;
        }

        try {
            $this->authorizeHistoryAccess();
            $state = app(SessionHistory::class)->capture(
                ...$this->historyArguments(),
                state: $this->form->getRawState(),
                field: $field,
                limit: max(10, min(100, (int) config('cms.page_editor_history_limit', 60))),
                maxBytes: max(1_048_576, (int) config('cms.page_editor_history_max_bytes', 6 * 1024 * 1024)),
            );

            if ($state === null) {
                $this->markSessionHistoryExpired();

                return;
            }

            $this->syncHistoryClientState($state);
        } catch (Throwable $exception) {
            $this->disableSessionHistory($exception);
        }
    }

    private function historyArguments(): array
    {
        return [
            'userId' => (int) Auth::id(),
            'pageId' => (int) $this->record->getKey(),
            'sessionId' => $this->historySessionId,
        ];
    }

    private function syncHistoryClientState(?array $state): void
    {
        if ($state === null) {
            $this->markSessionHistoryExpired();

            return;
        }

        $this->sessionHistoryEntries = $state['entries'];
        $this->sessionHistoryPointer = $state['pointer'];
        $this->sessionHistoryAvailable = true;
        $this->sessionHistoryNotice = null;
    }

    private function authorizeHistoryAccess(): void
    {
        abort_unless(Auth::check() && PageResource::canEdit($this->record), 403);
    }

    private function markSessionHistoryExpired(): void
    {
        $this->sessionHistoryAvailable = false;
        $this->sessionHistoryEntries = [];
        $this->sessionHistoryPointer = 0;
        $this->sessionHistoryNotice = 'عملیات تاریخچه دیگر در دسترس نیست.';
    }

    private function disableSessionHistory(Throwable $exception): void
    {
        Log::warning('Temporary page editor history is unavailable.', [
            'page_id' => $this->record?->getKey(),
            'user_id' => Auth::id(),
            'exception' => $exception::class,
        ]);
        $this->markSessionHistoryExpired();
    }

    protected function afterSave(): void
    {
        PageResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('history')
                ->label('تاریخچه')
                ->icon('heroicon-o-clock')
                ->modalHeading('تاریخچه')
                ->modalContent(fn () => view('filament.pages.page-history'))
                ->slideOver()
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('بستن'),
            Action::make('preview')
                ->label('پیش‌نمایش')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('admin.preview.pages.show', $this->record))
                ->openUrlInNewTab(),
            Action::make('viewPublic')
                ->label('مشاهده برگه')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => PageResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => PageResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make()
                ->label('حذف')
                ->modalHeading('حذف برگه')
                ->modalSubmitActionLabel('حذف')
                ->modalCancelActionLabel('انصراف'),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('ذخیره تغییرات');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'برگه ذخیره شد';
    }
}
