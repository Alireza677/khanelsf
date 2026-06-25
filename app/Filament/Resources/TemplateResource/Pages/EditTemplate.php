<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditTemplate extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = TemplateResource::class;

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
