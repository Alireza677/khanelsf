<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use App\Filament\Resources\TemplateResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListTemplates extends ListRecords
{
    protected static string $resource = TemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('createFromRecipe')
                ->label('ساخت از Recipe')
                ->icon('heroicon-o-sparkles')
                ->form([
                    Forms\Components\Select::make('recipe')
                        ->label('Recipe')
                        ->options(fn (): array => collect(app(TemplateRecipeRegistry::class)->all())
                            ->mapWithKeys(fn ($recipe, string $key): array => [
                                $key => "{$recipe->label()} — {$recipe->targetType()}",
                            ])
                            ->all())
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان Template')
                        ->maxLength(255)
                        ->helperText('اگر خالی باشد، عنوان پیش‌فرض Recipe استفاده می‌شود.'),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(255)
                        ->helperText('در صورت تکراری بودن، suffix امن اضافه می‌شود.'),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default برای این target')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $template = app(TemplateRecipeInstantiator::class)->createDraft(
                        $data['recipe'],
                        [
                            'title' => $data['title'] ?? null,
                            'slug' => $data['slug'] ?? null,
                            'is_default' => (bool) ($data['is_default'] ?? false),
                            'conditions' => ['type' => 'all'],
                        ],
                    );

                    return redirect(TemplateResource::getUrl('edit', ['record' => $template]));
                }),
            Actions\CreateAction::make(),
        ];
    }
}
