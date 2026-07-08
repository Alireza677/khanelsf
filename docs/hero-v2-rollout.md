# Hero v2 production rollout

Hero v2 rollout changes the editor path only. Rendering always normalizes legacy and v2 data into Hero Contract v2.

## Before rollout

Run the read-only audit and preserve its JSON output outside the release artifact:

```bash
php artisan cms:hero-v2:audit
php artisan cms:hero-v2:audit --json > hero-v2-baseline.json
```

The command exits `0` when no critical findings exist and `1` when rollout is blocked. Warnings and informational legacy-field findings do not block rollout. Confirm `rollout_status` is `ready` and `critical` is zero before enabling the editor.

## Enable

Set the deployment environment value without committing it:

```env
CMS_HERO_V2_EDITOR=true
```

Refresh Laravel's cached configuration using the cache workflow used by the deployment. When no platform-specific workflow exists, run:

```bash
php artisan config:clear
php artisan config:cache
```

## Verification

Select representative records: a simple Hero, media Hero, Hero with CTAs, stats, animations/effects, a Template Hero, and a document containing multiple Heroes.

For each record: open the editor, verify hydration, save without unrelated changes, reload, inspect persisted state, and verify frontend rendering. Monitor only Hero v2 validation/save failure logs. Then generate a second JSON audit and compare its stable `summary`, `issue_counts`, `legacy_fields`, and `findings` fields with the baseline. `generated_at`, duration, and query count are expected to vary.

## Rollback

Set:

```env
CMS_HERO_V2_EDITOR=false
```

Refresh configuration cache again. Unsaved legacy records return to the legacy editor and remain legacy when saved. Persisted v2 records continue using the v2 editor because flattening them would be destructive. Both legacy and v2 records continue rendering through the normalizer. No reverse conversion or data rewrite occurs.
