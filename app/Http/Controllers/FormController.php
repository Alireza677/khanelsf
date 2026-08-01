<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\FormAttributionSession;
use App\Services\FormSchema;
use App\Services\FormSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class FormController extends Controller
{
    public function show(
        Request $request,
        string $slug,
        FormSchema $schema,
        FormAttributionSession $attribution,
    ): View|RedirectResponse {
        $form = Form::query()->published()->where('slug', $slug)->firstOrFail();

        if ($request->hasAny(['_context_page_id', '_context_page_url', '_context_block_id'])) {
            $validated = $request->validate($this->attributionRules());
            $attribution->put($request, $form, $this->requestContext($validated));

            return redirect()->route('forms.show', $form->slug);
        }

        return view('forms.show', [
            'form' => $form,
            'fields' => $schema->fields($form),
        ]);
    }

    public function capture(Request $request, string $slug, FormAttributionSession $attribution): RedirectResponse
    {
        $form = Form::query()->published()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate($this->attributionRules());
        $attribution->put($request, $form, $this->requestContext($validated));

        return redirect()->route('forms.show', $form->slug);
    }

    public function modal(
        Request $request,
        string $slug,
        FormSchema $schema,
        FormAttributionSession $attribution,
    ): View {
        $form = Form::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();
        $validated = $request->validate($this->attributionRules());
        $attribution->put($request, $form, $this->requestContext($validated));

        return view('forms.modal', [
            'form' => $form,
            'fields' => $schema->fields($form),
        ]);
    }

    public function store(
        Request $request,
        string $slug,
        FormSchema $schema,
        FormSubmissionService $submissions,
        FormAttributionSession $attribution,
    ): RedirectResponse {
        $form = Form::query()->published()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate([
            ...$schema->validationRules($form),
            'website' => ['prohibited'],
            '_context_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            '_context_page_url' => ['nullable', 'string', 'max:2048'],
            '_context_block_id' => ['nullable', 'string', 'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i'],
            '_display_mode' => ['nullable', 'in:page,modal'],
        ]);

        unset(
            $validated['website'],
            $validated['_context_page_id'],
            $validated['_context_page_url'],
            $validated['_context_block_id'],
            $validated['_display_mode'],
        );

        $context = $attribution->get($request, $form);

        if ($context === []) {
            $context = $attribution->normalize($this->requestContext($request->all()));
        }

        $submission = $submissions->submit($form, $validated, $context);
        $attribution->forget($request, $form);

        $redirect = $request->input('_display_mode') === 'modal'
            ? redirect()->route('forms.show', $form->slug)
            : back();

        if ($form->isCalculator()) {
            return $redirect->with('calculator_result_state', [
                'form_id' => $form->getKey(),
                'calculation_result' => $submission->calculation_result,
                'report_url' => URL::temporarySignedRoute(
                    'forms.submissions.calculator-report',
                    now()->addMinutes(30),
                    ['submission' => $submission],
                ),
            ]);
        }

        return $redirect->with(
            'form_success',
            data_get($form->settings, 'success_message', 'Thanks, your information has been received.'),
        );
    }

    private function attributionRules(): array
    {
        return [
            '_context_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            '_context_page_url' => ['nullable', 'string', 'max:2048'],
            '_context_block_id' => ['nullable', 'string', 'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i'],
        ];
    }

    private function requestContext(array $data): array
    {
        return [
            'page_id' => $data['_context_page_id'] ?? null,
            'page_url' => $data['_context_page_url'] ?? null,
            'block_id' => $data['_context_block_id'] ?? null,
        ];
    }
}
