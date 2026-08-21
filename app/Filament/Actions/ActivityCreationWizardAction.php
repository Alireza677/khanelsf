<?php

namespace App\Filament\Actions;

use App\Enums\ServicePricingMode;
use App\Filament\Resources\ClientProjectActivityResource;
use App\Filament\Resources\ClientProjectResource;
use App\Models\ClientProjectActivity;
use App\Services\ActivityWizardProjectContext;
use App\Services\DurationFormatter;
use App\Services\ServiceActivityCatalog;
use App\Support\PersianDate;
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
use Illuminate\Support\HtmlString;

class ActivityCreationWizardAction
{
    public static function make(): Action
    {
        return Action::make('quickCreateActivity')
            ->label('ایجاد سریع فعالیت')
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->modalHeading('ثبت فعالیت جدید')
            ->modalDescription('فعالیت روزانه را در سه مرحله کوتاه ثبت کنید.')
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
            ->action(function (array $data): void {
                $projects = app(ActivityWizardProjectContext::class);
                $project = $projects->find($data['client_project_id'] ?? null);
                abort_unless($project, 422);

                $data = ClientProjectActivityResource::applyDurationFormState($data);
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
                        'service_id', 'service_name_snapshot', 'service_unit_snapshot', 'service_unit_label_snapshot',
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
                        ->afterStateUpdated(fn (Set $set, mixed $state) => $set('recent_project_id', $state)),
                    Forms\Components\Radio::make('recent_project_id')
                        ->label('پروژه‌های اخیر')
                        ->options(fn (): array => app(ActivityWizardProjectContext::class)->recentOptions())
                        ->live()
                        ->afterStateUpdated(fn (Set $set, mixed $state) => $set('client_project_id', $state)),
                    Forms\Components\Placeholder::make('project_context')
                        ->label('خلاصه پروژه')
                        ->content(fn (Get $get): HtmlString => self::projectSummary($get('client_project_id'))),
                ]),
            Wizard\Step::make('جزئیات فعالیت')
                ->description('مرحله ۲ از ۳')
                ->icon('heroicon-o-pencil-square')
                ->schema([
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
                    Forms\Components\DatePicker::make('activity_date')->jalali()->label('تاریخ فعالیت')->default(today()),
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
                ])->columns(2),
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
                        ])->default(ClientProjectActivity::VISIBILITY_INTERNAL),
                    Forms\Components\Radio::make('activity_status')->label('وضعیت')
                        ->options([
                            ClientProjectActivity::STATUS_DRAFT => 'پیش‌نویس',
                            ClientProjectActivity::STATUS_PUBLISHED => 'منتشرشده',
                        ])->default(ClientProjectActivity::STATUS_DRAFT),
                ]),
        ])
            ->skippable(false)
            ->nextAction(fn (FormAction $action): FormAction => $action->label('ادامه'))
            ->previousAction(fn (FormAction $action): FormAction => $action->label('بازگشت'))
            ->submitAction(new HtmlString(Blade::render('<x-filament::button type="submit" size="sm">ثبت فعالیت</x-filament::button>')));
    }

    private static function projectSummary(mixed $projectId): HtmlString
    {
        $summary = app(ActivityWizardProjectContext::class)->summary($projectId);
        if (! $summary) {
            return new HtmlString('<span class="activity-wizard-hint">برای مشاهده اطلاعات، یک پروژه انتخاب کنید.</span>');
        }

        return new HtmlString('<div class="activity-wizard-summary"><strong>'.e($summary['project']->title).'</strong><span>مشتری: '.e($summary['project']->customer->display_name).'</span><span>این ماه: '.e($summary['usage_text']).'</span></div>');
    }

    private static function finalSummary(Get $get): HtmlString
    {
        $project = app(ActivityWizardProjectContext::class)->find($get('client_project_id'));
        $duration = app(DurationFormatter::class)->format(((int) $get('duration_hours') * 60) + (int) $get('duration_remainder_minutes'));
        $service = ClientProjectActivityResource::serviceSummary($get('service_id'));

        return new HtmlString('<div class="activity-wizard-summary"><strong>'.e($project?->title ?? '—').'</strong><span>خدمت: '.e($service).'</span><span>زمان: '.e($duration).'</span><span>تاریخ: '.e(PersianDate::date($get('activity_date')) ?? '—').'</span></div>');
    }
}
