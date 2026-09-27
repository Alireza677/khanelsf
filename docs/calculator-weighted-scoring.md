# Weighted scoring configuration and admin editor

## Existing architecture

`Form` casts `forms.schema` to an array (schema version 2). Calculator configuration
is not stored in `settings` or a separate table. `FormResource` converts result and
simple-score maps to Filament repeater rows on fill, then converts them back on
create/save. `FormSchemaIdentityManager` maintains field/option identities.
`FormSchema` supplies normalized fields and submission rules.

`CalculatorManager` adds selected option `scores` for `image_choice`, `radio_card`,
`radio`, and `checkbox`. It invokes the independent eligibility evaluator, ranks
eligible results, and breaks ties by recommendation order. `select` remains an
unscored field. `FormSubmissionService` stores the calculation result and answer
snapshot in its existing submission/lead transaction. The public result modal and
PDF report consume that stored result; the builder preview renders field controls
and does not have a separate calculation engine.

## Persisted contract

All paths below are relative to `forms.schema`:

```json
{
  "calculator": {
    "scoring_mode": "weighted",
    "criteria": [
      {
        "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
        "label": "سرعت اجرا",
        "description": "اهمیت زمان اجرای پروژه",
        "base_weight": 0
      }
    ],
    "recommendations": { "lsf": "سازه سبک" },
    "criterion_scores": {
      "lsf": { "01ARZ3NDEKTSV4RRFFQ69G5FAV": 5 }
    }
  },
  "fields": [
    {
      "key": "priority",
      "label": "اولویت",
      "type": "radio",
      "options": [
        {
          "value": "speed",
          "label": "اجرای سریع",
          "scores": { "lsf": 3 },
          "criterion_weights": { "01ARZ3NDEKTSV4RRFFQ69G5FAV": 8 }
        }
      ]
    }
  ]
}
```

The existing `calculator.recommendations` string map stays intact. Result
performance is a sibling map: `calculator.criterion_scores[result_key][criterion_id]`.
This avoids changing every result consumer and eligibility reference into an
object-based result contract. Existing `options[].scores` remain independent.

`CalculatorScoringSchema` owns the new contract:

- `scoringMode($schema)` resolves absent mode to `simple`; explicit invalid modes
  (including null) fail validation.
- `newCriterion($label, $description, $baseWeight)` creates a validated criterion
  with a generated ULID. Normalization also generates an ID when the `id` member
  is absent. Supplied malformed, empty, null, or duplicate IDs are rejected.
- `normalize($schema, $path)` accepts the stored schema shape, validates new
  metadata, and returns an idempotent canonical schema. Existing IDs and reference
  keys are normalized to uppercase. Labels never determine IDs.
- Criterion labels are required (maximum 255 characters); optional descriptions
  allow null (maximum 2000 characters). Admin text is Persian; no script restriction
  is imposed on free text.
- Base weights default to 0. Base weights and option effects accept finite numbers
  in 0..10; performance accepts finite numbers in 0..5. Decimal values and numeric
  strings are supported; strings are stored as numbers. Performance cells accept
  null, empty strings, and absent keys as numeric zero. Booleans, arrays, nonnumeric
  and nonfinite values are rejected rather than clamped. Weight validation is unchanged.
- Option-effect maps remain sparse. Performance matrices are completed for every
  configured result key × criterion ID before validation/calculation and on save.
  Missing/blank matrices and result rows become zero-filled matrices; administrators
  do not need to enter zeros manually.
- Valid but unknown/deleted criterion references are pruned from both kinds of
  maps. Rows for deleted results are pruned too. Malformed IDs/ranges are rejected
  before pruning, even on stale references.
- New metadata is validated even when retained under `simple`, allowing mode
  switching without discarding the weighted configuration.

## Boundaries and compatibility

The existing editor fill and create/save boundaries invoke the contract helper.
The admin editor uses the existing Filament/Livewire interactions:

- `روش محاسبه` selects simple or weighted scoring and displays the weighted explanation.
- `معیارهای تصمیم‌گیری` uses a collapsible, reorderable repeater. New rows generate
  a hidden ULID; deletion requires Persian confirmation and prunes dependent values.
- `ماتریس امتیاز نتایج` shows criteria as rows and result labels as columns, with
  compact Filament numeric inputs and horizontal scrolling. Empty cells save as zero.
- Each option has a collapsed `تأثیر این پاسخ بر معیارها` editor using criterion
  selects and numeric weights. Duplicate criterion choices are prevented. All choice
  types, including `select`, support weighted calculation.

Weighted controls only appear in weighted mode. Hidden sections/repeaters still
dehydrate, preserving both sets of scores across mode changes and saves. Hidden
criteria repeaters retain UUID row keys, so the storage adapter explicitly converts
them back to lists. Answer-effect repeater rows become the original criterion-ID map.
Criteria/results labels and order are reactive; matrix bindings use stable IDs/keys.
Normal editing, add, delete, and reorder operations use Livewire without page reloads.

