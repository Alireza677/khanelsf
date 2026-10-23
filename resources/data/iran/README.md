# Iran province/city data

Vendored without modification from [sajaddp/list-of-cities-in-Iran](https://github.com/sajaddp/list-of-cities-in-Iran), by Sajad Dehshiri and contributors.

- Dataset/schema version: **3.1.2**.
- Pinned commit: **ace21bb251f96ced350bbc01541acffd71121feb**.
- Retrieved: **2026-10-23**.
- Administrative source: upstream `offical/list.xlsx`, covering the end of **1404 Solar Hijri**, per the upstream manifest. This is the latest source advertised by the repository at retrieval, not a guarantee that no subsequent administrative decisions exist.
- Source workbook SHA-256: `e58107e6d478366f98042c3f25b2f27032363976f0ce25dab91fc55ea03ad1df`.

## Files and meaning

| Local file | Upstream path | Purpose |
| --- | --- | --- |
| provinces.json | dist/json/provinces.json | All 31 provinces |
| cities-filtered.json | dist/json/cities-filtered.json | All 1481 real cities; the only city catalogue used by public forms and server validation |
| urban-zones.json | dist/json/urban-zones.json | All 191 urban zones, preserved for reference; `city_id` identifies their parent real city |
| cities.json | dist/json/cities.json | Complete source projection of 1672 city-level records, including zones |
| classification.json | classification/urban-zones.json | Upstream classification/evidence for separating zones from real cities |
| manifest.json | dist/manifest.json | Original version, counts, source checksum and generation metadata |
| verification.json | dist/verification.json | Original upstream validation report |
| LICENSE | LICENSE | Original GPLv3 license text |

`checksums.json` records SHA-256 checksums of the vendored files; the local `.gitattributes` disables line-ending conversion for these files so upstream bytes survive checkouts across platforms.

City (شهر) is not county (شهرستان). No county records are offered in this field. The zone split is an upstream derived classification, not an independent official administrative level. Relationships use explicit `province_id` and `city_id`; never infer parents from digits or rebuild IDs from array positions/names. Original IDs are preserved; application boundaries and stored answers represent IDs as decimal strings. No coordinates/GeoNames enrichment is included or used.

## Commercial use and redistribution

Upstream licenses its project under **GNU GPL v3.0**, not MIT or public domain. Commercial use and charging money are permitted; commercial use does not remove copyleft obligations. Keep this attribution and `LICENSE` with redistributed data. When conveying modified covered data, provide the corresponding editable source and GPL rights, record modifications, and satisfy the GPL's applicable distribution requirements. These unmodified JSON files are the editable dataset used by King CMS; upstream generation inputs/tools are available at the pinned [source revision](https://github.com/sajaddp/list-of-cities-in-Iran/tree/ace21bb251f96ced350bbc01541acffd71121feb).

GPL has no AGPL-style network-use source provision. Whether a particular distributed product forms a combined derivative work or mere aggregation depends on how it is combined/distributed; putting data in a separate folder alone does not settle that question. This import does not assert that an otherwise proprietary distribution is automatically GPL-compatible. A distribution requiring permissive-only licensing needs a separate upstream license or another suitably licensed source. See the [official GPL text](https://www.gnu.org/licenses/gpl-3.0.html) and [GNU GPL FAQ](https://www.gnu.org/licenses/gpl-faq.html).

## Updating

1. Review the upstream release, license, official source year, compatibility/migration notes, and classification changes. Choose an immutable commit; do not fetch `main` during application requests or deployment builds.
2. Download the exact upstream paths in the table from `https://raw.githubusercontent.com/sajaddp/list-of-cities-in-Iran/<commit>/<path>`, replacing the local files together. Preserve original bytes. Update this document's version, commit, retrieval date and source checksum; regenerate `checksums.json` from local files.
3. Confirm province count, unique IDs, city-to-province links, zone-to-city links, and the disjoint union of real cities plus zones equalling the complete city list. Review ID changes against previous data: never silently reuse a retired ID. Adjust the focused test's expected counts/version after reviewing the changes.
4. Run `vendor/bin/phpunit tests/Unit/IranProvinceCityFieldTest.php` and the frontend build. Manually check Page, Modal, builder preview, changing province, searching Persian/Arabic letter variants, and old submission display.
5. Restart long-running PHP processes after replacing the catalogue; the service caches the immutable files per process. Existing answers retain their original names and version and are never resolved again against new data.

There is no runtime external API, database table or migration for this catalogue. Only real-city browser data is embedded into forms containing the new field; full zone/reference data stays server-side.
