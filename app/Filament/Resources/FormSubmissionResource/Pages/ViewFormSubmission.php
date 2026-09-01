<?php

namespace App\Filament\Resources\FormSubmissionResource\Pages;

use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\LeadResource;
use App\Services\FormSubmissionPresenter;
use App\Services\FormSubmissionSubmitterResolver;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    protected static string $view = 'filament.resources.form-submission-resource.pages.view-form-submission';

    public function getTitle(): string
    {
        return 'مشاهده ورودی فرم';
    }

    protected function getViewData(): array
    {
        $record = $this->getRecord();
        $record->loadMissing(['form', 'lead', 'page', 'attachments']);
        $presenter = app(FormSubmissionPresenter::class);
        $submitter = app(FormSubmissionSubmitterResolver::class);

        return [
            'submission' => $record,
            'answerGroups' => $presenter->answerGroups($record),
            'files' => $presenter->files($record),
            'calculation' => (object) ['result' => data_get($record->calculation_result, 'result'), 'scores' => $presenter->calculationScores($record)],
            'technicalFields' => $presenter->rawFields($record),
            'submitter' => (object) ['name' => $submitter->resolve($record), 'email' => $submitter->email($record), 'phone' => $submitter->phone($record)],
            'sourceLabel' => match ($record->source) {
                'website' => 'وب‌سایت', 'manual' => 'دستی', default => $record->source ?: '—'
            },
            'statusLabel' => match ($record->lead?->status ?? 'received') {
                'received' => 'دریافت‌شده', 'new' => 'جدید', 'contacted' => 'تماس گرفته‌شده',
                'qualified' => 'واجد شرایط', 'closed' => 'بسته‌شده', 'archived' => 'بایگانی‌شده',
                default => $record->lead?->status ?? 'دریافت‌شده',
            },
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewLead')
                ->label('مشاهده سرنخ')
                ->icon('heroicon-o-user')
                ->url(fn (): ?string => $this->getRecord()->lead
                    ? LeadResource::getUrl('view', ['record' => $this->getRecord()->lead])
                    : null)
                ->visible(fn (): bool => filled($this->getRecord()->lead)),
            Actions\Action::make('calculatorReport')
                ->label('دریافت گزارش PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => URL::temporarySignedRoute(
                    'forms.submissions.calculator-report',
                    now()->addMinutes(30),
                    ['submission' => $this->getRecord()],
                ))
                ->openUrlInNewTab()
                ->visible(fn (): bool => filled($this->getRecord()->calculation_result)),
        ];
    }
}
