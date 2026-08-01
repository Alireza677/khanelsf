<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\Concerns\LogsHeroV2SaveFailures;
use App\Filament\Resources\Concerns\ManagesBlockEditorIdentity;
use App\Filament\Resources\Concerns\WarnsAboutMultiplePageHeadings;
use App\Filament\Resources\PageResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditPage extends EditRecord
{
    use LogsHeroV2SaveFailures;
    use ManagesBlockEditorIdentity;
    use WarnsAboutMultiplePageHeadings;

    protected static string $resource = PageResource::class;

    protected array $pageEditPerfQueries = [];

    protected int $pageEditPerfStartedAt = 0;

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
