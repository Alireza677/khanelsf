<?php

namespace App\CMS\Actions\Resolution;

use App\CMS\Actions\Contracts\ActionTargetResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Data\ResolvedAction;
use App\CMS\Actions\Enums\ActionResolutionReason;
use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\Actions\Enums\FormDisplay;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use App\Models\Form;
use Illuminate\Support\Facades\Route;

final class FormActionResolver implements ActionTargetResolver
{
    public function __construct(
        private readonly ActionDestinationValidator $validator,
    ) {}

    public function resolve(
        ActionDestination $destination,
        ResolutionContext $context,
    ): ResolvedAction {
        if ($destination->coreType() !== CoreActionType::Form
            || $this->validator->validate($destination)->isInvalid()) {
            return ResolvedAction::invalid(
                $destination->type,
                ActionResolutionReason::InvalidDestination->value,
            );
        }

        $form = Form::query()
            ->whereKey($destination->referenceId)
            ->first(['id', 'slug', 'status', 'display_mode']);

        if (! $form instanceof Form) {
            return ResolvedAction::unresolved(
                CoreActionType::Form->value,
                ActionResolutionReason::EntityNotFound->value,
                ['reference_id' => $destination->referenceId],
            );
        }

        if ($form->status !== 'published') {
            return ResolvedAction::unavailable(
                CoreActionType::Form->value,
                ActionResolutionReason::EntityUnpublished->value,
                ['reference_id' => $destination->referenceId],
            );
        }

        if (! Route::has('forms.context') || ! Route::has('forms.show')) {
            return ResolvedAction::unavailable(
                CoreActionType::Form->value,
                ActionResolutionReason::RouteUnavailable->value,
                ['reference_id' => $destination->referenceId],
            );
        }

        $display = FormDisplay::tryFrom((string) $destination->display)
            ?? FormDisplay::tryFrom((string) $form->display_mode)
            ?? FormDisplay::Page;

        if ($display === FormDisplay::Modal && ! Route::has('forms.modal')) {
            return ResolvedAction::unavailable(
                CoreActionType::Form->value,
                ActionResolutionReason::RouteUnavailable->value,
                ['reference_id' => $destination->referenceId],
            );
        }

        return ResolvedAction::resolved(
            CoreActionType::Form->value,
            route('forms.context', $form->slug),
            metadata: [
                'reference_id' => $form->getKey(),
                'form_id' => $form->getKey(),
                'display' => $display->value,
                'page_url' => route('forms.show', $form->slug),
                'modal_url' => $display === FormDisplay::Modal
                    ? route('forms.modal', $form->slug)
                    : null,
            ],
        );
    }
}
