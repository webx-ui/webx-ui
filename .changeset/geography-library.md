---
'@webx-ui/php': minor
---

New library `webx-ui/geography` — the shared geography of a site (delivery spec §4): tables `geo_countries`, `geo_regions`, `geo_region_aliases`, `geo_localities` and `geo_locality_names`; `Normalizer::key()` (NFKC, one apostrophe, one dash, quotes and dots dropped, no language rules); `LocalitySearch::suggest()` — localities by a prefix of a name, an alias or any later word of a name, heaviest first, the heaviest of the country for an empty query, switched-off ones never, in two queries; country names from `symfony/intl`; `webx:geo:import <file>` with `--activate` and `--dry-run`, idempotent.

The geography kept by hand (decision 42): `Editor` turns countries on, creates and edits regions and localities (`created_by = manual`), finds a similar locality before a second one is made (`DuplicateLocality`, `force` to create anyway), marks the fields an administrator changed on a carrier's or imported record (`manual_fields`, `isManual()`) so that a sync leaves them, and deletes a locality nothing holds or switches it off when `LocalityReferences` says something does.
