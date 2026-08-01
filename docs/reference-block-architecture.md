# Reference Block Architecture

Hero and CTA establish the CMS Reference Block Architecture. The shared lifecycle is:

```text
Persisted legacy or v2 data
    -> block-owned normalizer and v2 contract
    -> block-owned Filament schema
    -> shared editor hydration and block identity
    -> shared save boundary
    -> block-owned normalized Blade view
```

## Required parts

Each migrated reference block has:

- a `BlockDefinition` registered in `BlockRegistry`, owning its metadata, templates, capabilities, contract version, and Page/Template editor schema;
- a `BlockNormalizer` that converts legacy and v2 input into one idempotent, query-free canonical contract;
- compatibility with `BlockIdentityManager`, which creates or repairs `block_id` during hydration and persists it only through the save boundary;
- rendering that normalizes input before its block-owned Blade view reads the canonical contract.

`BlockEditorHydrator` applies the supported block normalizers by block type. `BlockEditorSaveManager` remains the single canonical persistence boundary and contains only genuinely block-specific save reconciliation, such as Hero media references.

## Block-specific behavior

Hero retains its media resolver, selector, stats, video, animation, and rollout flag behavior. CTA retains its classic/image templates, button structure, background image sizing, and always-v2 editor behavior. These concerns are not shared abstractions because their contracts and lifecycle rules differ.
