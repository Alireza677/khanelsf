<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\Calculators\CalculatorManager;
use App\Services\Calculators\InvalidCalculatorConfiguration;
use App\Services\FormAttributionSession;
use App\Services\FormSchema;
use App\Services\FormSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use App\Support\FormSubmitConfirmation;
use App\Support\FormPagePresentation;
use Illuminate\Validation\ValidationException;

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

        $presentation = FormPagePresentation::settings($form);
        $instanceToken = $this->instanceToken($request->all()) ?? $attribution->activeInstance($request, $form);
        if ((int) $request->session()->get('_form_feedback_form_id') === (int) $form->getKey()) {
            $instanceToken = $this->instanceToken(['_form_instance' => $request->session()->get('_form_feedback_instance')]);
        }
        $resultRedirect = $request->session()->get('calculator_result_redirect');
        if ($form->isCalculator() && (int) data_get($resultRedirect, 'form_id') === (int) $form->getKey()) {
            // Attribution is consumed after submission. Its instance must still own the result GET.
            $instanceToken = $this->instanceToken(['_form_instance' => data_get($resultRedirect, 'instance_token')]);
            $request->session()->forget('calculator_result_redirect');
        }

        return view('forms.show', [
            'form' => $form,
            'presentation' => $presentation,
            'hero' => FormPagePresentation::hero($presentation),
            'fields' => $schema->fields($form),
            'instanceToken' => $instanceToken,
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
        CalculatorManager $calculators,
    ): RedirectResponse {
        $form = Form::query()->published()->where('slug', $slug)->firstOrFail();
        $instanceToken = $this->instanceToken($request->all());
        if ($form->isCalculator()) {
            // Retain ownership of validation feedback even after a previous success consumed attribution.
            $request->session()->flash('_form_feedback_form_id', $form->getKey());
            // A failed or new attempt must never display the preceding attempt's result.
            foreach (['calculator_result_state', ...array_map(
                fn (string $token): string => "calculator_result_instances.{$token}",
                array_keys((array) $request->session()->get('calculator_result_instances', [])),
            )] as $key) {
                if ((int) data_get($request->session()->get($key), 'form_id') === (int) $form->getKey()) {
                    $request->session()->forget($key);
                }
            }
            if ((int) data_get($request->session()->get('calculator_result_redirect'), 'form_id') === (int) $form->getKey()) {
                $request->session()->forget('calculator_result_redirect');
            }
        }
        try {
            $calculators->assertConfiguration($form);
        } catch (InvalidCalculatorConfiguration) {
            return back()->withErrors(['calculator' => InvalidCalculatorConfiguration::PUBLIC_MESSAGE], $this->errorBag($instanceToken))
                ->withInput()->with('_form_feedback_instance', $instanceToken);
        }
        $request->merge($schema->normalizeSubmissionInput($form, $request->all()));
        $validator = Validator::make($request->all(), [
            ...$schema->validationRules($form),
            FormSubmitConfirmation::INPUT_KEY => FormSubmitConfirmation::enabled($form)
                ? ['required', 'accepted']
                : ['nullable'],
            'website' => ['prohibited'],
            '_context_page_id' => ['nullable', 'integer', 'exists:pages,id'],
            '_context_page_url' => ['nullable', 'string', 'max:2048'],
            '_context_block_id' => ['nullable', 'string', 'regex:/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i'],
            '_display_mode' => ['nullable', 'in:page,modal'],
            '_form_instance' => ['nullable', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{0,99}$/'],
        ], [
            ...$schema->validationMessages($form),
            FormSubmitConfirmation::INPUT_KEY.'.required' => 'برای ارسال فرم، تأیید این مورد الزامی است.',
            FormSubmitConfirmation::INPUT_KEY.'.accepted' => 'برای ارسال فرم، تأیید این مورد الزامی است.',
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
            $validated[FormSubmitConfirmation::INPUT_KEY],
            $validated['website'],
            $validated['_context_page_id'],
            $validated['_context_page_url'],
            $validated['_context_block_id'],
            $validated['_display_mode'],
            $validated['_form_instance'],
        );

        $files = collect($validated)
            ->filter(fn (mixed $value): bool => $value instanceof UploadedFile)
            ->all();
        $validated = collect($validated)
            ->reject(fn (mixed $value): bool => $value instanceof UploadedFile)
            ->all();

        $hasExplicitContext = collect($requestContext)->contains(fn (mixed $value): bool => filled($value));
        $context = $hasExplicitContext
            ? $attribution->normalize($requestContext)
            : $attribution->get($request, $form, $instanceToken);

        if ($context === []) {
            $context = $attribution->normalize($this->requestContext($request->all()));
        }

        $confirmationAudit = FormSubmitConfirmation::enabled($form)
            ? [
                'confirmed' => true,
                'confirmation_text_snapshot' => FormSubmitConfirmation::text($form),
            ]
            : null;

        try {
            $submission = $submissions->submit($form, $validated, $context, $files, $confirmationAudit);
        } catch (InvalidCalculatorConfiguration) {
            return back()->withErrors(['calculator' => InvalidCalculatorConfiguration::PUBLIC_MESSAGE], $this->errorBag($instanceToken))
                ->withInput()->with('_form_feedback_instance', $instanceToken);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors(), $this->errorBag($instanceToken))
                ->withInput()->with('_form_feedback_instance', $instanceToken);
        }
        $attribution->forget($request, $form, $instanceToken);

        $redirect = $request->input('_display_mode') === 'modal'
            ? redirect()->route('forms.show', $form->slug)
            : back();

        if ($form->isCalculator()) {
            $redirect->withInput($request->except(['_token', 'website']))
                ->with('_form_feedback_instance', $instanceToken)
                ->with('calculator_result_redirect', [
                    'form_id' => $form->getKey(),
                    'instance_token' => $instanceToken,
                ]);

            $state = [
                'form_id' => $form->getKey(),
                'submission_id' => $submission->getKey(),
                'display_mode' => $request->input('_display_mode', 'page'),
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
