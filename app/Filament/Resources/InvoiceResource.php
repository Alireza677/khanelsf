<?php

namespace App\Filament\Resources;

use App\Enums\ClientProjectCycleStatus;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\ItemsRelationManager;
use App\Jobs\GenerateInvoicePdf;
use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceLifecycle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationGroup = 'پرتال مشتریان';

    protected static ?string $navigationLabel = 'فاکتورها';

    protected static ?string $modelLabel = 'فاکتور';

    protected static ?string $pluralModelLabel = 'فاکتورها';

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $slug = 'client-portal/invoices';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بازیابی فاکتور از دوره تکمیل‌شده')->schema([
                Forms\Components\Select::make('customer_id')->label('مشتری')->options(Customer::query()->orderBy('display_name')->pluck('display_name', 'id'))->searchable()->preload()->required()->live()->disabledOn('edit'),
                Forms\Components\Select::make('client_project_id')->label('پروژه')->options(fn (Forms\Get $get) => ClientProject::query()->where('customer_id', $get('customer_id'))->pluck('title', 'id'))->required()->live()->visibleOn('create'),
                Forms\Components\Select::make('client_project_cycle_id')->label('دوره تکمیل‌شده بدون فاکتور')->options(fn (Forms\Get $get) => ClientProjectCycle::query()->where('client_project_id', $get('client_project_id'))->where('status', ClientProjectCycleStatus::Completed)->whereDoesntHave('invoice')->get()->mapWithKeys(fn ($cycle) => [$cycle->id => $cycle->starts_at->format('Y-m-d').' تا '.$cycle->ends_at->format('Y-m-d')]))->required()->visibleOn('create'),
                Forms\Components\TextInput::make('discount_amount')->label('تخفیف')->numeric()->minValue(0)->default(0)->visibleOn('edit'),
                Forms\Components\TextInput::make('tax_amount')->label('مالیات')->numeric()->minValue(0)->default(0)->visibleOn('edit'),
                Forms\Components\DateTimePicker::make('due_at')->jalali()->label('سررسید')->visibleOn('edit'),
                Forms\Components\Textarea::make('notes')->label('یادداشت مشتری')->columnSpanFull()->visibleOn('edit'),
                Forms\Components\Textarea::make('internal_notes')->label('یادداشت داخلی')->columnSpanFull()->visibleOn('edit'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('invoice_number')->label('شماره')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('customer.display_name')->label('مشتری')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('period_start')->label('دوره')->formatStateUsing(fn ($state, Invoice $record) => $state->format('Y-m-d').' تا '.$record->period_end->format('Y-m-d')),
            Tables\Columns\TextColumn::make('total_amount')->label('مبلغ نهایی')->formatStateUsing(fn ($state, Invoice $record) => number_format((float) $state).' '.$record->currency),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (InvoiceStatus $state) => $state->label()),
            Tables\Columns\TextColumn::make('issued_at')->label('صدور')->jalaliDateTime()->placeholder('—'),
            Tables\Columns\TextColumn::make('paid_at')->label('پرداخت')->jalaliDateTime()->placeholder('—'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(InvoiceStatus::options()),
            Tables\Filters\SelectFilter::make('customer_id')->label('مشتری')->relationship('customer', 'display_name')->searchable()->preload(),
        ])->actions([
            Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft),
            static::issueAction(), static::paidAction(), static::cancelAction(),
            Tables\Actions\Action::make('pdf')->label(fn (Invoice $record) => $record->pdf_status === 'ready' ? 'دانلود PDF' : 'ساخت PDF')->visible(fn (Invoice $record) => in_array($record->status, [InvoiceStatus::Issued, InvoiceStatus::Paid], true))->url(fn (Invoice $record) => $record->pdf_status === 'ready' ? route('admin.invoices.pdf', $record) : null)->action(fn (Invoice $record) => GenerateInvoicePdf::dispatch($record->id)),
        ])->bulkActions([])->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('سند مالی')->schema([
                Infolists\Components\TextEntry::make('invoice_number')->label('شماره'), Infolists\Components\TextEntry::make('customer.display_name')->label('مشتری'),
                Infolists\Components\TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn (InvoiceStatus $state) => $state->label()),
                Infolists\Components\TextEntry::make('period_start')->label('شروع')->jalaliDate(), Infolists\Components\TextEntry::make('period_end')->label('پایان')->jalaliDate(),
                Infolists\Components\TextEntry::make('subtotal')->label('جمع'), Infolists\Components\TextEntry::make('discount_amount')->label('تخفیف'), Infolists\Components\TextEntry::make('tax_amount')->label('مالیات'), Infolists\Components\TextEntry::make('total_amount')->label('نهایی'),
                Infolists\Components\RepeatableEntry::make('items')->label('آیتم‌ها')->schema([
                    Infolists\Components\TextEntry::make('activity_title_snapshot')->label('فعالیت'), Infolists\Components\TextEntry::make('project_title_snapshot')->label('پروژه'), Infolists\Components\TextEntry::make('service_name_snapshot')->label('خدمت')->placeholder('—'), Infolists\Components\TextEntry::make('quantity')->label('مقدار')->placeholder('—'), Infolists\Components\TextEntry::make('unit_price')->label('قیمت واحد')->placeholder('—'), Infolists\Components\TextEntry::make('total_amount')->label('مبلغ'),
                ])->columns(3)->columnSpanFull(),
            ])->columns(4),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoices::route('/'), 'create' => Pages\CreateInvoice::route('/create'), 'view' => Pages\ViewInvoice::route('/{record}'), 'edit' => Pages\EditInvoice::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    private static function issueAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('issue')->label('صدور')->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Draft)->requiresConfirmation()->action(fn (Invoice $record) => app(InvoiceLifecycle::class)->issue($record))->successNotificationTitle('فاکتور صادر شد');
    }

    private static function paidAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('paid')->label('ثبت پرداخت')->visible(fn (Invoice $record) => $record->status === InvoiceStatus::Issued)->requiresConfirmation()->action(fn (Invoice $record) => app(InvoiceLifecycle::class)->markPaid($record))->successNotificationTitle('پرداخت ثبت شد');
    }

    private static function cancelAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cancel')->label('لغو')->color('danger')->visible(fn (Invoice $record) => in_array($record->status, [InvoiceStatus::Draft, InvoiceStatus::Issued], true))->requiresConfirmation()->action(fn (Invoice $record) => app(InvoiceLifecycle::class)->cancel($record))->successNotificationTitle('فاکتور لغو شد');
    }
}
