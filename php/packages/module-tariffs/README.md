# webx-ui/module-tariffs

Tariffs as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: price
cards with a name, a badge ("30 HOURS / 25$"), a price in a currency of the site's list — or words
instead of one ("On request") — a period, what the plan includes, a description, one button and
a "recommended" mark. Gathered in flat groups ("For individuals", "For business") and, when the
site has services, linked to the services they are for. Shown on any page as a block (a slider or
a grid) and in the site's own templates through `tariffs()`.

The module has no public route and no page of its own. A tariff reaches the site **in a block**:
"pricing" as a slider on the Pricing page, "what it costs" as a grid on a service's page. The page
brings the address, the SEO and the menu entry; the module brings the cards.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-blocks`, `webx-ui/localization`, `webx-ui/routing`
- `webx-ui/module-pages` for a page to put the block on — suggested, not required
- `webx-ui/module-services` to link tariffs to services — suggested, not required

## Install

```bash
composer require webx-ui/module-tariffs
php artisan migrate
php artisan webx:blocks:offered --install --module=tariffs
```

The last line puts the offered block type **Tariffs** on the site and publishes it. A type the
site already has under the slug `tariffs` is left alone: the site may have rewritten it.
`webx:setup` runs this line by itself for a new site.

Permissions: `tariffs.view`, `tariffs.manage`, and `tariffs.groups.manage` for the groups — the
tabs of a page of prices are a different job from the price on a card. The section is a group of
the panel's menu, **Tariffs**, with two entries: **Tariffs** and **Groups**.

## A tariff

| Field            | Stored as                                                                      |
| ---------------- | ------------------------------------------------------------------------------ |
| `name`           | translatable; required in the default language — the one required field        |
| `badge`          | translatable: the short line over the name                                     |
| `price`          | a decimal with two places, or nothing — then `price_text` is printed           |
| `currency`       | a key of `webx-tariffs.currencies`; a new tariff starts in the first one       |
| `period`         | translatable free text: "/mo", "a year"                                        |
| `price_text`     | translatable: the words printed when there is no number                        |
| `features`       | rows `{ "text": { "en": … } }` in their order — "what is included"             |
| `description`    | translatable plain text, printed with its line breaks                          |
| `button_label`   | translatable                                                                   |
| `button_link`    | the value of a `wx-link` field: a page of the site, or an address              |
| `button_variant` | a key of `webx-tariffs.variants`                                               |
| `featured`       | the "recommended" mark                                                         |
| `published`      | a new tariff is not — the first save of a half-written card is not on the site |
| groups, services | groups through the shared category code; services through `webx_relations`     |

No draft and no history: a save is what the site shows, at once. Deleting puts a tariff in the
bin, and it comes back to its places.

**Nobody is hidden over a language.** A published tariff is shown in every language. The name, the
badge, the period and the words instead of a price fall back to the default language — a price
without "/mo" on a Russian page reads as a one-off payment, which is worse than an English word.
The description is printed only in the language it is written in. A row of "what is included" not
written in the language drops out of the list there. A button without a label in the language
drops out, the tariff stays.

**Zero is a price.** `0` prints as "$0"; "Free" is the words instead of a price with no number.

## Configuration

```php
// config/webx-tariffs.php
'currencies' => ['USD' => '$', 'EUR' => '€', 'UAH' => '₴', 'PLN' => 'zł'],
'variants' => ['primary' => 'Primary', 'secondary' => 'Secondary', 'link' => 'Link'],
```

- **Currencies** — ISO code → the symbol the site prints. A key that is not three capital letters
  is skipped. The select of the form offers them as "USD — $".
- **Button looks** — key → label for the panel (a string or a translation key). The key is what
  the template turns into a class, `b-tariffs__button--secondary`.
- Taking a currency or a look out of the list locks nothing: a tariff that has it keeps it and
  saves it back; a tariff that does not cannot choose it (422). On the site a lost currency prints
  as its code, a lost look as the first one of the list.

## Two roads into a template

**The block.** The offered type has a `wx-collection` field on the `tariffs` source. The editor of
the page chooses groups, a limit and, when the site has services, "only related to" — some
services, or **the service of this page**. The template gets the cards already read:

```blade
@foreach ($tariffs['items'] as $tariff)
    <article id="{{ $tariff['anchor'] }}">
        <h3>{{ $tariff['name'] }}</h3>
        @if ($tariff['price'] !== null)
            <p>{{ $tariff['symbol'] }}{{ $tariff['amount'] }} {{ $tariff['period'] }}</p>
        @else
            <p>{{ $tariff['price_text'] }}</p>
        @endif
    </article>
