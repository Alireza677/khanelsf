<?php

namespace App\Filament\Actions;

use App\Enums\ServicePricingMode;
use App\Filament\Resources\ClientProjectActivityResource;
use App\Filament\Resources\ClientProjectResource;
use App\Filament\Resources\ClientProjectWorkItemResource;
use App\Models\ClientProjectActivity;
use App\Models\ClientProjectWorkItem;
use App\Services\ActivityWizardProjectContext;
use App\Services\ClientProjectDeliveryProgress;
use App\Services\ClientProjectWorkItemGenerator;
use App\Services\ClientProjectWorkItemManager;
use App\Services\ClientWorkItemDeliveryManager;
use App\Services\DurationFormatter;
use App\Services\ServiceActivityCatalog;
use App\Services\WorkRegistrationContext;
use App\Support\PersianDate;
use App\Support\ServiceDeliveryNumber;
use Closure;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ActivityCreationWizardAction
{
    public static function make(): Action
    {
        return Action::make('quickCreateActivity')
            ->label('ثبت کار انجام‌شده')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading('ثبت کار انجام‌شده')
            ->modalDescription('پروژه را انتخاب کنید؛ ثبت زمان یا تحویل خروجی بر اساس قرارداد پروژه در دسترس است.')
            ->modalWidth(MaxWidth::TwoExtraLarge)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes(['class' => 'activity-creation-wizard-modal'])
            ->modalSubmitAction(false)
            ->fillForm([
                'activity_date' => today()->toDateString(),
                'duration_hours' => 0,
                'duration_remainder_minutes' => 0,
                'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
                'activity_status' => ClientProjectActivity::STATUS_DRAFT,
            ])
            ->form([self::wizard()])
            ->action(function (array $data, $livewire): void {
                $projects = app(ActivityWizardProjectContext::class);
                $project = $projects->find($data['client_project_id'] ?? null);
                abort_unless($project, 422);

                $context = app(WorkRegistrationContext::class);
                try {
                    $operation = $context->operation($project->id, $data['operation'] ?? null);
                    if ($operation === 'work_item') {
                        self::registerWorkItem($data);

                        return;
                    }
                } catch (ValidationException $exception) {
                    $errors = [];
                    $prefix = 'mountedActionsData.'.array_key_last($livewire->mountedActionsData).'.';
                    foreach ($exception->errors() as $field => $messages) {
                        $field = match ($field) {
                            'lock_version' => 'work_item_id',
                            'work_cycle_id' => 'client_project_id',
                            'operation', 'work_item_id', 'task_action' => $field,
                            default => 'work_data.'.$field,
                        };
                        $errors[$prefix.$field] = $messages;
                    }
                    Notification::make()->danger()->title('ثبت انجام نشد')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                    throw ValidationException::withMessages($errors);
                }
                Gate::authorize('create', ClientProjectActivity::class);
                $data = ClientProjectActivityResource::applyDurationFormState($data);
                $data = ClientProjectActivityResource::applyCycleFormState($data);
                $data = ClientProjectActivityResource::applyCommercialFormState($data);
                $activity = $project->activities()->create([
                    'performed_by' => auth()->id(),
                    'activity_date' => $data['activity_date'] ?? today()->toDateString(),
                    'duration_minutes' => $data['duration_minutes'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'internal_notes' => $data['internal_notes'] ?? null,
                    'visibility' => $data['visibility'] ?? ClientProjectActivity::VISIBILITY_INTERNAL,
                    'status' => $data['activity_status'] ?? ClientProjectActivity::STATUS_DRAFT,
                    ...Arr::only($data, [
                        'client_project_cycle_id', 'service_id', 'service_name_snapshot', 'service_unit_snapshot', 'service_unit_label_snapshot',
                        'pricing_mode_snapshot', 'currency_snapshot', 'unit_price_snapshot', 'quantity', 'total_amount',
                    ]),
                ]);

                $duration = app(DurationFormatter::class)->format($activity->duration_minutes);
                $status = ClientProjectActivityResource::statusOptions()[$activity->status] ?? $activity->status;

                Notification::make()
                    ->success()
                    ->title('فعالیت ثبت شد')
                    ->body("{$project->title} · {$duration} · وضعیت: {$status}")
                    ->actions([
                        NotificationAction::make('new')->label('ثبت فعالیت جدید')->url(ClientProjectActivityResource::getUrl('index')),
                        NotificationAction::make('project')->label('مشاهده پروژه')->url(ClientProjectResource::getUrl('view', ['record' => $project])),
                    ])
                    ->send();
            });
    }

    private static function wizard(): Wizard
    {
        return Wizard::make([
            Wizard\Step::make('انتخاب پروژه')
                ->description('مرحله ۱ از ۳')
                ->icon('heroicon-o-briefcase')
                ->schema([
                    Forms\Components\Select::make('client_project_id')
                        ->label('پروژه')
                        ->options(fn (): array => app(ActivityWizardProjectContext::class)->options())
                        ->searchable()->preload()->live()->required()
                        ->afterStateUpdated(fn (Set $set, mixed $state) => self::selectProject($set, $state)),
                    Forms\Components\Radio::make('recent_project_id')
                        ->label('پروژه‌های اخیر')
                        ->options(fn (): array => app(ActivityWizardProjectContext::class)->recentOptions())
                        ->live()
                        ->afterStateUpdated(fn (Set $set, mixed $state) => self::selectProject($set, $state)),
                    Forms\Components\Radio::make('operation')->label('نوع ثبت')
                        ->options(fn (Get $get): array => app(WorkRegistrationContext::class)->choices($get('client_project_id')))
                        ->default('time')->live()->required()
                        ->visible(fn (Get $get): bool => app(WorkRegistrationContext::class)->project($get('client_project_id'))?->delivery_mode === 'hybrid'),
                    Forms\Components\Placeholder::make('project_context')
                        ->label('خلاصه پروژه')
                        ->content(fn (Get $get): HtmlString => self::projectSummary($get('client_project_id'))),
                ]),
            Wizard\Step::make('جزئیات کار')
                ->description('مرحله ۲ از ۳')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Forms\Components\Group::make()->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('خدمت (اختیاری)')
                            ->options(fn (): array => app(ServiceActivityCatalog::class)->options())
                            ->searchable()->preload()->live()
                            ->hidden(fn (): bool => ! app(ServiceActivityCatalog::class)->enabled())
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                $service = app(ServiceActivityCatalog::class)->find($state);
                                if ($service && blank($get('title'))) {
                                    $set('title', $service->name);
                                }
                            }),
                        Forms\Components\Placeholder::make('service_summary')->label('مشخصات خدمت')
                            ->content(fn (Get $get): string => ClientProjectActivityResource::serviceSummary($get('service_id')))
                            ->visible(fn (Get $get): bool => filled($get('service_id'))),
                        Forms\Components\TextInput::make('title')->label('عنوان فعالیت')->required()->maxLength(255)->autofocus(),
                        Forms\Components\DatePicker::make('activity_date')->jalali()->closeOnDateSelection()->label('تاریخ فعالیت')->default(today()),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('duration_hours')->label('ساعت')->numeric()->minValue(0)->maxValue(24)->default(0)->required()->live(),
                            Forms\Components\TextInput::make('duration_remainder_minutes')->label('دقیقه')->numeric()->minValue(0)->maxValue(59)->default(0)->required()->live()
                                ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $minutes = ((int) $get('duration_hours') * 60) + (int) $value;
                                    if ($minutes < 1 || $minutes > ClientProjectActivity::MAX_DURATION_MINUTES) {
                                        $fail('مدت فعالیت باید بین ۱ دقیقه و ۲۴ ساعت باشد.');
                                    }
                                }]),
                        ]),
                        Forms\Components\TextInput::make('quantity')->label('مقدار تحویل‌شده')
                            ->numeric()->minValue(0.0001)->inputMode('decimal')
                            ->visible(fn (Get $get): bool => ClientProjectActivityResource::selectedPricingMode($get('service_id')) === ServicePricingMode::PerUnit->value)
                            ->required(fn (Get $get): bool => ClientProjectActivityResource::selectedPricingMode($get('service_id')) === ServicePricingMode::PerUnit->value),
                        Forms\Components\Textarea::make('description')->label('توضیحات قابل نمایش به مشتری')->rows(3),
                        Forms\Components\Textarea::make('internal_notes')->label('یادداشت داخلی — خصوصی')->rows(3)
                            ->helperText('این متن هرگز در پرتال مشتری نمایش داده نمی‌شود.'),
                    ])->columns(2)->visible(fn (Get $get): bool => self::isTime($get)),
                    Forms\Components\Group::make()->schema(self::taskFields())
                        ->visible(fn (Get $get): bool => ! self::isTime($get)),
                ]),
            Wizard\Step::make('ثبت نهایی')
                ->description('مرحله ۳ از ۳')
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Forms\Components\Placeholder::make('final_summary')->label('خلاصه فعالیت')
                        ->content(fn (Get $get): HtmlString => self::finalSummary($get)),
                    Forms\Components\Radio::make('visibility')->label('نمایش')
                        ->options([
                            ClientProjectActivity::VISIBILITY_INTERNAL => 'داخلی — فقط مدیران',
                            ClientProjectActivity::VISIBILITY_CLIENT => 'قابل مشاهده برای مشتری',
                        ])->default(ClientProjectActivity::VISIBILITY_INTERNAL)->visible(fn (Get $get): bool => self::isTime($get)),
                    Forms\Components\Radio::make('activity_status')->label('وضعیت')
                        ->options([
                            ClientProjectActivity::STATUS_DRAFT => 'پیش‌نویس',
                            ClientProjectActivity::STATUS_PUBLISHED => 'منتشرشده',
                        ])->default(ClientProjectActivity::STATUS_DRAFT)->visible(fn (Get $get): bool => self::isTime($get)),
                ]),
        ])
            ->skippable(false)
            ->nextAction(fn (FormAction $action): FormAction => $action->label('ادامه'))
            ->previousAction(fn (FormAction $action): FormAction => $action->label('بازگشت'))
            ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit" size="sm">ثبت کار</x-filament::button>')));
    }

    private static function projectSummary(mixed $projectId): HtmlString
    {
        if (app(WorkRegistrationContext::class)->project($projectId)?->delivery_mode === 'tasks') {
            return self::taskSummary($projectId);
        }
        $summary = app(ActivityWizardProjectContext::class)->summary($projectId);
        if (! $summary) {
            return new HtmlString('<span class="activity-wizard-hint">برای مشاهده اطلاعات، یک پروژه انتخاب کنید.</span>');
        }

        return new HtmlString('<div class="activity-wizard-summary"><strong>'.e($summary['project']->title).'</strong><span>مشتری: '.e($summary['project']->customer->display_name).'</span><span>این ماه: '.e($summary['usage_text']).'</span></div>');
    }

    private static function finalSummary(Get $get): HtmlString
    {
        if (! self::isTime($get)) {
            $item = app(WorkRegistrationContext::class)->item($get('client_project_id'), $get('work_item_id'));
            $action = ['update' => 'ویرایش / پیشبرد کار', 'deliver' => 'ارسال نسخه تحویل', 'extra' => 'ایجاد کار خارج از بسته'][$get('task_action')] ?? '—';
            $title = $get('task_action') === 'extra' ? $get('work_data.title') : $item?->title;

            return new HtmlString(self::taskSummary($get('client_project_id'))->toHtml().'<p>'.e($action).' · '.e($title ?? '—').'</p><p>عملیات انتخاب‌شده پس از ثبت نهایی انجام می‌شود. تأیید خروجی از مسیر بررسی مشتری انجام می‌شود.</p>');
        }
        $project = app(ActivityWizardProjectContext::class)->find($get('client_project_id'));
        $durationMinutes = ((int) $get('duration_hours') * 60) + (int) $get('duration_remainder_minutes');
        $duration = app(DurationFormatter::class)->format($durationMinutes);
        $service = ClientProjectActivityResource::serviceSummary($get('service_id'));
        $warning = app(ActivityWizardProjectContext::class)->overageWarning(
            $get('client_project_id'),
            $get('activity_date'),
            $durationMinutes,
        );
        $warningHtml = $warning
            ? '<span class="text-warning-600 dark:text-warning-400">'.e($warning).'</span>'
            : '';

        return new HtmlString('<div class="activity-wizard-summary"><strong>'.e($project?->title ?? '—').'</strong><span>خدمت: '.e($service).'</span><span>زمان: '.e($duration).'</span><span>تاریخ: '.e(PersianDate::date($get('activity_date')) ?? '—').'</span>'.$warningHtml.'</div>');
    }

    private static function selectProject(Set $set, mixed $id): void
    {
        $set('client_project_id', $id);
        $set('recent_project_id', $id);
        $set('operation', array_key_first(app(WorkRegistrationContext::class)->choices($id)));
        $set('work_item_id', null);
        $set('task_action', 'update');
        $set('work_data', []);
        $set('work_cycle_id', app(WorkRegistrationContext::class)->cycle($id)?->id);
    }

    private static function isTime(Get $get): bool
    {
        $context = app(WorkRegistrationContext::class);
        $choices = $context->choices($get('client_project_id'));

        return (count($choices) === 1 ? array_key_first($choices) : $get('operation')) === 'time';
    }

    private static function taskSummary(mixed $id): HtmlString
    {
        $cycle = app(WorkRegistrationContext::class)->cycle($id);
        if (! $cycle) {
            return new HtmlString('<p>دوره جاری ثبت نشده است. ابتدا از مدیریت دوره‌های پروژه، دوره قراردادی را ایجاد کنید.</p>');
        }
        $agreement = $cycle->agreement_snapshot ?? [];
        $html = '<div class="activity-wizard-summary"><strong>دوره جاری: '.e(PersianDate::date($cycle->starts_at)).' تا '.e(PersianDate::date($cycle->ends_at)).' (پایان غیرشامل)</strong><span>بسته: '.e($agreement['offering']['name'] ?? '—').'</span><span>قیمت قراردادی (صرفاً اطلاعات): '.e(ServiceDeliveryNumber::format($agreement['amount'] ?? null)).' '.e($agreement['currency_code'] ?? '').'</span>';
        foreach (app(ClientProjectDeliveryProgress::class)->cycle($cycle) as $line) {
            $html .= '<span>'.e($line['name']).' · '.e($line['unit']).' — تعهد: '.e(ServiceDeliveryNumber::format($line['committed'])).'، تکمیل: '.e(ServiceDeliveryNumber::format($line['completed'])).'، باقی‌مانده: '.e(ServiceDeliveryNumber::format($line['remaining'])).'</span>';
        }

        return new HtmlString($html.'</div>');
    }

    private static function taskFields(): array
    {
        return [
            Forms\Components\Hidden::make('work_cycle_id'),
            Forms\Components\Placeholder::make('contract_context')->label('قرارداد و تعهدات دوره')
                ->content(fn (Get $get): HtmlString => self::taskSummary($get('client_project_id'))),
            Forms\Components\Actions::make([
                FormAction::make('generateMissingWork')->label('ایجاد کارهای قراردادیِ ثبت‌نشده')
                    ->visible(fn (Get $get): bool => app(WorkRegistrationContext::class)->cycle($get('client_project_id')) !== null)
                    ->action(function (Get $get, Set $set): void {
                        $cycle = app(WorkRegistrationContext::class)->cycle($get('client_project_id'));
                        abort_unless($cycle, 422);
                        $count = app(ClientProjectWorkItemGenerator::class)->generate($cycle, auth()->user());
                        $set('work_cycle_id', $cycle->id);
                        Notification::make()->success()->title('کارهای قراردادی آماده‌اند')->body("تعداد کارهای جدید: {$count}")->send();
                    }),
            ]),
            Forms\Components\Radio::make('task_action')->label('عملیات کار')
                ->options(['update' => 'ویرایش / پیشبرد کار موجود', 'deliver' => 'ارسال نسخه تحویل کار موجود', 'extra' => 'ایجاد کار خارج از بسته'])
                ->default('update')->live()->required()
                ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                    $item = $state === 'extra' ? null : app(WorkRegistrationContext::class)->item($get('client_project_id'), $get('work_item_id'));
                    self::fillWorkData($set, $item);
                }),
            Forms\Components\Select::make('work_item_id')->label('کار دوره جاری')
                ->helperText('ابتدا کار را آماده و سپس در حال اجرا کنید. برای ارسال خروجیِ نیازمند تأیید، دسترسی کار باید قابل نمایش به مشتری باشد.')
                ->options(fn (Get $get): array => app(WorkRegistrationContext::class)->itemOptions($get('client_project_id')))
                ->searchable()->preload()->live()->required()
                ->visible(fn (Get $get): bool => $get('task_action') !== 'extra')
                ->afterStateUpdated(fn (Set $set, Get $get, $state) => self::fillWorkData($set, app(WorkRegistrationContext::class)->item($get('client_project_id'), $state))),
            Forms\Components\Placeholder::make('selected_work_context')->label('کار انتخاب‌شده')
                ->visible(fn (Get $get): bool => $get('task_action') !== 'extra' && filled($get('work_item_id')))
                ->content(function (Get $get): string {
                    $item = app(WorkRegistrationContext::class)->item($get('client_project_id'), $get('work_item_id'));
                    if (! $item) {
                        return 'کار به این پروژه و دوره تعلق ندارد.';
                    }

                    return $item->title.' · '.$item->status->options()[$item->status->value].' · '.ServiceDeliveryNumber::format($item->quantity).' '.$item->unit.' · '.($item->commitment ? 'داخل بسته قراردادی؛ بدون هزینه اضافی' : 'خارج از بسته').($item->approvalRequired() ? ' · نیازمند تأیید مشتری' : '');
                }),
            Forms\Components\Group::make()->statePath('work_data')->columns(2)
                ->schema(function (Get $get): array {
                    $item = app(WorkRegistrationContext::class)->item($get('client_project_id'), $get('work_item_id'));

                    return $get('task_action') === 'deliver' ? ClientProjectWorkItemResource::deliveryFields() : ClientProjectWorkItemResource::workItemFields($get('task_action') === 'extra' ? null : $item);
                })
                ->visible(fn (Get $get): bool => $get('task_action') === 'extra' || filled($get('work_item_id'))),
        ];
    }

    private static function fillWorkData(Set $set, ?ClientProjectWorkItem $item): void
    {
        $set('work_data', $item ? [...$item->attributesToArray(), 'status' => $item->status->value,
            'assignees' => $item->assignees()->pluck('users.id')->all(), 'activities' => $item->activities()->pluck('client_project_activities.id')->all()]
            : ['status' => 'planned', 'visibility' => 'internal', 'lock_version' => 0]);
    }

    private static function registerWorkItem(array $data): void
    {
        $context = app(WorkRegistrationContext::class);
        $cycle = $context->cycle($data['client_project_id']);
        if (! $cycle || (int) ($data['work_cycle_id'] ?? 0) !== $cycle->id) {
            throw ValidationException::withMessages(['work_cycle_id' => 'دوره جاری تغییر کرده یا ثبت نشده است؛ پروژه را دوباره انتخاب کنید.']);
        }
        $values = $data['work_data'] ?? [];
        $action = $data['task_action'] ?? null;
        if ($action === 'extra') {
            app(ClientProjectWorkItemManager::class)->createExtra($cycle, $values, auth()->user());
        } else {
            $item = $context->item($data['client_project_id'], $data['work_item_id'] ?? null);
            if (! $item || ($action === 'deliver' ? ! $context->canDeliver($item) : ! $context->canEdit($item))) {
                throw ValidationException::withMessages(['work_item_id' => 'این کار برای عملیات انتخاب‌شده قابل ثبت نیست. وضعیت کار و دوره را بررسی کنید.']);
            }
            if ($action === 'deliver') {
                app(ClientWorkItemDeliveryManager::class)->submit($item, auth()->user(), (int) ($values['lock_version'] ?? -1), $values['message'] ?? null,
                    ['client' => $values['client_files'] ?? [], 'internal' => $values['internal_files'] ?? []]);
            } elseif ($action === 'update') {
                app(ClientProjectWorkItemManager::class)->update($item, $values, (int) ($values['lock_version'] ?? -1), auth()->user());
            } else {
                throw ValidationException::withMessages(['task_action' => 'عملیات نامعتبر است.']);
            }
        }
        Notification::make()->success()->title($action === 'deliver' ? 'نسخه تحویل ثبت شد' : 'کار ثبت شد')->send();
    }
}
