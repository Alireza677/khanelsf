<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientProjectCycleResource\Pages\ManageDeliveryCycles;
use App\Models\ClientProjectCycle;
use App\Services\ClientCycleReportPublisher;
use App\Services\ClientProjectDeliveryProgress;
use App\Services\ClientProjectWorkItemGenerator;
use App\Support\ServiceDeliveryNumber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientProjectCycleResource extends Resource
{
    protected static ?string $model = ClientProjectCycle::class;

    protected static ?string $modelLabel = 'دوره تحویل';

    protected static ?string $pluralModelLabel = 'دوره‌های تحویل';

    protected static ?string $navigationGroup = 'پرتال مشتری';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('delivery_mode', ['tasks', 'hybrid']);
    }

    public static function statusOptions(): array
    {
        return ['planned' => 'برنامه‌ریزی‌نشده', 'active' => 'در حال تحویل', 'awaiting_approval' => 'منتظر تأیید مشتری', 'fulfilled' => 'تعهدات تکمیل‌شده', 'overdue' => 'تعهدات معوق'];
    }

    public static function details(): array
    {
        return [
            Infolists\Components\TextEntry::make('project.title')->label('پروژه'),
            Infolists\Components\TextEntry::make('starts_at')->label('شروع')->jalaliDate(),
            Infolists\Components\TextEntry::make('ends_at')->label('پایان دوره؛ غیرشامل')->jalaliDate(),
            Infolists\Components\TextEntry::make('delivery_status')->label('وضعیت تحویل')->formatStateUsing(fn ($state) => self::statusOptions()[$state] ?? '—'),
            Infolists\Components\TextEntry::make('agreement_snapshot.offering.name')->label('بسته محفوظ'),
            Infolists\Components\TextEntry::make('agreement_snapshot.contract_terms')->label('شرایط محفوظ')->columnSpanFull(),
            Infolists\Components\RepeatableEntry::make('commitments')->label('تعهدات مستقل این دوره')->schema([
                Infolists\Components\TextEntry::make('name')->label('تعهد'),
                Infolists\Components\TextEntry::make('quantity')->label('مقدار تعهد')->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
                Infolists\Components\TextEntry::make('unit')->label('واحد'),
                Infolists\Components\TextEntry::make('approval_required')->label('تأیید لازم')->formatStateUsing(fn ($state) => $state ? 'بله' : 'خیر'),
                Infolists\Components\TextEntry::make('revision_allowance')->label('اصلاح مجاز'),
                Infolists\Components\TextEntry::make('operational_completed')->label('اجراشده')->getStateUsing(fn ($record) => app(ClientProjectDeliveryProgress::class)->commitment($record)['operational_completed'])->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
                Infolists\Components\TextEntry::make('accepted_completed')->label('تکمیل تعهد')->getStateUsing(fn ($record) => app(ClientProjectDeliveryProgress::class)->commitment($record)['completed'])->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
                Infolists\Components\TextEntry::make('remaining')->label('باقی‌مانده تعهد')->getStateUsing(fn ($record) => app(ClientProjectDeliveryProgress::class)->commitment($record)['remaining'])->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
            ])->columns(3)->columnSpanFull(),
            Infolists\Components\RepeatableEntry::make('reports')->label('گزارش‌های تاریخی منتشرشده')->schema([
                Infolists\Components\TextEntry::make('version')->label('نسخه'),
                Infolists\Components\TextEntry::make('published_at')->label('انتشار')->jalaliDateTime(),
                Infolists\Components\TextEntry::make('outcome')->label('نتیجه')->formatStateUsing(fn ($state) => $state === 'successful' ? 'تعهدات تکمیل‌شده' : 'ناقص'),
                Infolists\Components\TextEntry::make('snapshot.message')->label('توضیح عمومی'),
                Infolists\Components\TextEntry::make('snapshot.agreement.offering.name')->label('بسته محفوظ'),
                Infolists\Components\TextEntry::make('snapshot.agreement.amount')->label('قیمت محفوظ'),
                Infolists\Components\RepeatableEntry::make('snapshot.commitments')->label('تعهدات محفوظ')->schema([
                    Infolists\Components\TextEntry::make('name')->label('نام'),
                    Infolists\Components\TextEntry::make('committed')->label('تعهد')->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
                    Infolists\Components\TextEntry::make('completed')->label('تکمیل')->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
                    Infolists\Components\TextEntry::make('remaining')->label('باقی‌مانده')->formatStateUsing(fn ($state) => ServiceDeliveryNumber::format($state)),
                ])->columns(4)->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(self::details())->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('project.customer.display_name')->label('مشتری'),
            Tables\Columns\TextColumn::make('project.title')->label('پروژه')->searchable(),
            Tables\Columns\TextColumn::make('starts_at')->label('شروع')->jalaliDate()->sortable(),
            Tables\Columns\TextColumn::make('ends_at')->label('پایان غیرشامل')->jalaliDate(),
            Tables\Columns\TextColumn::make('delivery_status')->label('تحویل')->badge()->formatStateUsing(fn ($state) => self::statusOptions()[$state] ?? '—'),
            Tables\Columns\TextColumn::make('commitments_count')->counts('commitments')->label('انواع تعهد'),
            Tables\Columns\TextColumn::make('work_items_count')->counts('workItems')->label('کارهای دوره'),
        ])->defaultSort('starts_at', 'desc')->actions([
            Tables\Actions\ViewAction::make()->label('تعهدات و پیشرفت')->infolist(self::details()),
            Tables\Actions\Action::make('publishReport')->label('انتشار نسخه گزارش')
                ->visible(fn ($record) => $record->ends_at->lte(today()) && auth()->user()?->can('update', $record->project))
                ->form([
                    Select::make('outcome')->label('نتیجه')->options(['incomplete' => 'گزارش ناقص', 'successful' => 'تعهدات تکمیل‌شده'])->default('incomplete')->required(),
                    Textarea::make('message')->label('توضیح قابل نمایش به مشتری')->maxLength(10000),
                ])->action(function ($record, array $data): void {
                    app(ClientCycleReportPublisher::class)->publish($record, auth()->user(), $data['outcome'], $data['message'] ?? null);
                    Notification::make()->success()->title('نسخه گزارش منتشر شد')->send();
                }),
            Tables\Actions\Action::make('generate')->label('تولید کارهای تعهد')->requiresConfirmation()
                ->visible(fn ($record) => auth()->user()?->can('update', $record->project))
                ->action(function ($record): void {
                    $count = app(ClientProjectWorkItemGenerator::class)->generate($record, auth()->user());
                    Notification::make()->success()->title($count.' کار جدید ایجاد شد')->send();
                }),
            Tables\Actions\Action::make('workItems')->label('برنامه‌ریزی کارها')->url(fn ($record) => ClientProjectWorkItemResource::getUrl('index', ['tableFilters' => ['client_project_cycle_id' => ['value' => $record->id]]])),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveryCycles::route('/')];
    }
}
