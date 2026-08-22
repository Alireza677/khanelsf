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
use Illuminate\Support\Facades\Validator;

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
            $attribution->put($request, $form, $this->requestContext($validated), $this->instanceToken($validated));

            return redirect()->route('forms.show', $form->slug);
        }

        return view('forms.show', [
            'form' => $form,
            'fields' => $schema->fields($form),
            'instanceToken' => $this->instanceToken($request->all()) ?? $attribution->activeInstance($request, $form),
        ]);
    }

    public function capture(Request $request, string $slug, FormAttributionSession $attribution): RedirectResponse
    {
        $form = Form::query()->published()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate($this->attributionRules());
        $attribution->put($request, $form, $this->requestContext($validated), $this->instanceToken($validated));

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
        $instanceToken = $this->instanceToken($validated);
        $attribution->put($request, $form, $this->requestContext($validated), $instanceToken);

        return view('forms.modal', [
            'form' => $form,
            'fields' => $schema->fields($form),
            'instanceToken' => $instanceToken,
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
        $validator = Validator::make($request->all(), [
            ...$schema->validationRules($form),
            'website' => ['prohibited'],
            '_context_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            '_context_page_url' => ['nullable', 'string', 'max:2048'],
            '_context_block_id' => ['nullable', 'string', 'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i'],
            '_display_mode' => ['nullable', 'in:page,modal'],
            '_form_instance' => ['nullable', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{0,99}$/'],
        ]);
        $instanceToken = $this->instanceToken($request->all());

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, $this->errorBag($instanceToken))
                ->withInput()
                ->with('_form_feedback_instance', $instanceToken);
        }

        $validated = $validator->validated();
        $requestContext = $this->requestContext($validated);

        unset(
            $validated['website'],
            $validated['_context_page_id'],
            $validated['_context_page_url'],
            $validated['_context_block_id'],
            $validated['_display_mode'],
            $validated['_form_instance'],
        );

        $hasExplicitContext = collect($requestContext)->contains(fn (mixed $value): bool => filled($value));
        $context = $hasExplicitContext
            ? $attribution->normalize($requestContext)
            : $attribution->get($request, $form, $instanceToken);

        if ($context === []) {
            $context = $attribution->normalize($this->requestContext($request->all()));
        }

        $submission = $submissions->submit($form, $validated, $context);
        $attribution->forget($request, $form, $instanceToken);

        $redirect = $request->input('_display_mode') === 'modal'
            ? redirect()->route('forms.show', $form->slug)
            : back();

        if ($form->isCalculator()) {
            $state = [
                'form_id' => $form->getKey(),
                'calculation_result' => $submission->calculation_result,
                'report_url' => URL::temporarySignedRoute(
                    'forms.submissions.calculator-report',
                    now()->addMinutes(30),
                    ['submission' => $submission],
                ),
            ];

            return $instanceToken === null
                ? $redirect->with('calculator_result_state', $state)
                : $redirect->with("calculator_result_instances.{$instanceToken}", $state);
        }

        $message = data_get($form->settings, 'success_message', 'Thanks, your information has been received.');

        return $instanceToken === null
            ? $redirect->with('form_success', $message)
            : $redirect->with("form_success_instances.{$instanceToken}", $message);
    }

    private function attributionRules(): array
    {
        return [
            '_context_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            '_context_page_url' => ['nullable', 'string', 'max:2048'],
            '_context_block_id' => ['nullable', 'string', 'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i'],
            '_form_instance' => ['nullable', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{0,99}$/'],
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

    private function instanceToken(array $data): ?string
    {
        $value = $data['_form_instance'] ?? null;

        return is_string($value) && preg_match('/^[a-z0-9][a-z0-9_-]{0,99}$/', $value) === 1
            ? $value
            : null;
    }

    private function errorBag(?string $instanceToken): string
    {
        return $instanceToken === null ? 'default' : 'form_'.substr(hash('sha256', $instanceToken), 0, 24);
    }
}
