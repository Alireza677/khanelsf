<?php

namespace App\Filament\Resources\ClientProjectResource\RelationManagers;

use App\Filament\Resources\ClientProjectCycleResource;
use App\Models\ClientProject;
use App\Services\ClientProjectCycleReconciler;
use App\Services\ClientProjectCycleResolver;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeliveryCyclesRelationManager extends RelationManager
{
    protected static string $relationship = 'cycles';

    protected static ?string $title = 'دوره‌های تحویل و تعهدات';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof ClientProject && $ownerRecord->delivery_mode !== 'time' && auth()->user()?->can('update', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return ClientProjectCycleResource::table($table)->modifyQueryUsing(fn (Builder $query) => $query->whereIn('delivery_mode', ['tasks', 'hybrid']))->headerActions([
            Tables\Actions\Action::make('ensureCycle')->label('ثبت دوره برای تاریخ')->form([
                Forms\Components\DatePicker::make('reference')->label('تاریخ داخل دوره')->jalali()->default(today())->required(),
            ])->action(function (array $data): void {
                $project = $this->getOwnerRecord();
                Gate::authorize('update', $project);
                if (! app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse($data['reference']))) {
                    throw ValidationException::withMessages(['reference' => 'پروژه فعال و تاریخ داخل زمان‌بندی و قرارداد مؤثر را انتخاب کنید.']);
                }
            }),
            Tables\Actions\Action::make('reconcile')->label('همگام‌سازی امن')->action(function (): void {
                Gate::authorize('update', $this->getOwnerRecord());
                app(ClientProjectCycleReconciler::class)->reconcile($this->getOwnerRecord());
            }),
        ]);
    }
}
