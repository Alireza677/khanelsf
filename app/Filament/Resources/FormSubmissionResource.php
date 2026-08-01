<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\FormSubmissionResource\Pages;
use App\Models\FormSubmission;
use App\Services\FormSubmissionPresenter;
use App\Services\FormSubmissionSubmitterResolver;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FormSubmissionResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = FormSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'crm/form-entries';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['form', 'lead', 'page']);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->recordUrl(fn (FormSubmission $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),
                Tables\Columns\TextColumn::make('form.name')->label('نام فرم')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('submitter_name')
                    ->label('ارسال‌کننده')
                    ->state(fn (FormSubmission $record): string => app(FormSubmissionSubmitterResolver::class)->resolve($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('payload', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('source')
                    ->label('منبع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => static::sourceLabels()[$state] ?? $state ?? '—'),
                Tables\Columns\TextColumn::make('entry_status')
                    ->label('وضعیت')
                    ->badge()
                    ->state(fn (FormSubmission $record): string => $record->lead?->status ?? 'received')
                    ->formatStateUsing(fn (string $state): string => static::statusLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('submitted_at')->label('تاریخ ارسال')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('form_id')
                    ->label('فرم')
                    ->relationship('form', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('source')
                    ->label('منبع')
                    ->options(static::sourceLabels()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('جزئیات'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('خلاصه ورودی')
                ->schema([
                    Infolists\Components\TextEntry::make('form.name')->label('فرم')->placeholder('—'),
                    Infolists\Components\TextEntry::make('submitted_at')->label('تاریخ ارسال')->dateTime(),
                    Infolists\Components\TextEntry::make('source')
                        ->label('منبع')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => static::sourceLabels()[$state] ?? $state ?? '—'),
                    Infolists\Components\TextEntry::make('entry_status')
                        ->label('وضعیت')
                        ->state(fn (FormSubmission $record): string => $record->lead?->status ?? 'received')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => static::statusLabels()[$state] ?? $state),
                    Infolists\Components\TextEntry::make('page.title')
                        ->label('برگه')
                        ->visible(fn (FormSubmission $record): bool => filled($record->page)),
                    Infolists\Components\TextEntry::make('page_url')
                        ->label('نشانی صفحه')
                        ->visible(fn (FormSubmission $record): bool => filled($record->page_url)),
                ])
                ->columns(2),
            Infolists\Components\Section::make('اطلاعات ارسال‌کننده')
                ->schema([
                    Infolists\Components\TextEntry::make('submitter_name')
                        ->label('نام نمایشی')
                        ->state(fn (FormSubmission $record): string => app(FormSubmissionSubmitterResolver::class)->resolve($record)),
                    Infolists\Components\TextEntry::make('submitter_email')
                        ->label('ایمیل')
                        ->state(fn (FormSubmission $record): ?string => app(FormSubmissionSubmitterResolver::class)->email($record))
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('submitter_phone')
                        ->label('تلفن')
                        ->state(fn (FormSubmission $record): ?string => app(FormSubmissionSubmitterResolver::class)->phone($record))
                        ->placeholder('—'),
                ])
                ->columns(3),
            Infolists\Components\Section::make('سرنخ مرتبط')
                ->schema([
                    Infolists\Components\TextEntry::make('related_lead')
                        ->label('سرنخ')
                        ->state(fn (FormSubmission $record): string => $record->lead?->name
                            ?? $record->lead?->email
                            ?? $record->lead?->phone
                            ?? 'مشاهده سرنخ')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('primary')
                        ->url(fn (FormSubmission $record): ?string => $record->lead
                            ? LeadResource::getUrl('view', ['record' => $record->lead])
                            : null)
                        ->visible(fn (FormSubmission $record): bool => filled($record->lead)),
                    Infolists\Components\TextEntry::make('no_related_lead')
                        ->hiddenLabel()
                        ->state('سرنخ مرتبطی برای این ورودی وجود ندارد.')
                        ->color('gray')
                        ->visible(fn (FormSubmission $record): bool => blank($record->lead)),
                ]),
            Infolists\Components\Section::make('پاسخ‌ها')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('readable_answers')
                        ->hiddenLabel()
                        ->state(fn (FormSubmission $record): array => app(FormSubmissionPresenter::class)->answers($record))
                        ->schema([
                            Infolists\Components\TextEntry::make('label')->label('فیلد'),
                            Infolists\Components\TextEntry::make('value')->label('پاسخ')->placeholder('—'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->visible(fn (FormSubmission $record): bool => app(FormSubmissionPresenter::class)->answers($record) !== []),
            Infolists\Components\Section::make('اطلاعات فنی')
                ->schema([
                    Infolists\Components\TextEntry::make('id')->label('شناسه داخلی ورودی'),
                    Infolists\Components\TextEntry::make('block_id')
                        ->label('شناسه داخلی بلوک')
                        ->placeholder('—'),
                    Infolists\Components\KeyValueEntry::make('raw_fields')
                        ->label('داده‌های خام ثبت‌شده')
                        ->state(fn (FormSubmission $record): array => app(FormSubmissionPresenter::class)->rawFields($record))
                        ->keyLabel('کلید فیلد')
                        ->valueLabel('مقدار')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible()
                ->collapsed(),
            Infolists\Components\Section::make('نتیجه محاسبه')
                ->schema([
                    Infolists\Components\TextEntry::make('calculation_result.result')
                        ->label('خلاصه')
                        ->placeholder('—'),
                    Infolists\Components\RepeatableEntry::make('calculation_scores')
                        ->label('جزئیات محاسبه')
                        ->state(fn (FormSubmission $record): array => app(FormSubmissionPresenter::class)->calculationScores($record))
                        ->schema([
                            Infolists\Components\TextEntry::make('label')->label('عنوان'),
                            Infolists\Components\TextEntry::make('value')->label('مقدار'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->visible(fn (FormSubmission $record): bool => app(FormSubmissionPresenter::class)->calculationResult($record) !== []),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormSubmissions::route('/'),
            'view' => Pages\ViewFormSubmission::route('/{record}'),
        ];
    }

    private static function sourceLabels(): array
    {
        return [
            'website' => 'وب‌سایت',
            'manual' => 'دستی',
        ];
    }

    private static function statusLabels(): array
    {
        return [
            'received' => 'دریافت‌شده',
            'new' => 'جدید',
            'contacted' => 'تماس گرفته‌شده',
            'qualified' => 'واجد شرایط',
            'closed' => 'بسته‌شده',
            'archived' => 'بایگانی‌شده',
        ];
    }
}
