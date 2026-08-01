# Lead Generation Foundation

This phase introduces reusable Forms, immutable Form Submissions, and actionable Leads. CTA remains a presentation block and never creates leads directly.

## Data flow

```text
Published Form + field schema
    -> reusable Blade renderer
    -> validated public submission
    -> FormSubmission payload + attribution
    -> related Lead + copied attribution
```

Attribution consists of `source`, `form_id`, optional `page_id` and `page_url`, and optional stable `block_id`. A future CTA form can render the existing form partial and pass its CTA `block_id`; no CTA contract change is required for the foundation.

Forms with attribution history are retained and should be archived instead of deleted.

## Schema v1

Form schema is JSON with a `fields` array. Editor-authored fields support `text`, `email`, `tel`, and `textarea`:

```json
{
  "fields": [
    { "name": "name", "label": "Name", "type": "text", "required": true },
    { "name": "phone", "label": "Phone", "type": "tel", "required": false },
    { "name": "email", "label": "Email", "type": "email", "required": false },
    { "name": "message", "label": "Message", "type": "textarea", "required": false }
  ]
}
```

Form `settings` currently supports `submit_label` and `success_message`. Calculator definitions may contribute fixed validated `select` fields before these Form-owned fields. Multi-step forms, conditional logic, and an admin rule builder remain intentionally deferred.

Calculator Forms use the same renderer and submission boundary. Their versioned PHP definition supplies calculation questions, while `calculation_result` JSON on the submission and related Lead stores the selected answers, scores, and recommendation. See `construction-calculator-foundation.md`.

## Display modes and CTA actions

Published Forms have a default `display_mode` of `page` or `modal`. CTA Form actions can override that default per usage. Page actions use `/forms/{slug}`. Modal actions retain that URL as a no-JavaScript fallback while lazy-loading only the reusable form fragment from `/forms/{form}/modal` when opened. Existing CTA actions without an override inherit the Form default.

CTA passes `page_id` when the source is a CMS Page, the current page URL when available, and its stable `block_id`. The existing submission boundary validates and stores these values before creating the related Lead.

Public CTA attribution is transported in the request body to a short-lived server-side session entry keyed by Form. Page actions store it and redirect to the clean `/forms/{slug}` URL; modal actions store it while returning the lazy fragment. Form markup does not repeat attribution as hidden fields. The entry is removed after a successful submission or discarded after 30 minutes. Legacy context query strings are accepted once and redirected to the clean URL.