@endforeach
```

**`tariffs()`.** A template of the site, or a block that wants something the field does not do,
asks for them itself:

```blade
@foreach (tariffs()->categories() as $group)
    <h2>{{ $group['title'] }}</h2>
    @foreach ($group['tariffs'] as $tariff) … @endforeach
@endforeach
```

| Step                  | What it does                                                             |
| --------------------- | ------------------------------------------------------------------------ |
| `in($groups)`         | only from these groups — an id, a model or a list; one group — its order |
| `relatedTo($t, $ids)` | only those related to these records (`'service'`, an id or a model)      |
| `only([7, 3])`        | these and no others, in this order                                       |
| `except($tariff)`     | all but these                                                            |
| `take(3)`             | at most three; null or zero — all                                        |
| `locale('uk')`        | the language of the cards; by default the one the page is drawn in       |
| `categories()`        | the catalogue by group: each group with its tariffs, empty ones left out |
| `get()`, `first()`    | a list of cards, or one; the query itself can be looped and counted      |

A group has no slug: a string that is not a number is a filter nothing passes, not "all".
`relatedTo()` with an empty list is none.

Both roads hand over the same card:

```php
[
    'id' => 7,
    'anchor' => 'tariff-7',
    'categories' => [2],               // the ids of its groups
    'name' => 'Combo Starter',
    'badge' => '30 HOURS / 25$',       // '' when there is none
    'price' => 750.0,                  // or null
    'amount' => '750',                 // the number as the page's language writes it; '' without one
    'currency' => 'USD',               // or null
    'symbol' => '$',                   // the code for a currency the config lost; '' without one
    'period' => '/mo',
    'price_text' => '',                // printed when price is null
    'features' => ['Design', 'SEO'],   // the rows written in the language
    'description' => '…',              // '' when it is not written in the language
    'button' => ['label' => 'Get started', 'url' => '/contacts', 'new_tab' => false, 'rel' => null, 'variant' => 'primary'], // or null
    'featured' => true,
    'service_links' => [['id' => 3, 'title' => 'SEO', 'url' => '/seo']], // [] without services
    'fields' => ['note' => '…'],       // the project's own fields, by name
]
```

`amount` is formatted by the package — no fraction when it is zero, two digits otherwise, the
separators of the language — because the symbol's place is the template's: "$750" or "750 $".
A list of any length is the same few queries. `tariffs()` is declared only if the site has no
function of that name; `php artisan webx:doctor` says whose it is.

## The Tariffs block

The type is a document in `resources/blocks/tariffs.json`, the same format as
`webx:blocks:export`: a heading, the `tariffs` collection, a `layout`, `columns`, the words over
the list ("This plan includes:") and the label of a recommended card.

- **Slider** — the untouched layout: a ribbon that snaps, one card on a phone, `columns` across
  on a wide screen (3 when not set), with arrows and a count "01 / 03". Without JavaScript it
  scrolls sideways by finger and shows no count; with every card in view it shows neither.
  No autoplay: prices are compared, not watched.
- **Grid** — `columns` across, a stack on a narrow screen; the cards of a row are one height and
  the button is pressed to the bottom.
- A card is an `<article id="tariff-7">`: the badge, the name, the price (symbol, amount and
  period, or the words — neither, no line), the words over the list and the list with ticks drawn
  by CSS (two columns past four lines), the description, links to the services, and the button
  with the class of its look. A recommended card gets `is-featured` and its label over it.
- The symbol stands before the number. For "750$", swap the two in the site's copy of the type.
- The styles are neutral — `currentColor`, `Canvas` and `em` — so the block stands in any design.

**No markup.** An `Offer` needs something it offers and a page of it; a tariff has neither, and on
a service's page the `Service` markup is the services module's to print.

## The panel's API

Under the panel's API path, behind `tariffs.view` to read and `tariffs.manage` to write:

```
GET    tariffs              ?category=&trashed=1&search=  → { data: [row], filters: { categories } }
POST   tariffs              { values }                    → 201 { data: { tariff, values } }
GET    tariffs/{id}                                       → { data: { tariff, values } }
PUT    tariffs/{id}         { values }                    → 422 under the name of the field
DELETE tariffs/{id}
POST   tariffs/{id}/restore                               → { data: row }
POST   tariffs/reorder      { ids, category? }
       tariffs/categories/* the shared routes of categories, behind tariffs.groups.manage to write
```

A row is `{ id, name, badge, price, currency, symbol, period, price_text, featured, published,
position, categories: [{ id, title }], updated_at, deleted_at }`; `tariff` is `{ id, name,
published, deleted_at }`. Refusals land under `name.<default language>`, `price`, `currency`,
`button_variant`, `button_link` (a label without a link) and `features.<n>.text` — the row as the
editor counts them, empty ones included. Empty rows of the list are dropped.

## License

MIT
