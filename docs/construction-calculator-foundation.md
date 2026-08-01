# Schema-driven Calculator Foundation

Calculator forms use the same `forms.schema` JSON contract as normal forms. Runtime PHP no longer owns construction questions or scoring matrices.

```text
Published calculator Form schema
    -> page/step markers + choice fields + contact fields
    -> FormSchema normalization and validation
    -> schema-driven score aggregation
    -> unchanged FormSubmission + Lead transaction and attribution
```

Choice fields (`image_choice` and `radio_card`) store `value`, `label`, optional `image`, and a simple `scores` map on each option. `schema.calculator.recommendations` maps those score keys to result labels. The first recommendation in schema order wins a tied highest score, preserving deterministic behavior without a rule engine.

Migration `2026_07_16_000003_migrate_construction_calculator_to_schema.php` prepends the original eight construction questions and step markers to every existing `construction_process_v1` form. It preserves existing contact fields and the calculator identifier. The original option values, Persian labels, matrix values, tie order, and result labels are unchanged.

Normal forms remain schema-driven and do not invoke the calculator service. CTA context, session attribution, submission payloads, and Lead creation continue through the existing flow.

Current boundaries: no conditional branching, formulas, weighted expressions, cross-field rules, result ranges, or admin rule builder. Scoring is additive metadata on rich choice options only.
