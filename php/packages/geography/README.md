# webx-ui/geography

The shared geography of a [WebX UI](https://github.com/webx-ui/webx-ui) site: country → region →
locality, with names in the site's languages. A buyer picks a locality once from this one
directory; carriers bind their own ids to it, and a shop's own pick-up points stand on it too.

No panel of its own: the screens live in `webx-ui/module-delivery`.

```bash
composer require webx-ui/geography
php artisan migrate
```

## Names and keys

Every name is matched by its key — one rule for any alphabet, no transliteration:

```php
use WebxUi\Geography\Normalizer;

Normalizer::key('  O’Town–on–Sea ');   // "o'town-on-sea"
Normalizer::key('St. «Town»');          // "st town"
```

A locality saved through its model writes its search rows: a row per language, and a row per
later word of a name of several words, so that "Lower Town D" is found by "town".

## Suggestions

```php
use WebxUi\Geography\LocalitySearch;

$lines = app(LocalitySearch::class)->suggest('XX', 'tow', 'en');   // up to 10
$lines[0]->label;      // "Town D, Region B"
$lines[0]->toArray();  // id, name, kind, district, region, label
```

An empty query gives the heaviest localities of the country (`weight`: the type's share plus the
points in the place). Switched-off localities are never suggested.

## Kept by hand

A shop with no carrier still needs towns. `Editor` is what the panel and the MCP tools call:

```php
use WebxUi\Geography\DuplicateLocality;
use WebxUi\Geography\Editor;

$editor = app(Editor::class);

$editor->enableCountry('XX', names: ['en' => 'Demo land']);
$region = $editor->createRegion('XX', ['en' => 'Region A']);

try {
    $town = $editor->createLocality([
        'country' => 'XX',
        'names' => ['en' => 'Town A'],
        'kind' => 'city',
        'region_id' => $region->id,
    ]);
} catch (DuplicateLocality $duplicate) {
    $duplicate->similar;   // offer these instead; pass force: true to create anyway
}

$editor->updateLocality($town, ['weight' => 500]);
$editor->removeLocality($town);   // Removal::Deleted, or Deactivated when something holds it
$editor->removeCountry('XX');     // the same for a country: deleted only while nothing is in it
```

Records made by hand get `created_by = manual`. A field an administrator changes on a carrier's
record is listed in `manual_fields`, and `$locality->isManual('names')` tells a sync to leave it.

What holds a locality is registered by its owner:

```php
use WebxUi\Geography\LocalityReferences;

app(LocalityReferences::class)->table('delivery_points', 'locality_id');
```

## Carriers: matching and the queue

A carrier's locality is bound to a shared one by `Matcher` (spec §4.3–§4.4). The delivery sync
hands it a page of records with the bindings it stored, and stores what comes back:

```php
use WebxUi\Geography\Matcher;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Matching\MatchResult;

$record = new LocalityRecord(externalId: '417', country: 'XX', name: 'Village A', kind: 'village', region: 'Region A', lat: 10.0, lng: 20.0);

$results = app(Matcher::class)->matchPage('carrier-a', [$record], ['417' => MatchResult::fromRow('pending', null)]);
$results['417']->toArray();   // status, locality_id, method, reason, candidates
```

- bound already, or ignored by a person — kept (`kept = true`); a manual binding never moves;
- the region by its key or alias; unknown — `pending` / `region_unknown` (the leading carrier
  brings the region in instead);
- one locality of the same region, type and name or alias — `auto_exact`; several namesakes, or
  only another type, — the one within `webx-geography.match_radius_km` (5) as `auto_coords`, else
  `ambiguous` / `kind_mismatch` with the candidates and their distances;
- nothing — the leading carrier of the country creates the locality (`created`), any other carrier
  waits as `not_found`.

The leading carrier is `webx-geography.countries.<code>.source`; unset, the first carrier to sync
the country leads it (`LeadingCarriers` — the delivery module keeps it in the site settings).
Hand-made and imported localities are candidates like any other: bound to, never duplicated, and
never renamed — the leading carrier only fills in languages and coordinates nobody set by hand.
Each carrier spelling becomes an alias of the town and of its region, so the next carrier spelled
alike matches by itself.

The queue's buttons: `$matcher->bind($carrier, $record, $locality)` (manual), `$matcher->create($carrier, $record)`
(a shared locality from the record), `MatchResult::ignored()`.

## Import

```bash
php artisan webx:geo:import towns.jsonl --dry-run
php artisan webx:geo:import towns.jsonl --activate
```

One record per line (or a JSON array):

```text
{"country": "XX", "name": "Town A", "kind": "city", "region": "Region A", "lat": 1.5, "lng": 2.5, "names": {"de": "Stadt A"}}
```

A record matching a locality exactly (country, region, type, a name or alias) adds what it has
and nobody changed by hand; the rest become localities with `created_by = import`. The same file
twice changes nothing.

## License

MIT
