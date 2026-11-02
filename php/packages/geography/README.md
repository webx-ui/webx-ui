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
```

Records made by hand get `created_by = manual`. A field an administrator changes on a carrier's
record is listed in `manual_fields`, and `$locality->isManual('names')` tells a sync to leave it.

What holds a locality is registered by its owner:

```php
use WebxUi\Geography\LocalityReferences;

app(LocalityReferences::class)->table('delivery_points', 'locality_id');
```

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
