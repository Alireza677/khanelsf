<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Services\CalculatorSubmissionReport;
use Illuminate\Http\Response;

final class CalculatorSubmissionReportController extends Controller
{
    public function __invoke(FormSubmission $submission, CalculatorSubmissionReport $report): Response
    {
        abort_if(blank($submission->calculation_result), 404);

        return $report->download($submission);
    }
}
