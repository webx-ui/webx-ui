# webx-ui/geography

The shared geography of a site: countries, regions and localities in the site's languages, the
key their names are matched by, and the search a checkout picks a town with. A library with no
panel section of its own — the "Geography" screens and the MCP tools over it belong to
`webx-ui/module-delivery`; carriers' satellites (`delivery-<carrier>`) fill it through that
module's sync. Languages come from `webx-ui/localization`.

## What it owns

- **Tables** (migration `2026_01_01_000000_create_geo_tables`):
  - `geo_countries` — `code` (ISO 3166-1 alpha-2, primary key), `names` (own name by language,
    `null` — the name from `symfony/intl`), `active`, `position`. Not seeded: a row appears when a
    country is turned on, or when a carrier or the import brings a locality in it (off until turned on);
  - `geo_regions` — `country_code`, `code` (ISO 3166-2 without the country), `names`, `name_key`,
    `manual_fields`; unique `(country_code, name_key)`;
  - `geo_region_aliases` — other spellings of a region (`region_id`, `name_key`, `source`);
  - `geo_localities` — `country_code`, `region_id`, `district`, `kind`, `names`, `name_key`,
    `lat`, `lng`, `weight`, `active`, `created_by` (`manual`, `import`, `carrier:<key>`),
    `manual_fields`;
  - `geo_locality_names` — the search rows: a name per language (`name`), a carrier's spelling
    (`alias`), the tail of either from a later word on (`word`).
- **Models** in `WebxUi\Geography\Models`: `GeoCountry`, `GeoRegion`, `GeoRegionAlias`,
  `GeoLocality`, `GeoLocalityName`. Saving a locality rewrites its `name` and `word` rows
  (`NameIndex::sync()`); aliases stay.
- **`Normalizer::key($name)`** — NFKC, lower case, apostrophes to `'`, dashes to `-`, quotes and
  dots dropped, spaces collapsed; no language rules. `Normalizer::tails()` gives the `word` rows.
- **`LocalityKind`** — the closed list `city`, `town`, `township`, `village`, `settlement`,
  `other`, with the weight each type adds.
- **`LocalitySearch::suggest($country, $query, $locale, $limit = 10)`** — active localities whose
  name, alias or later word starts with the query, heaviest first; an empty query gives the
  heaviest of the country. Two queries; not cached on purpose.
- **`Editor`** — the geography kept by hand: `enableCountry()`, `disableCountry()`,
  `removeCountry()` (deleted while it has no regions or localities, otherwise only switched off),
  `createRegion()`, `updateRegion()`, `deleteRegion()` (refused while it has localities),
  `similar()`, `createLocality()` (`created_by = manual`, throws `DuplicateLocality` with the
  similar ones unless `force`), `updateLocality()` (marks the changed fields of a carrier's or
  imported record in `manual_fields`), `removeLocality()` (returns `Removal::Deleted`, or
  `Removal::Deactivated` when something holds it), `activate()`, `deactivate()`. Every refusal
  is a `GeographyException` with a message for a person.
- **`LocalityReferences`** — who holds a locality; owners register from their providers.
- **`Matcher`** — binds a carrier's `Matching\LocalityRecord` to a shared locality (§4.3):
  `matchPage($carrier, $records, $current)` returns a `MatchResult` per external id — `status`
  (`matched`, `pending`, `ignored`), `locality_id`, `method` (`auto_exact`, `auto_coords`,
  `created`, `manual`), `reason` (`region_unknown`, `ambiguous`, `kind_mismatch`, `not_found`),
  `candidates`. Keeps no rows: the binding table is the delivery module's. A settled binding is
  kept; only the leading carrier of a country (`LeadingCarriers`, config
  `webx-geography.countries.<code>.source`, else the first to sync) creates regions and localities;
  manual and imported localities are bound to, never duplicated or renamed. `bind()` and
  `create()` are the queue's actions. Radius — `webx-geography.match_radius_km` (5).
- **Command** `webx:geo:import <file> [--activate] [--dry-run]` — JSON array or JSON lines of
  records shaped like a carrier's `LocalityRecord` (`country`, `name`, `kind`, `region`,
  `district`, `lat`, `lng`, `names`, `regionNames`).

## Change it without forking

| You want                                          | Do this                                                                                              |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| A table of yours to keep localities from deletion | `app(LocalityReferences::class)->table('my_table', 'locality_id')` in your provider's `boot()`       |
| A check that is not one column                    | `app(LocalityReferences::class)->register('orders', fn (int $id): bool => …)`                        |
| A country under another name                      | `app(Editor::class)->enableCountry('XX', names: ['en' => '…'])`                                      |
| Towns of a whole country at once                  | `php artisan webx:geo:import towns.jsonl --activate` (`--dry-run` first)                             |
| A town or region by hand from your own code       | `Editor::createLocality([...])` / `createRegion()`; catch `DuplicateLocality` and offer `->similar`  |
| A carrier's spelling to match next time           | `NameIndex::addAlias($locality, 't. Town D', 'carrier:<key>')`                                       |
| To know if a sync may overwrite a field           | `$locality->isManual('names')` — true for hand-made records and for fields an administrator changed  |
| The leading carrier kept elsewhere                | bind `Matching\LeadingCarriers` to your implementation in your provider's `register()`               |
| Suggestions for your own field                    | `app(LocalitySearch::class)->suggest('XX', $q, app()->getLocale())`, `->toArray()` per line for JSON |

## Do not

- Do not edit anything in `vendor/webx-ui/geography`. The rows above are the supported ways; if
  none fits, the package is missing a seam — say so instead of working around it.
- Do not insert into `geo_localities` with the query builder: the search rows in
  `geo_locality_names` are written by the model's `saved` event, and a locality without them is
  never suggested. Create and update through the model or the `Editor`.
- Do not match a carrier's towns one by one in a loop of `match()`: `matchPage()` loads the
  candidates of a page at once (four queries), `match()` per record is a query storm.
- Do not overwrite a stored binding the matcher returned with `kept = true`, and do not send a
  manual one back through matching expecting it to move: a person's binding is final.
- Do not search with `LIKE '%q%'`: no index takes it. A word inside a name is found by its
  `word` row with a prefix — that is what `LocalitySearch` does.
- Do not delete a locality outright when something may point at it: `Editor::removeLocality()`
  asks `LocalityReferences` and switches it off instead.
- Do not put a type word into a name ("t. Town D"): the type is `kind`, a carrier's spelling is
  an alias.
- Do not seed real countries' towns into tests or demos: use `XX` (private use) and invented
  names.

## Check your work

- `vendor/bin/phpunit packages/geography/tests` from `php/` of the monorepo — normalisation, search,
  the editor, the import, rolling the migration back.
- A locality you made appears in `app(LocalitySearch::class)->suggest('XX', '<first letters>', 'en')`;
  after `deactivate()` it does not.
- `MatcherTest` — the table of §4.3 cases: namesakes, type mismatch, unknown region, manual
  binding kept, follower's unmatched queued, leader bound to a hand-made town without a duplicate.
- `php artisan webx:geo:import <file> --dry-run` prints what would be created, updated and
  skipped, with the reason for every skipped record, and saves nothing.

## Read more

- [README.md](README.md) in this directory — the API with examples.
- The delivery spec, §4 (geography) and decision 42 (kept by hand):
  https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md
