<?php

namespace App\Filament\Support;

use App\Services\Calculators\CalculatorScoringSchema;
use App\Support\PersianDate;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

/** Standard Filament controls bound directly to the stored result-key × criterion-ID map. */
final class CalculatorDecisionReportEditor
{
    public static function section(): Section
    {
        return Section::make('گزارش توضیحی نتیجه')
            ->description('توضیح هر گزینه را متناسب با عملکرد آن در هر معیار بنویسید؛ توضیح می‌تواند درباره نقاط قوت یا ضعف باشد.')
            ->visible(fn ($livewire): bool => self::isWeighted($livewire))
            ->dehydratedWhenHidden()
            ->extraAttributes(['dir' => 'rtl', 'style' => 'min-width: 0;'])
            ->schema(function ($livewire): array {
                if (! self::isWeighted($livewire)) {
                    // No report controls/defaults in simple mode; retain any existing metadata verbatim.
                    $calculator = data_get($livewire, 'data.schema.calculator', []);

                    return is_array($calculator) && array_key_exists('decision_report', $calculator)
                        ? [Hidden::make('schema.calculator.decision_report')]
                        : [];
                }

                return [
                    // Dehydrate the entire report so non-editable top_factors_count is preserved.
                    Group::make([
                        Toggle::make('enabled')
                            ->label('فعال‌سازی گزارش توضیحی نتیجه')
                            ->helperText('با خاموش‌کردن این گزینه، توضیحات قبلی حفظ می‌شوند.')
                            ->dehydrateStateUsing(fn (mixed $state): bool => (bool) $state)
                            ->live(),
                        Placeholder::make('decision_report_empty')
                            ->statePath(null)
                            ->hiddenLabel()
                            ->content('برای نوشتن توضیحات، ابتدا حداقل یک نتیجه و یک معیار تعریف کنید.')
                            ->visible(fn ($livewire): bool => self::enabled($livewire)
                                && (self::results($livewire) === [] || self::criteria($livewire) === [])),
                        Tabs::make('توضیحات نتایج')
                            ->id('calculator-decision-report-results')
                            ->tabs(fn ($livewire): array => self::tabs($livewire))
                            ->visible(fn ($livewire): bool => self::enabled($livewire)
                                && self::results($livewire) !== [] && self::criteria($livewire) !== [])
                            ->dehydratedWhenHidden()
                            ->columnSpanFull(),
                    ])
                        ->statePath('schema.calculator.decision_report')
                        ->dehydratedWhenHidden()
                        ->columnSpanFull(),
                ];
            });
    }

    private static function tabs($livewire): array
    {
        $criteria = self::criteria($livewire);
        $tabs = [];
        foreach (self::results($livewire) as $resultKey => $resultLabel) {
            $fields = [];
            foreach ($criteria as $id => $label) {
                $fields[] = Textarea::make("explanations.{$resultKey}.{$id}")
                    ->label($label)
                    ->hint(fn ($livewire): string => self::performanceLabel($livewire, $resultKey, $id))
                    ->placeholder('توضیح دهید چرا این گزینه در این معیار عملکرد مناسب، متوسط یا ضعیفی دارد...')
                    ->rows(3)
                    ->live(onBlur: true)
                    ->extraInputAttributes(['dir' => 'rtl', 'aria-label' => "توضیح {$resultLabel} درباره {$label}"])
                    ->dehydratedWhenHidden()
                    ->columnSpanFull();
            }
            $tabs[] = Tab::make($resultLabel)
                ->id($resultKey)
                ->key("decision-report-result-{$resultKey}")
                ->badge(function ($livewire) use ($resultKey, $criteria): string {
                    $texts = data_get($livewire, "data.schema.calculator.decision_report.explanations.{$resultKey}", []);
                    $completed = 0;
                    foreach ($criteria as $id => $_label) {
                        $text = is_array($texts) ? ($texts[$id] ?? null) : null;
                        if (is_string($text) && trim($text) !== '') {
                            $completed++;
                        }
                    }

                    return PersianDate::digits($completed.' از '.count($criteria).' توضیح تکمیل شده');
                })
                ->schema($fields);
        }

        return $tabs;
    }

    private static function performanceLabel($livewire, string $resultKey, string $id): string
    {
        $score = data_get($livewire, "data.schema.calculator.criterion_scores.{$resultKey}.{$id}", 0);

        return 'امتیاز عملکرد: '.PersianDate::digits(is_numeric($score) ? (string) $score : '0').' از ۵';
    }

    private static function criteria($livewire): array
    {
        return CalculatorWeightedEditor::criteriaOptions(data_get($livewire, 'data.schema.calculator.criteria', []));
    }

    private static function results($livewire): array
    {
        return CalculatorWeightedEditor::resultOptions(data_get($livewire, 'data.schema.calculator.recommendations', []));
    }

    private static function isWeighted($livewire): bool
    {
        return data_get($livewire, 'data.type') === 'calculator'
            && data_get($livewire, 'data.schema.calculator.scoring_mode') === CalculatorScoringSchema::WEIGHTED;
    }

    private static function enabled($livewire): bool
    {
        return data_get($livewire, 'data.schema.calculator.decision_report.enabled') === true;
    }
}
