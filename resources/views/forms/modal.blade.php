<div class="form-action-modal__header">
    <h2 id="form-action-modal-title">{{ $form->name }}</h2>
    <button type="button" class="form-action-modal__close" data-form-action-modal-close aria-label="بستن">&times;</button>
</div>

@include('forms._form', [
    'form' => $form,
    'fields' => $fields,
    'displayMode' => 'modal',
    'instanceToken' => $instanceToken ?? null,
])
