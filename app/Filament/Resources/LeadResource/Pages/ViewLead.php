<?php

namespace App\Filament\Resources\LeadResource\Pages;

use App\Filament\Resources\FormSubmissionResource;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\URL;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewSubmission')
                ->label('مشاهده ورودی فرم')
                ->icon('heroicon-o-inbox-stack')
                ->url(fn (): ?string => $this->getRecord()->submission
                    ? FormSubmissionResource::getUrl('view', ['record' => $this->getRecord()->submission])
                    : null)
                ->visible(fn (): bool => filled($this->getRecord()->submission)),
            Actions\Action::make('calculatorReport')
                ->label('دریافت گزارش PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => URL::temporarySignedRoute(
                    'forms.submissions.calculator-report',
                    now()->addMinutes(30),
                    ['submission' => $this->getRecord()->submission],
                ))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->hasCalculatorSubmission()),
            Actions\EditAction::make(),
        ];
    }

    private function hasCalculatorSubmission(): bool
    {
        /** @var Lead $lead */
        $lead = $this->getRecord();

        return filled($lead->submission?->calculation_result);
    }
}
