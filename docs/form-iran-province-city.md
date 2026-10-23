# Iran province and city field

In Form Builder, choose **فیلدهای انتخابی → استان و شهر ایران**. Set the existing label, required flag, and width. Edit, duplicate, remove, and reorder through the existing field actions. The builder canvas includes an interactive dependent-select preview; preview selections are never stored in `forms.schema` or submitted as answers.

The schema remains the source of truth:

```json
{"key":"location","label":"محل سکونت","type":"iran_province_city","required":true,"layout":{"span":6}}
```

The shared public form runtime renders searchable RTL province/city selects for standalone Page, embedded forms, and dynamically inserted Modal forms. City selection is disabled until a province is selected. A province change clears the city; city options contain only that province's real cities. Search normalizes Arabic/Persian yeh/kaf, combining marks, whitespace and zero-width joiners. Native keyboard choices, search arrow navigation, Escape, outside dismissal, instance isolation, and server validation use the existing select/form paths.

Requests contain only `location[province_id]` and `location[city_id]`. Server rules reject malformed values, unknown IDs, zones, extra keys/client-supplied names, incomplete answers and cities from another province. Optional fields allow both empty or omitted. Required fields require a valid pair. Names are resolved by the server immediately before the existing submission transaction/snapshot flow.

The existing JSON `form_submissions.payload.location` stores:

```json
{
  "province_id": "100",
  "province_name": "مرکزی",
  "city_id": "1000001001101",
  "city_name": "اراک",
  "dataset_version": "3.1.2"
}
```

An empty optional answer is `null`. The existing `_answer_snapshot` records the field label, structured raw value and readable `مرکزی، اراک` display value. Submission detail, Lead presentation, notification email and calculator report reuse those snapshots; raw admin fields also format the location readably. Historical answers never depend on future city lists or field labels. No extra Lead columns, score/eligibility operators, calculator scoring types, tables, routes, or migration are introduced.

Data provenance, the 31/1481/191 coverage split, license conditions and the update procedure are in [the dataset README](../resources/data/iran/README.md). Do not replace real cities with counties or enable urban zones in the public picker.

## Changed files

- Builder: `app/Filament/Resources/FormResource.php`, `resources/views/filament/forms/components/form-builder-editor.blade.php`, new `iran-location-preview.blade.php` in that directory, and `resources/views/filament/theme.blade.php`.
- Schema/validation/data: `app/Services/FormSchema.php`, new `app/Rules/IranProvinceCity.php`, new `app/Services/IranLocations.php`, and the new `resources/data/iran/` catalogue, provenance, license and integrity files.
- Storage/presentation: `app/Services/FormSubmissionService.php`, `app/Services/SubmissionAnswerSnapshot.php`, `app/Services/FormSubmissionPresenter.php`.
- Public runtime: `resources/views/forms/_form.blade.php`, new `_iran-province-city.blade.php` beside it, `resources/js/app.js`, new `resources/js/components/form-iran-locations.js`, `resources/js/components/form-page.js`, and `resources/css/app.css`.
- Verification/documentation: new `tests/Unit/IranProvinceCityFieldTest.php` and this guide. No existing scoring/eligibility, Lead persistence, Page/Modal configuration, database schemas or public routes are changed.
