<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Services\LeadSubmissionPresenter;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LeadResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $slug = 'crm/customers';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات سرنخ')
                ->schema([
                    Forms\Components\TextInput::make('name')->maxLength(255),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->required()
                        ->options([
                            'new' => 'جدید',
                            'contacted' => 'تماس گرفته‌شده',
                            'qualified' => 'واجد شرایط',
                            'closed' => 'بسته‌شده',
                            'archived' => 'بایگانی‌شده',
                        ])
                        ->default('new'),
                    Forms\Components\Textarea::make('notes')->label('پیام / یادداشت')->rows(6)->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('منبع')
                ->schema([
                    Forms\Components\Select::make('source')
                        ->required()
                        ->options(['website' => 'وب‌سایت', 'manual' => 'دستی'])
                        ->default('manual'),
                    Forms\Components\Select::make('form_id')
                        ->label('فرم')
                        ->relationship('form', 'name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('page_id')
                        ->label('صفحه')
                        ->relationship('page', 'title')
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('page_url')->label('نشانی صفحه')->maxLength(2048),
                    Forms\Components\TextInput::make('block_id')->label('شناسه بلاک / منبع')->maxLength(26),
                    Forms\Components\TextInput::make('form_submission_id')->label('شناسه ارسال فرم')->disabled()->dehydrated(false),
                ])
                ->columns(2),
            Forms\Components\Section::make('نتیجه محاسبه‌گر')
                ->schema([
                    Forms\Components\Placeholder::make('calculation_result.result')
                        ->label('پیشنهاد نهایی')
                        ->content(fn (?Lead $record): string => data_get($record, 'calculation_result.result', '—')),
                    Forms\Components\Placeholder::make('calculation_result.calculator_identifier')
                        ->label('شناسه محاسبه‌گر')
                        ->content(fn (?Lead $record): string => data_get($record, 'calculation_result.calculator_identifier', '—')),
                    Forms\Components\KeyValue::make('calculation_result.scores')
                        ->label('امتیازها')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Forms\Components\KeyValue::make('calculation_result.answer_labels')
                        ->label('پاسخ‌ها')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->visible(fn (?Lead $record): bool => filled($record?->calculation_result)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Lead $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('source')->badge()->sortable(),
                Tables\Columns\TextColumn::make('form.name')->label('فرم')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->jalaliDateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'new' => 'جدید',
                    'contacted' => 'تماس گرفته‌شده',
                    'qualified' => 'واجد شرایط',
                    'closed' => 'بسته‌شده',
                    'archived' => 'بایگانی‌شده',
                ]),
                Tables\Filters\SelectFilter::make('source')->options([
                    'website' => 'وب‌سایت',
                    'manual' => 'دستی',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('اطلاعات سرنخ')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label('نام')->placeholder('—'),
                    Infolists\Components\TextEntry::make('phone')->label('تلفن')->placeholder('—'),
                    Infolists\Components\TextEntry::make('email')->label('ایمیل')->placeholder('—'),
                    Infolists\Components\TextEntry::make('status')
                        ->label('وضعیت')
                        ->badge()
                        ->formatStateUsing(fn (?string $state): string => static::statusLabels()[$state] ?? $state ?? '—'),
                    Infolists\Components\TextEntry::make('notes')->label('پیام / یادداشت')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2),
            Infolists\Components\Section::make('اطلاعات فرم')
                ->schema([
                    Infolists\Components\TextEntry::make('form.name')->label('فرم')->placeholder('—'),
                    Infolists\Components\TextEntry::make('submission.submitted_at')
                        ->label('زمان ارسال')
                        ->jalaliDateTime()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('submission.source')
                        ->label('منبع')
                        ->state(fn (Lead $record): ?string => $record->submission?->source ?? $record->source)
                        ->formatStateUsing(fn (?string $state): string => static::sourceLabels()[$state] ?? $state ?? '—'),
                    Infolists\Components\TextEntry::make('page.title')
                        ->label('صفحه')
                        ->placeholder('—')
                        ->visible(fn (Lead $record): bool => filled($record->page)),
                    Infolists\Components\TextEntry::make('page_url')
                        ->label('نشانی صفحه')
                        ->state(fn (Lead $record): ?string => $record->submission?->page_url ?? $record->page_url)
                        ->placeholder('—')
                        ->visible(fn (Lead $record): bool => filled($record->submission?->page_url ?? $record->page_url)),
                    Infolists\Components\TextEntry::make('block_id')
                        ->label('شناسه منبع صفحه')
                        ->state(fn (Lead $record): ?string => $record->submission?->block_id ?? $record->block_id)
                        ->placeholder('—')
                        ->visible(fn (Lead $record): bool => filled($record->submission?->block_id ?? $record->block_id)),
                ])
                ->columns(2)
                ->visible(fn (Lead $record): bool => filled($record->submission) || filled($record->form)),
            Infolists\Components\Section::make('پاسخ‌های کاربر')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('submission_answers')
                        ->hiddenLabel()
                        ->state(fn (Lead $record): array => app(LeadSubmissionPresenter::class)->answers($record))
                        ->schema([
                            Infolists\Components\TextEntry::make('label')->label('فیلد'),
                            Infolists\Components\TextEntry::make('value')->label('پاسخ')->placeholder('—'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->visible(fn (Lead $record): bool => app(LeadSubmissionPresenter::class)->answers($record) !== []),
            Infolists\Components\Section::make('نتیجه محاسبه')
                ->schema([
                    Infolists\Components\TextEntry::make('calculation_result.result')
                        ->label('پیشنهاد نهایی')
                        ->state(fn (Lead $record): string => data_get(
                            app(LeadSubmissionPresenter::class)->calculationResult($record),
                            'result',
                            '—',
                        )),
                    Infolists\Components\RepeatableEntry::make('calculation_scores')
                        ->label('امتیاز نتایج')
                        ->state(fn (Lead $record): array => app(LeadSubmissionPresenter::class)->scores($record))
                        ->schema([
                            Infolists\Components\TextEntry::make('label')->label('نتیجه'),
                            Infolists\Components\TextEntry::make('value')->label('امتیاز'),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->visible(fn (Lead $record): bool => app(LeadSubmissionPresenter::class)->calculationResult($record) !== []),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }

    private static function statusLabels(): array
    {
        return [
            'new' => 'جدید',
            'contacted' => 'تماس گرفته‌شده',
            'qualified' => 'واجد شرایط',
            'closed' => 'بسته‌شده',
            'archived' => 'بایگانی‌شده',
        ];
    }

    private static function sourceLabels(): array
    {
        return [
            'website' => 'وب‌سایت',
            'manual' => 'دستی',
        ];
    }
}