`CalculatorWeightedEditor` prunes stale/malformed references on editor hydration so
imported stale mappings cannot break the UI. Domain validation remains strict on
save. `CalculatorPerformanceMatrix` uses ordinary Filament numeric fields, including
per-cell Persian validation naming the criterion and result. Filament validates
before dehydration, so the shared matrix normalizer runs on validation and
dehydration copies, as well as at the schema boundary used by storage and weighted
calculation. It never refills the live form on save failure, preserving entered values.
Blank matrix cells and blank effect weights are saved as zero. Technical identifiers
are never rendered as labels.

The existing simple-score controls are retained and hidden in weighted mode, with
their state still saved. Runtime `FormSchema` retains normalized weights
on rich choice options; the existing select label-map representation is unchanged.

There is no database migration, schema-version bump, new model save observer, or
rewrite of existing rows. Existing sparse weighted matrices are completed on read
for calculation/editing and persisted on the next successful save. Legacy schemas normalize without adding weighted members
or changing simple scores. Saving in the editor may explicitly persist `simple`.
Direct Eloquent/import writers must call the normalization helper, as editor
normalization is not a global model persistence hook.

## Shared static result content

Both scoring methods use the same optional
`calculator.result_content[result_key]` map in `forms.schema`:

```json
{
  "lsf": {
    "result_title": "عنوان نتیجه",
    "result_summary": "خلاصه کوتاه",
    "result_description": "توضیحات نتیجه",
    "result_note": "نکته یا هشدار پایانی"
  }
}
```

The result keys come exclusively from `calculator.recommendations`; this map
does not define additional results or duplicate their labels. The builder shows
one collapsible section per canonical result under `محتوای نتایج`, in both modes.
Renaming or reordering results and switching calculation methods preserve text.
The shared schema normalizer trims optional text, omits blank values and prunes
content for deleted results. Missing content stays absent in legacy schemas.

`CalculatorResultContent` resolves only the winning key after calculation. The
four optional fields are added to the existing `CalculationResult` snapshot;
`result` remains the canonical result label and `recommended_method` its key.
No winner (including weighted zero-weight results) means no static content.
The modal and PDF render escaped snapshot text and retain their legacy fallbacks.
Later form edits do not change historical submission or lead content.

The existing weighted `decision_report.explanations` below contains per-criterion
explanations, not static result copy. It remains intact and can coexist with the
shared content. No strategy-specific static storage was found to migrate; there
is no migration, scoring change or new dynamic explanation behavior.

## Decision report configuration (Phase 1 only)

An optional `calculator.decision_report` stores report metadata separately from
criteria and the numeric performance matrix:

```json
{
  "enabled": false,
  "top_factors_count": 3,
  "explanations": {
    "lsf": {
      "01ARZ3NDEKTSV4RRFFQ69G5FAV": "توضیح عملکرد این نتیجه در معیار"
    }
  }
}
```

`CalculatorScoringSchema` normalizes this member only in weighted mode and only
when present. An absent member stays absent. An empty configuration defaults to
disabled, a factor count of 3, and an empty explanations map. `enabled` requires a
boolean; `top_factors_count` requires a positive PHP/JSON integer (numeric strings
and floats are rejected), with no limit tied to the number of criteria.

Explanations use existing result keys and canonical uppercase criterion ULIDs,
never labels. Missing or null `explanations` normalize to an empty map. Explanation
values accept strings or null; text is trimmed and empty values are omitted.
Valid references to deleted results/criteria and empty result rows are pruned.
As with the performance matrix, malformed identifiers, duplicate canonical IDs,
invalid containers, and non-text values fail validation before pruning.
Normalization is idempotent. Simple mode preserves report metadata unchanged and
does not validate it; switching to weighted mode applies the contract.

This phase adds no report builder, admin controls, snapshot fields, or rendering.
The config does not yet change calculation, the existing three top factors, or PDF
output. No database migration is needed.

## Decision report admin editor

`CalculatorDecisionReportEditor` builds the weighted-only section after the
performance matrix using standard Filament Toggle, Tabs, and Textarea components.
There is no custom Blade component or JavaScript. Each result tab uses its machine
key as its ID; textareas bind directly to
`decision_report.explanations[result_key][criterion_id]`. Labels are presentation
only. Tab badges count current criteria with nonblank trimmed explanations and
are not stored. Read-only score hints read the existing performance matrix; matrix
cells now refresh on blur so these hints follow edits without duplicating scores.

The report group dehydrates its entire state, preserving `top_factors_count`
without a dedicated input. Turning the toggle off hides tabs but retains their
state. Simple mode has no visible report controls and preserves existing report
metadata verbatim, without adding a report to legacy simple forms. Legacy weighted
forms show a disabled toggle; saving uses the Phase 1 defaults. The editor does not
write to the database on fill or mode changes.

Tabs rebuild from current result/criterion identities. Deleted references no
longer render, while final pruning and text trimming remain at the existing
`CalculatorScoringSchema` save boundary. No calculation, result frontend, snapshot,
or PDF behavior is added by this editor.

## Phase 3 calculation and resolution

### Runtime decision report

