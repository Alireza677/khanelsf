<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\Concerns\HasStickyFormActions;
use App\Filament\Resources\Concerns\LogsHeroV2SaveFailures;
use App\Filament\Resources\Concerns\ManagesBlockEditorIdentity;
use App\Filament\Resources\Concerns\ValidatesTemplatePublication;
use App\Filament\Resources\TemplateResource;
use App\Models\Template;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;

class EditTemplate extends EditRecord
{
    use HasStickyFormActions;
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
                ->label(fn (): string => $this->record->type === 'site_footer' ? 'پیش‌نمایش آخرین نسخه ذخیره‌شده' : __('Preview'))
                ->icon('heroicon-o-eye')
                ->visible(fn (): bool => ! in_array($this->record->type, Template::LEGACY_GALLERY_TYPES, true))
                ->form(fn (): array => $this->previewForm())
                ->modalSubmitActionLabel(__('Open preview'))
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
                    ->label(__('Preview context'))
                    ->content($this->record->type === 'site_footer'
                        ? 'این پیش‌نمایش با Runtime اصلی فوتر از آخرین نسخه ذخیره‌شده ساخته می‌شود. تغییرات ذخیره‌نشده فرم در آن نیستند؛ ابتدا ذخیره کنید. بازکردن پیش‌نمایش چیزی را ذخیره یا منتشر نمی‌کند.'
                        : __('This template type does not need a selected item.')),
            ];
        }

        return [
            Forms\Components\Select::make('context_id')
                ->label(TemplateResource::previewContextLabel($this->record->type))
                ->options($options)
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('Select a real record to provide context for dynamic template blocks.')),
        ];
    }
}
