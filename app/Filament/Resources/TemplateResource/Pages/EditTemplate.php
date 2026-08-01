<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\Concerns\LogsHeroV2SaveFailures;
use App\Filament\Resources\Concerns\ManagesBlockEditorIdentity;
use App\Filament\Resources\Concerns\ValidatesTemplatePublication;
use App\Filament\Resources\TemplateResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditTemplate extends EditRecord
{
    use LogsHeroV2SaveFailures;
    use ManagesBlockEditorIdentity {
        mutateFormDataBeforeSave as prepareBlockDataBeforeSave;
    }
    use ValidatesTemplatePublication;

    protected static string $resource = TemplateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->validateTemplatePublication(
            $this->prepareBlockDataBeforeSave($data),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->form(fn (): array => $this->previewForm())
                ->modalSubmitActionLabel('Open preview')
                ->action(function (array $data) {
                    return redirect()->away(route('admin.preview.templates.show', [
                        'template' => $this->record,
                        'context_id' => $data['context_id'] ?? null,
                    ]));
                }),
            Actions\DeleteAction::make(),
        ];
    }

    private function previewForm(): array
    {
        $options = TemplateResource::previewContextOptions($this->record->type);

        if ($options === []) {
            return [
                Forms\Components\Placeholder::make('preview_note')
                    ->label('Preview context')
                    ->content('This template type does not need a selected item.'),
            ];
        }

        return [
            Forms\Components\Select::make('context_id')
                ->label(TemplateResource::previewContextLabel($this->record->type))
                ->options($options)
                ->searchable()
                ->preload()
                ->required()
                ->helperText('Select a real record to provide context for dynamic template blocks.'),
        ];
    }
}