After resolving eligibility, the winner, percentages and the existing three
`top_factors`, `WeightedCalculator` calls `DecisionReportBuilder`. The builder
only maps those factors to the winning result's normalized explanations; it never
scores, sorts, truncates, or consults `top_factors_count`. Missing explanations
receive a fixed neutral Persian fallback without removing the factor.

The report is omitted unless weighted mode, enabled configuration, a winner,
nonzero scoring capacity, and an eligible recommendation are all present. Its
version-1 shape contains `result_key`, `result_label`, `suitability_percentage`,
`intro`, `factors` (`criterion_id`, `label`, `contribution`, `explanation`),
`summary`, and plain `rendered_text`. Contributions and percentages retain their
decimal-string representation; prose uses Persian digits. No AI or random text
is involved.

The existing submission transaction saves this report in `calculation_result`
and copies it to the lead. `CalculationResultRows::weightedSummary` exposes the
stored report to both the modal and PDF. Both render escaped text in separate
factor sections, without showing raw contributions or rebuilding from the current
form. Snapshots without a report retain the original top-factor label list.
Changing explanations or disabling reports affects future submissions only.

`CalculatorManager` dispatches only explicit `weighted` forms to `WeightedCalculator`.
Absent mode and `simple` still execute the existing simple calculation path.
No eligibility rules or evaluation logic have changed.

1. Validate persisted weighted configuration. Unknown/deleted references are pruned;
   malformed IDs, invalid modes, missing result definitions, and invalid numeric
   ranges fail with a Persian log message containing the form ID and configuration
   error. Public submission returns a Persian error in the existing instance error
   bag without saving a submission/lead. Field rendering tolerates invalid weight
   metadata; it cannot bypass calculation-time validation.
2. Start each criterion weight at `base_weight` and add effects from selected
   `select`, `radio`, `radio_card`, `image_choice`, and every selected `checkbox`
   option. Optional unanswered choices contribute nothing. Unknown/duplicate answers
   are rejected. Client-provided scores/weights/matrices are ignored.
3. Compute every configured result, including those later excluded by eligibility:

   ```text
   weight[c] = base_weight[c] + sum(selected_option.criterion_weights[c])
   raw[result] = sum(weight[c] * performance[result][c])
   maximum = sum(weight[c] * 5)
   suitability[result] = 100 * raw[result] / maximum
   ```

   Missing entries are zero. Individual configured weights remain bounded 0..10,
   but accumulated weights are neither capped nor normalized. Performance remains
   0..5. An incomplete matrix is allowed and missing performance is zero.
4. Run the existing independent eligibility evaluator. Preserve the existing
   result-resolution contract: eligible results first, descending exact raw score
   within each eligibility group, and original recommendation order for equal scores.
   Only eligible results receive competitive ranks and can be recommended. Excluded
   results keep their actual raw scores and suitability percentages for audit.
5. If maximum is zero, return `no_score: true`, null percentages/recommendation/ranks,
   zero raw scores, and no factors. `no_eligible_recommendation` independently means
   every candidate was excluded. If maximum is positive but all performance values
   are zero, 0% is a valid score and the normal deterministic tie policy applies.

Arithmetic follows the project's BCMath convention. Normalized JSON numeric values
are converted to decimal strings (including scientific notation); additions and
products use the full operand scale, and comparisons never use floats. Raw scores,
weights, maximum, and contributions are persisted as decimal strings. Suitability
is rounded half-up to two decimal places only after scoring; ranking uses unrounded
raw scores. Presentation rounds raw scores to two places and uses Persian digits.

The existing result snapshot gains weighted-only fields: `scoring_mode`, `no_score`,
`criterion_weights`, `criterion_labels`, `criterion_contributions` (result × criterion),
`max_possible_score`, `suitability_percentages`, and `top_factors`. Ranking rows also
contain `raw_score` and `suitability_percentage`. Top factors are the three largest
positive weighted contributions to the selected recommendation, breaking ties by
criterion order, with labels captured at submission time.

`CalculationResultRows`, submission/lead presenters, the result modal, and the PDF
report read these snapshots. The UI presents calculated suitability based on the
configured model, without universal engineering claims. Simple snapshots receive
no new weighted fields. Existing submissions are never rewritten or recalculated.

## Manual checks

- Configure weights 8 and 10, performances 5/5 and 4/3: expect raw 90/62 and 100%/68.89%.
- Combine base weights and multiple checkbox selections; confirm effects add and
  aggregate weights can exceed 10. Also try decimal weights and a select question.
- Leave optional questions unanswered and matrix cells empty; they contribute zero.
- Make all weights zero: expect the Persian no-score state and no recommendation.
- Tie two eligible results; confirm the first configured result wins. Exclude that
  result through existing eligibility rules; confirm the next eligible result wins
  while the excluded result keeps its score. Exclude all results and check messaging.
- Submit, then edit criterion labels/matrix values: the stored result, factors, and
  report must retain their original values.
- Check a legacy/simple calculator and its historical report. Invalid imported
  weighted configuration should show safe public feedback and a useful Persian log.
