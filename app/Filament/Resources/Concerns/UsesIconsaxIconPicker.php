<?php

namespace App\Filament\Resources\Concerns;

use Filament\Forms;
use Illuminate\Support\Facades\Log;

trait UsesIconsaxIconPicker
{
    protected static function iconsaxIconPicker(string $name, ?string $label = null, ?string $helperText = null): Forms\Components\ViewField
    {
        $field = Forms\Components\ViewField::make($name)
            ->label($label ?? 'آیکن')
            ->view('filament.forms.components.iconsax-icon-picker')
            ->viewData(fn (): array => [
                'stylesheet' => asset('assets/iconsax/outline/style.css'),
                'selectionJsonUrl' => asset('assets/iconsax/outline/selection.json'),
            ]);

        if ($helperText !== null) {
            $field->helperText($helperText);
        }

        return $field;
    }

    protected static function iconsaxIconSizeInput(string $name = 'icon_size', ?string $label = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->label($label ?? 'سایز')
            ->numeric()
            ->minValue(1)
            ->maxValue(240)
            ->suffix('px')
            ->placeholder('24')
            ->extraInputAttributes(['style' => 'max-width: 5rem'])
            ->columnSpan(1);
    }

    protected static function iconsaxIconItems(): array
    {
        static $icons = null;

        if ($icons !== null) {
            return $icons;
        }

        $startedAt = hrtime(true);
        $path = public_path('assets/iconsax/outline/selection.json');

        if (! is_file($path)) {
            return $icons = [];
        }

        $selection = json_decode((string) file_get_contents($path), true);
        $prefix = $selection['preferences']['fontPref']['prefix'] ?? 'icon-';

        $icons = collect($selection['icons'] ?? [])
            ->map(function (array $icon) use ($prefix): ?array {
                $name = $icon['properties']['name'] ?? null;

                if (blank($name)) {
                    return null;
                }

                $tags = collect($icon['icon']['tags'] ?? [])
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'name' => $name,
                    'class' => $prefix.$name,
                    'tags' => $tags,
                    'search' => trim($name.' '.implode(' ', $tags)),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (request()->routeIs('filament.admin.resources.pages.edit')) {
            Log::info('PERF PageResource edit: icon picker load ms', [
                'ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
                'icons' => count($icons),
                'selection_json_bytes' => filesize($path) ?: null,
            ]);
        }

        return $icons;
    }
}
