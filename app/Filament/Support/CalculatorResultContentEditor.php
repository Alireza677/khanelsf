<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class CalculatorResultContentEditor
{
    public static function section(): Section
    {
        return Section::make('محتوای نتایج')
            ->description('محتوای ثابت هر نتیجه در هر دو روش امتیازدهی نمایش داده می‌شود. نتایج از گزینه‌های محاسبه‌گر خوانده می‌شوند.')
            ->visible(fn ($livewire): bool => data_get($livewire, 'data.type') === 'calculator')
            ->dehydratedWhenHidden()
            ->schema([
                Group::make()->statePath('schema.calculator.result_content')
                    ->schema(function ($livewire): array {
                        $results = CalculatorWeightedEditor::resultOptions(data_get($livewire, 'data.schema.calculator.recommendations', []));
                        if ($results === []) {
                            return [Placeholder::make('empty_results')->statePath(null)->hiddenLabel()
                                ->content('ابتدا گزینه‌های نتیجه محاسبه‌گر را تعریف کنید.')];
                        }

                        $sections = [];
                        foreach ($results as $key => $label) {
                            $sections[] = Section::make($label)
                                ->key("result-content-{$key}")
                                ->statePath($key)
                                ->collapsible()
                                ->schema([
                                    TextInput::make('result_title')->label('عنوان نتیجه')->placeholder($label),
                                    Textarea::make('result_summary')->label('خلاصه کوتاه')->rows(2),
                                    Textarea::make('result_description')->label('توضیحات نتیجه')->rows(4),
                                    Textarea::make('result_note')->label('نکته / هشدار پایانی')->rows(2),
                                ]);
                        }

                        return $sections;
                    }),
            ]);
    }
}
