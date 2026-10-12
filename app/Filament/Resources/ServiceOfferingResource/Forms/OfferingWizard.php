<?php

namespace App\Filament\Resources\ServiceOfferingResource\Forms;

use App\Enums\ClientProjectDeliveryMode;
use App\Filament\Resources\ClientProjectResource;
use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingVersionResource;
use App\Filament\Resources\ServiceResource;
use App\Models\DeliverableType;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;

final class OfferingWizard
{
    public const STEPS = [
        'package' => 'اطلاعات بسته', 'pricing' => 'نحوه ارائه و قیمت',
        'deliverables' => 'تعهدات تحویل', 'features' => 'ویژگی‌ها و شرایط بسته', 'review' => 'بررسی و انتشار',
    ];

    public static function make(): Wizard
    {
        return Wizard::make([
            Step::make(self::STEPS['package'])->id('package')->schema([
                Forms\Components\Placeholder::make('package_help')->hiddenLabel()->content('خدمت، موضوع اصلی خدمات شماست؛ بسته، یک برنامه قابل خرید از آن خدمت با قیمت و شرایط مشخص است.')->columnSpanFull(),
                ...ServiceOfferingResource::metadataFields(),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('createService')->label('ایجاد خدمت در تب جدید')->color('gray')->url(fn () => ServiceResource::getUrl('create'))->openUrlInNewTab()->visible(fn () => ServiceResource::canCreate()),
                ])->columnSpanFull(),
                Forms\Components\Placeholder::make('service_help')->hiddenLabel()->content('پس از ایجاد خدمت، نام آن را در فهرست جستجو کنید. اطلاعات این فرم در همین تب باقی می‌ماند.')->visible(fn () => ServiceResource::canCreate())->columnSpanFull(),
            ])->columns(2),
            Step::make(self::STEPS['pricing'])->id('pricing')->schema([
                Forms\Components\Select::make('delivery_mode')->label('نحوه ارائه')->options(ClientProjectDeliveryMode::options())->required()->default('time')->live()
                    ->helperText('زمانی: پیگیری ساعت کار؛ وظیفه‌محور: تحویل تعهدات بدون ساعت اجباری؛ ترکیبی: هر دو.'),
                Forms\Components\Select::make('schedule_mode')->label('زمان‌بندی')->options(ClientProjectResource::scheduleModeOptions())->required()->default('recurring')->live()
                    ->helperText('ماهانه برای خدمات تکرارشونده؛ دوره ثابت برای همکاری با شروع و پایان مشخص.'),
                ...ServiceOfferingVersionResource::billingFields(),
                Forms\Components\Section::make('پیش‌فرض‌های برنامه‌ریزی')->description('مقادیر پیشنهادی هنگام ایجاد قرارداد مشتری؛ در قرارداد قابل تنظیم هستند.')->schema(self::schedulingFields())->columns(2),
                Forms\Components\Placeholder::make('billing_help')->hiddenLabel()->content('این قیمت و شرایط در قرارداد ثبت می‌شوند. محاسبات صورتحساب از قواعد فعلی سیستم پیروی می‌کنند.')->columnSpanFull(),
            ])->columns(2),
            Step::make(self::STEPS['deliverables'])->id('deliverables')->schema([
                Forms\Components\Placeholder::make('deliverables_help')->hiddenLabel()->content('تعهد تحویل یعنی کاری با مقدار مشخص؛ مثلاً ۲ گزارش، ۴ جلسه یا ۳ نوبت سرویس در هر دوره. بسته زمانی می‌تواند بدون تعهد باشد.')->columnSpanFull(),
                Forms\Components\Placeholder::make('empty_catalog')->label('اولین نوع تحویل را تعریف کنید')->content('ابتدا «افزودن تعهد» را بزنید، سپس کنار فهرست نوع تحویل از دکمه + استفاده کنید؛ مثلاً گزارش با واحد «گزارش». این نوع در همه بسته‌ها قابل استفاده است.')->visible(fn () => ! DeliverableType::query()->exists())->columnSpanFull(),
                ServiceOfferingVersionResource::deliverablesField(false, true)->addActionLabel('افزودن تعهد'),
            ]),
            Step::make(self::STEPS['features'])->id('features')->schema([
                Forms\Components\Placeholder::make('features_help')->hiddenLabel()->content('تعهدات، کارهایی هستند که باید انجام شوند؛ ویژگی‌ها، مشخصات و شرایط ارائه آن خدمات هستند. این مرحله اختیاری است؛ در صورت نیاز نداشتن، به مرحله بعد بروید.')->columnSpanFull(),
                ServiceOfferingVersionResource::featuresField(),
            ]),
            Step::make(self::STEPS['review'])->id('review')->schema([
                Forms\Components\Placeholder::make('summary')->hiddenLabel()->content(fn (Forms\Get $get) => view('filament.service-offerings.summary', ['data' => $get()]))->columnSpanFull(),
            ]),
        ])->columnSpanFull()->extraAttributes(['dir' => 'rtl'])
            ->extraAlpineAttributes(['x-on:offering-wizard-step.window' => 'if (getSteps().includes($event.detail.step)) { step = $event.detail.step; scroll(); }'])
            ->nextAction(fn (Forms\Components\Actions\Action $action) => $action->label('مرحله بعد'))
            ->previousAction(fn (Forms\Components\Actions\Action $action) => $action->label('مرحله قبل'))
            ->submitAction(view('filament.service-offerings.submit'));
    }

    private static function schedulingFields(): array
    {
        $fields = ServiceOfferingVersionResource::schedulingFields();
        // Hide unrelated defaults without discarding them from a resumed legacy draft.
        foreach ($fields as $field) {
            match ($field->getName()) {
                'scheduling_defaults.cycle_anchor_day' => $field->visible(fn (Forms\Get $get) => $get('schedule_mode') === 'recurring')->dehydratedWhenHidden(),
                'scheduling_defaults.monthly_hour_limit_minutes' => $field->visible(fn (Forms\Get $get) => $get('delivery_mode') !== 'tasks' && $get('schedule_mode') === 'recurring')->dehydratedWhenHidden(),
                'scheduling_defaults.start_date' => $field->helperText('اختیاری؛ تاریخ نهایی در قرارداد مشتری تعیین می‌شود.'),
                'scheduling_defaults.end_date' => $field->visible(fn (Forms\Get $get) => $get('schedule_mode') === 'fixed_period')->dehydratedWhenHidden(),
            };
        }

        return $fields;
    }

    public static function errorStep(string $field): string
    {
        $field = preg_replace('/^data\./', '', $field);
        if (in_array(explode('.', $field)[0], ['service_id', 'name', 'description', 'sort_order'], true)) {
            return 'package';
        }
        if (str_starts_with($field, 'deliverables')) {
            return 'deliverables';
        }
        if (str_starts_with($field, 'features')) {
            return 'features';
        }

        return in_array($field, ['review', 'status'], true) ? 'review' : 'pricing';
    }
}
