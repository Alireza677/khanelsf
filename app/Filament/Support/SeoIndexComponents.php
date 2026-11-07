<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SeoIndexComponents
{
    public static function column(): TextColumn
    {
        return TextColumn::make('robots_index')
            ->label('ایندکس')
            // Match SeoService::make(): a missing/null preference allows indexing.
            ->getStateUsing(fn (Model $record): bool => $record->robots_index ?? true)
            ->formatStateUsing(fn (bool $state): string => $state ? 'Index' : 'Noindex')
            ->badge()
            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
            ->alignment('start')
            ->extraAttributes(['class' => 'whitespace-nowrap'])
            ->tooltip('اجازه ایندکس طبق تنظیمات سئو؛ بیانگر ثبت واقعی در گوگل نیست.')
            ->toggleable()
            ->sortable(query: function (Builder $query, string $direction): Builder {
                $column = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn('robots_index'));

                return $query->orderByRaw('COALESCE('.$column.', 1) '.($direction === 'desc' ? 'desc' : 'asc'));
            });
    }

    public static function filter(): TernaryFilter
    {
        return TernaryFilter::make('robots_index')
            ->label('ایندکس')
            ->placeholder('همه')
            ->trueLabel('Index')
            ->falseLabel('Noindex')
            ->queries(
                true: fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->where($query->qualifyColumn('robots_index'), true)
                        ->orWhereNull($query->qualifyColumn('robots_index')),
                ),
                false: fn (Builder $query): Builder => $query->where($query->qualifyColumn('robots_index'), false),
            );
    }

    public static function toggle(): Toggle
    {
        return Toggle::make('robots_index')
            ->default(true)
            ->afterStateHydrated(function (Toggle $component, mixed $state): void {
                $component->state($state ?? true);
            })
            // Preserve a legacy null on unrelated saves, while displaying its real default.
            ->dehydrateStateUsing(fn (bool $state, ?Model $record): ?bool => $record && $record->robots_index === null && $state ? null : $state);
    }
}
