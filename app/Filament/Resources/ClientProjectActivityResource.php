<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientProjectActivityResource\Pages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\User;
use App\Services\DurationFormatter;
use Carbon\CarbonImmutable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientProjectActivityResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ClientProjectActivity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $slug = 'client-portal/activities';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('ثبت فعالیت')->schema([
                Forms\Components\Select::make('client_project_id')
                    ->label('پروژه مشتری')
                    ->relationship('project', 'title', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->whereHas('customer', fn (Builder $query): Builder => $query->where('status', Customer::STATUS_ACTIVE)))
                    ->searchable()->preload()->required(),
                Forms\Components\DatePicker::make('activity_date')->label('تاریخ فعالیت')->default(today())->required(),
                Forms\Components\TextInput::make('title')->label('عنوان')->required()->maxLength(255),
                Forms\Components\Select::make('performed_by')->label('ثبت‌کننده / اجراکننده')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()->default(fn (): ?int => auth()->id()),
                Forms\Components\TextInput::make('duration_hours')->label('مدت — ساعت')
                    ->numeric()->minValue(0)->maxValue(24)->default(0)->required(),
                Forms\Components\TextInput::make('duration_remainder_minutes')->label('مدت — دقیقه')
                    ->numeric()->minValue(0)->maxValue(59)->default(0)->required(),
                Forms\Components\DateTimePicker::make('started_at')->label('زمان شروع اختیاری')->seconds(false),
                Forms\Components\DateTimePicker::make('ended_at')->label('زمان پایان اختیاری')->seconds(false),
                Forms\Components\Select::make('visibility')->label('نمایش')
                    ->options(self::visibilityOptions())->default(ClientProjectActivity::VISIBILITY_INTERNAL)->required(),
                Forms\Components\Select::make('status')->label('وضعیت')
                    ->options(self::statusOptions())->default(ClientProjectActivity::STATUS_DRAFT)->required(),
                Forms\Components\Textarea::make('description')->label('توضیحات قابل نمایش به مشتری')->rows(5)->columnSpanFull(),
                Forms\Components\Textarea::make('internal_notes')->label('یادداشت داخلی — خصوصی')
                    ->helperText('این یادداشت هرگز در پرتال مشتری نمایش داده نمی‌شود.')
                    ->rows(5)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('activity_date')->label('تاریخ')->date()->sortable(),
            Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
            Tables\Columns\TextColumn::make('project.title')->label('پروژه')->searchable(),
            Tables\Columns\TextColumn::make('project.customer.display_name')->label('مشتری')->searchable(),
            Tables\Columns\TextColumn::make('duration_minutes')->label('مدت')->formatStateUsing(fn (int $state): string => app(DurationFormatter::class)->format($state)),
            Tables\Columns\TextColumn::make('visibility')->label('نمایش')->badge()->formatStateUsing(fn (string $state): string => self::visibilityOptions()[$state] ?? $state),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
        ])->filters([
            Tables\Filters\SelectFilter::make('customer')->label('مشتری')->options(Customer::query()->orderBy('display_name')->pluck('display_name', 'id')->all())
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'], fn (Builder $query, $id): Builder => $query->forCustomer((int) $id))),
            Tables\Filters\SelectFilter::make('client_project_id')->label('پروژه')->relationship('project', 'title')->searchable()->preload(),
            Tables\Filters\SelectFilter::make('visibility')->label('نمایش')->options(self::visibilityOptions()),
            Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(self::statusOptions()),
            Filter::make('month')->label('ماه')->form([Forms\Components\TextInput::make('month')->label('ماه میلادی')->placeholder('2026-08')->regex('/^\d{4}-(0[1-9]|1[0-2])$/')])
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['month'] ?? null, fn (Builder $query, string $month): Builder => $query->inMonth(CarbonImmutable::createFromFormat('!Y-m', $month)))),
        ])->actions([
            Tables\Actions\ViewAction::make()->label('مشاهده'),
            Tables\Actions\EditAction::make()->label('ویرایش'),
        ])->bulkActions([])->defaultSort('activity_date', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('اطلاعات فعالیت')->schema([
                Infolists\Components\TextEntry::make('project.title')->label('پروژه'),
                Infolists\Components\TextEntry::make('project.customer.display_name')->label('مشتری'),
                Infolists\Components\TextEntry::make('activity_date')->label('تاریخ')->date(),
                Infolists\Components\TextEntry::make('title')->label('عنوان'),
                Infolists\Components\TextEntry::make('duration_minutes')->label('مدت')->formatStateUsing(fn (int $state): string => app(DurationFormatter::class)->format($state)),
                Infolists\Components\TextEntry::make('performedBy.name')->label('اجراکننده')->placeholder('—'),
                Infolists\Components\TextEntry::make('description')->label('توضیحات مشتری')->placeholder('—')->columnSpanFull(),
                Infolists\Components\TextEntry::make('internal_notes')->label('یادداشت داخلی — خصوصی')->placeholder('—')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientProjectActivities::route('/'),
            'create' => Pages\CreateClientProjectActivity::route('/create'),
            'view' => Pages\ViewClientProjectActivity::route('/{record}'),
            'edit' => Pages\EditClientProjectActivity::route('/{record}/edit'),
        ];
    }

    public static function durationFormState(int $minutes): array
    {
        return ['duration_hours' => intdiv($minutes, 60), 'duration_remainder_minutes' => $minutes % 60];
    }

    public static function applyDurationFormState(array $data): array
    {
        $data['duration_minutes'] = ((int) ($data['duration_hours'] ?? 0) * 60) + (int) ($data['duration_remainder_minutes'] ?? 0);
        unset($data['duration_hours'], $data['duration_remainder_minutes']);

        return $data;
    }

    public static function visibilityOptions(): array
    {
        return ['client' => 'قابل نمایش به مشتری', 'internal' => 'داخلی'];
    }

    public static function statusOptions(): array
    {
        return ['draft' => 'پیش‌نویس', 'published' => 'منتشرشده', 'cancelled' => 'لغوشده'];
    }
}
