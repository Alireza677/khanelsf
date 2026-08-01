<?php

namespace App\Filament\Resources\FormSubmissionResource\Pages;

use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\LeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

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
