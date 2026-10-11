<?php

namespace App\Filament\Resources;

use App\Enums\ClientProjectWorkItemStatus;
use App\Filament\Resources\ClientProjectWorkItemResource\Pages\ManageWorkItems;
use App\Models\ClientProjectCycle;
use App\Models\ClientProjectWorkItem;
use App\Models\Customer;
use App\Models\User;
use App\Services\ClientProjectWorkItemManager;
use App\Services\ClientWorkItemDeliveryManager;
use App\Support\PersianDate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ClientProjectWorkItemResource extends Resource
{
    protected static ?string $model = ClientProjectWorkItem::class;

    protected static ?string $modelLabel = 'کار اجرایی';

    protected static ?string $pluralModelLabel = 'کارهای اجرایی';

    protected static ?string $navigationLabel = 'کارهای اجرایی';

    protected static ?string $navigationGroup = 'پرتال مشتری';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_project_cycle_id')->label('دوره پروژه')->required()->searchable()
                ->options(fn () => ClientProjectCycle::query()->whereIn('delivery_mode', ['tasks', 'hybrid'])->with('project.customer')->orderByDesc('starts_at')->get()->mapWithKeys(fn ($cycle) => [$cycle->id => $cycle->project->customer->display_name.' / '.$cycle->project->title.' / '.PersianDate::date($cycle->starts_at)])->all())
                ->disabled(fn (?ClientProjectWorkItem $record) => $record !== null)->dehydrated(fn (?ClientProjectWorkItem $record) => $record === null),
            Forms\Components\TextInput::make('title')->label('عنوان')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('توضیحات')->columnSpanFull(),
            Forms\Components\TextInput::make('quantity')->label('مقدار کار اضافی')->regex('/^\d{1,14}(?:\.\d{1,4})?$/')->disabled(fn (?ClientProjectWorkItem $record) => (bool) $record?->client_project_cycle_commitment_id),
            Forms\Components\TextInput::make('unit')->label('واحد')->maxLength(255)->disabled(fn (?ClientProjectWorkItem $record) => (bool) $record?->client_project_cycle_commitment_id),
            Forms\Components\Select::make('visibility')->label('دسترسی')->options(['internal' => 'داخلی', 'client' => 'قابل نمایش به مشتری'])->default('internal')->required(),
            Forms\Components\Select::make('status')->label('وضعیت اجرایی')->required()->default('planned')
                ->options(function (?ClientProjectWorkItem $record): array {
                    $keys = $record ? [$record->status->value, ...$record->status->administrativeTargets($record->approvalRequired())] : ['planned'];
                    if ($record?->status->value !== 'submitted') {
                        $keys = array_diff($keys, ['submitted']);
                    }

                    return array_intersect_key(ClientProjectWorkItemStatus::options(), array_flip($keys));
                }),
            Forms\Components\Textarea::make('reason')->label('دلیل تغییر وضعیت')->helperText('برای لغو یا مسدودشدن الزامی است.'),
            Forms\Components\DateTimePicker::make('scheduled_start_at')->label('شروع برنامه‌ریزی‌شده')->jalali(),
            Forms\Components\DateTimePicker::make('scheduled_end_at')->label('پایان برنامه‌ریزی‌شده')->jalali(),
            Forms\Components\DateTimePicker::make('due_at')->label('موعد')->jalali(),
            Forms\Components\Select::make('assignees')->label('مدیران مسئول')->multiple()->searchable()->options(fn () => User::query()->where('is_admin', true)->where('status', 'active')->pluck('name', 'id')),
            Forms\Components\Select::make('activities')->label('فعالیت‌های زمانی مرتبط')->multiple()->searchable()
                ->options(fn (?ClientProjectWorkItem $record) => $record && $record->cycle->delivery_mode === 'hybrid' ? $record->project->activities()->where('client_project_cycle_id', $record->client_project_cycle_id)->pluck('title', 'id') : [])
                ->visible(fn (?ClientProjectWorkItem $record) => $record?->cycle->delivery_mode === 'hybrid'),
            Forms\Components\Hidden::make('lock_version')->default(0),
        ])->columns(2);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('title')->label('عنوان'),
            Infolists\Components\TextEntry::make('project.title')->label('پروژه'),
            Infolists\Components\TextEntry::make('commitment.name')->label('تعهد')->placeholder('کار اضافی'),
            Infolists\Components\TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn ($state) => ClientProjectWorkItemStatus::options()[$state->value]),
            Infolists\Components\TextEntry::make('quantity')->label('مقدار'),
            Infolists\Components\TextEntry::make('unit')->label('واحد'),
            Infolists\Components\TextEntry::make('description')->label('توضیحات')->columnSpanFull(),
            Infolists\Components\TextEntry::make('scheduled_start_at')->label('شروع برنامه')->jalaliDateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('scheduled_end_at')->label('پایان برنامه')->jalaliDateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('started_at')->label('شروع اجرا')->jalaliDateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('completed_at')->label('تکمیل نهایی')->jalaliDateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('assignees.name')->label('مسئولان'),
            Infolists\Components\TextEntry::make('due_at')->label('موعد')->jalaliDateTime(),
            Infolists\Components\TextEntry::make('operational_completed_at')->label('پایان اجرا')->jalaliDateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('accepted_at')->label('تأیید مشتری')->jalaliDateTime()->placeholder('ثبت نشده'),
            Infolists\Components\RepeatableEntry::make('deliveries')->label('نسخه‌های تحویل')->schema([
                Infolists\Components\TextEntry::make('version')->label('نسخه'),
                Infolists\Components\TextEntry::make('submitted_at')->label('ارسال')->jalaliDateTime(),
                Infolists\Components\TextEntry::make('submitter.name')->label('ارسال‌کننده'),
                Infolists\Components\TextEntry::make('message')->label('پیام تحویل'),
                Infolists\Components\TextEntry::make('review_state')->label('بررسی')->formatStateUsing(fn ($state) => ['pending' => 'منتظر مشتری', 'accepted' => 'تأیید', 'revision_requested' => 'اصلاح', 'not_required' => 'بدون تأیید'][$state]),
                Infolists\Components\TextEntry::make('review.reason')->label('نظر مشتری'),
                Infolists\Components\TextEntry::make('review.reviewed_at')->label('زمان بررسی')->jalaliDateTime(),
                Infolists\Components\RepeatableEntry::make('files')->label('فایل‌ها')->schema([
                    Infolists\Components\TextEntry::make('original_name')->label('نام')->url(fn ($record) => route('admin.service-delivery-files.download', ['file' => $record->id])),
                    Infolists\Components\TextEntry::make('visibility')->label('دسترسی')->formatStateUsing(fn ($state) => $state === 'client' ? 'مشتری' : 'داخلی'),
                    Infolists\Components\TextEntry::make('size')->label('حجم؛ بایت'),
                ])->columns(3)->columnSpanFull(),
            ])->columns(3)->columnSpanFull(),
            Infolists\Components\RepeatableEntry::make('events')->label('تاریخچه تغییرات')->schema([
                Infolists\Components\TextEntry::make('to_status')->label('وضعیت')->formatStateUsing(fn ($state) => ClientProjectWorkItemStatus::options()[$state]),
                Infolists\Components\TextEntry::make('created_at')->label('زمان')->jalaliDateTime(),
                Infolists\Components\TextEntry::make('actor.name')->label('مدیر')->placeholder('—'),
                Infolists\Components\TextEntry::make('reason')->label('دلیل')->placeholder('ویرایش یا ایجاد'),
            ])->columns(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->label('عنوان')->searchable(),
            Tables\Columns\TextColumn::make('project.customer.display_name')->label('مشتری'),
            Tables\Columns\TextColumn::make('project.title')->label('پروژه')->searchable(),
            Tables\Columns\TextColumn::make('cycle.starts_at')->label('دوره')->jalaliDate(),
            Tables\Columns\TextColumn::make('commitment.name')->label('تعهد')->placeholder('اضافی'),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn ($state) => ClientProjectWorkItemStatus::options()[$state->value])->color(fn ($state) => $state === ClientProjectWorkItemStatus::Blocked ? 'danger' : 'gray'),
            Tables\Columns\TextColumn::make('assignees.name')->label('مسئولان')->listWithLineBreaks(),
            Tables\Columns\TextColumn::make('due_at')->label('موعد')->jalaliDateTime()->sortable()->color(fn ($record) => $record->due_at?->isPast() && ! in_array($record->status->value, ['completed', 'cancelled'], true) ? 'danger' : 'gray'),
        ])->filters([
            Tables\Filters\SelectFilter::make('customer')->label('مشتری')->options(fn () => Customer::pluck('display_name', 'id'))->query(fn ($query, $data) => $query->when($data['value'], fn ($query, $value) => $query->whereHas('project', fn ($query) => $query->where('customer_id', $value)))),
            Tables\Filters\SelectFilter::make('client_project_id')->label('پروژه')->relationship('project', 'title'),
            Tables\Filters\SelectFilter::make('client_project_cycle_id')->label('دوره')->options(fn () => ClientProjectCycle::whereIn('delivery_mode', ['tasks', 'hybrid'])->with('project')->get()->mapWithKeys(fn ($cycle) => [$cycle->id => $cycle->project->title.' / '.PersianDate::date($cycle->starts_at)])->all()),
            Tables\Filters\SelectFilter::make('assignee')->label('مسئول')->relationship('assignees', 'name'),
            Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(ClientProjectWorkItemStatus::options()),
            Tables\Filters\Filter::make('overdue')->label('موعد گذشته')->query(fn ($query) => $query->where('due_at', '<', now())->whereNotIn('status', ['completed', 'cancelled'])),
            Tables\Filters\Filter::make('blocked')->label('مسدود')->query(fn ($query) => $query->where('status', 'blocked')),
            Tables\Filters\Filter::make('awaitingApproval')->label('منتظر تأیید مشتری')->query(fn ($query) => $query->where('status', 'submitted')->whereHas('deliveries', fn ($query) => $query->where('approval_required', true)->whereDoesntHave('review'))),
            Tables\Filters\TernaryFilter::make('contractual')->label('قراردادی / اضافی')->queries(true: fn ($query) => $query->whereNotNull('client_project_cycle_commitment_id'), false: fn ($query) => $query->whereNull('client_project_cycle_commitment_id'), blank: fn ($query) => $query),
        ])->actions([
            Tables\Actions\ViewAction::make()->label('جزئیات'),
            Tables\Actions\Action::make('submitDelivery')->label('ارسال نسخه تحویل')
                ->visible(fn ($record) => auth()->user()?->can('update', $record->project) && ($record->status->value === 'in_progress' || ($record->status->value === 'submitted' && ! $record->deliveries()->exists())))
                ->fillForm(fn ($record) => ['lock_version' => $record->lock_version])
                ->form([
                    Forms\Components\Hidden::make('lock_version')->required(),
                    Forms\Components\Textarea::make('message')->label('پیام تحویل')->maxLength(10000)->helperText('برای کار قابل نمایش، این پیام به مشتری نمایش داده می‌شود.'),
                    Forms\Components\FileUpload::make('client_files')->label('فایل‌های مشتری')->multiple()->maxFiles(10)->maxSize(102400)->acceptedFileTypes(ClientWorkItemDeliveryManager::MIME_TYPES)->storeFiles(false),
                    Forms\Components\FileUpload::make('internal_files')->label('فایل‌های داخلی')->multiple()->maxFiles(10)->maxSize(102400)->acceptedFileTypes(ClientWorkItemDeliveryManager::MIME_TYPES)->storeFiles(false),
                ])->action(function ($record, array $data): void {
                    app(ClientWorkItemDeliveryManager::class)->submit($record, auth()->user(), (int) $data['lock_version'], $data['message'] ?? null,
                        ['client' => $data['client_files'] ?? [], 'internal' => $data['internal_files'] ?? []]);
                    Notification::make()->success()->title('نسخه تحویل ثبت شد')->send();
                }),
            Tables\Actions\EditAction::make()->label('ویرایش و اجرا')
                ->visible(fn ($record) => ! in_array($record->status->value, ['completed', 'cancelled'], true) && ! ($record->status->value === 'submitted' && $record->approvalRequired()))
                ->mutateRecordDataUsing(fn (array $data, $record) => [...$data, 'status' => $record->status->value, 'assignees' => $record->assignees()->pluck('users.id')->all(), 'activities' => $record->activities()->pluck('client_project_activities.id')->all()])
                ->using(fn ($record, array $data) => app(ClientProjectWorkItemManager::class)->update($record, $data, (int) $data['lock_version'], auth()->user())),
        ])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWorkItems::route('/')];
    }
}
