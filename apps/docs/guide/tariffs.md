# Tariffs

`@webx-ui/module-tariffs` is the tariffs as a section of the panel, and `webx-ui/module-tariffs` on
the server is what it edits. This page is both, because neither is useful alone.

A tariff is a price card: a name, a badge over it («30 HOURS / 25$»), a price in a currency with a
period — or words instead of a price («On request») — the lines of what it includes, a paragraph of
description, one button and a mark that makes it the recommended one. Tariffs are gathered into
**groups** («For individuals», «For business») and, when the site has
[services](/guide/services), linked to the services they are for.

A tariff has **no page of its own**, and the module has **no public route**: the tariffs reach the
site inside a block — a slider of cards on the page «Pricing», a grid «what it costs» on a
service's page — or through `tariffs()` in a template of the site. The block is a
[collection](/guide/collections), as the [team](/guide/team) and the [reviews](/guide/reviews) are.

## Install

```bash
pnpm add @webx-ui/module-tariffs
composer require webx-ui/module-tariffs
php artisan migrate
php artisan webx:blocks:offered --install --module=tariffs
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { tariffs } from '@webx-ui/module-tariffs'
import '@webx-ui/module-tariffs/style.css'

createAdmin({
  modules: [...tariffs()],
})
```

`tariffs()` is **two** modules — the spread matters: **Tariffs** and **Groups**, in a menu group of
their own, **Tariffs**.

The last command installs the block type the module **offers** — **Tariffs**, slug `tariffs` — and
publishes it. A type the site already has under that slug is never touched: once installed, the
type is the site's to rewrite. `webx:setup` runs the command for a new site.
[Offered block types](/guide/collections#the-block-types-a-module-offers) explains the mechanism.

Permissions: `tariffs.view` opens the list, `tariffs.manage` writes tariffs and their order,
`tariffs.groups.manage` writes the groups — the tabs of a page of prices are a different job from
the price on a card.

## A tariff

| Field            | What it is                                                                 |
| ---------------- | -------------------------------------------------------------------------- |
| `name`           | translatable; required in the default language — the one required field    |
| `badge`          | translatable: the short line over the name                                 |
| `price`          | a number, up to two digits after the point, below 10⁸; empty — no price    |
| `currency`       | a code from the site's list (below); a new tariff starts in the first one  |
| `period`         | translatable free text printed after the price: «/mo», «a year»            |
| `price_text`     | translatable: the words printed when there is no number                    |
| `features`       | the lines of «what is included», each translatable, in the editor's order  |
| `description`    | translatable plain text; the block prints it with its line breaks          |
| `button_label`   | translatable                                                               |
| `button_link`    | a `wx-link` value — a page of the site or an address                       |
| `button_variant` | the look of the button, from the site's list (below)                       |
| `featured`       | «Recommended»: the card stands out, with the label the block gives it      |
| `categories`     | the groups it is in — several, or none                                     |
| `services`       | the services it is for — there only when `module-services` is installed    |
| `published`      | the whole of a tariff's life: no draft, no history; the bin brings it back |

**Zero is a number.** `price = 0` prints «$0»; «Free» is the words instead of a price, with the
number left empty.

**Nobody is hidden over a language.** A published tariff is in every language of the site. The
name, the badge, the period and the words instead of a price, not written in a language, are taken
from the default one: a price without its «/mo» on a Russian page reads as a one-off payment, which
is worse than an English word. The description is printed only where it is written. A line of
«what is included» not written in the language drops out — the list is shorter, not in another
language. A button without a label in the language drops out, and the tariff stays.

A button whose link leads to a page in draft or in the bin drops out too: no button is better than
a button to a 404. A label without a link is refused by the form; a link without a label passes,
and prints nothing.

## Groups

Groups are the panel's shared flat categories — the screens, the order, the bin — with this
module's words: in the code they are `categories` (`tariff_categories`, `?category=`), in the panel
and to an agent they are **groups**. A group has no address, no SEO card and no page. A tariff can
be in several groups; the order **inside** a group is its own, dragged with the list narrowed to it,
and the whole list keeps the order of its own.

A hidden group drops out of every page of prices; its tariffs stay in the blocks that show them by
other means.

## The page of prices is a page with a block

The site's page of prices is an **ordinary page** of [`module-pages`](/guide/pages):

1. **Pages** → a new page «Pricing» with the slug `pricing`;
2. **Content** → add the block **Tariffs**; leave **Tariffs** as it is (that is «everything»), or
   narrow it to a group; pick **Slider** or **Grid**;
3. publish.

The address, the SEO card, the menu entry and the sitemap line come from the page.

## «What it costs» on a service's page

A tariff is linked to services in its own form — the **Services** field. Then a service's page
answers «what it costs» by itself:

1. open the service → **Content** → add the block **Tariffs**;
2. in **Tariffs**, **Only related to** → **Services** → **The record of the page it stands on**;
3. pick **Grid**.

The block remembers «the service of this page», not a service, so the same block copied onto
another service shows that service's tariffs. On a page that is not a service it shows none.

Without `module-services` none of this exists: no field in the form, no choice in the block, no
links on the card. Links written while the module was there are kept, and come back with it.

## Two roads into a template

Both give **the same card**, so a block can move from one to the other without its markup changing.

**The `tariffs` collection** — what the offered block uses. A `wx-collection` field with
`"source": "tariffs"` lets the editor choose groups, a limit and «only related to»; the template
gets the tariffs already read:

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

**`tariffs()`** — for a template of the site, or a block that wants what the field does not do. It
never shows what a reader may not see: unpublished, or in the bin.

```blade
@foreach (tariffs()->in($group)->take(3) as $tariff) … @endforeach
```

| Step                  | What it does                                                             |
| --------------------- | ------------------------------------------------------------------------ |
| `in($groups)`         | Only from these groups — an id, a model or a list; one group — its order |
| `relatedTo($t, $ids)` | Only the tariffs related to these records: `'service'`, an id, a model   |
| `only([7, 3])`        | These and no others, in this order                                       |
| `except($tariff)`     | All but these                                                            |
| `take(3)`             | At most three; null or zero — all                                        |
| `locale('uk')`        | The language of the cards; by default the one being rendered             |
| `categories()`        | A catalogue by group: each group with its tariffs in its order (below)   |
| `get()`, `first()`    | A list of cards, or one; the query itself can be looped over and counted |

A group is named by its id or its model: it has no slug. A string that is not a number is a filter
nobody passes, not «everything». `relatedTo('service', [])` is none — «the tariffs of no service»
is not every tariff. The helper is a
[`RecordQuery`](/guide/collections#a-helper-for-templates-recordquery), like `services()` and
`team()`, and is declared only if the site has no `tariffs()` of its own; `php artisan webx:doctor`
says whose it is.

The card:

```php
[
    'id' => 7,
    'anchor' => 'tariff-7',            // for a link to #tariff-7
    'categories' => [2],               // the ids of its groups
    'name' => 'Combo Starter',         // in the language of the page, else the default one
    'badge' => '30 HOURS / 25$',       // the same; '' when there is none
    'price' => 750.0,                  // the number, or null
    'amount' => '750',                 // the number as the page's language writes it; '' without one
    'currency' => 'USD',               // the code, or null
    'symbol' => '$',                   // from the config; the code itself for one taken out of it
    'period' => '/mo',                 // as the name; '' when there is none
    'price_text' => '',                // as the name; printed when price is null
    'features' => ['Design', 'SEO'],   // the lines in the page's language; untranslated ones drop
    'description' => '…',              // in the language of the page only, else ''
    'button' => [                      // null without a label in the language or a live link
        'label' => 'Get started',
        'url' => '/contacts',
        'new_tab' => false,
        'rel' => null,                 // with a new tab — "noopener noreferrer"
        'variant' => 'primary',        // a look taken out of the config — the first one instead
    ],
    'featured' => true,
    'service_links' => [['id' => 3, 'title' => 'SEO', 'url' => '/seo']], // [] without services
    'fields' => ['hours' => '30'],     // the project's own fields, by name
]
```

`amount` is formatted by the package — «750», «12.50», «1,380» or «1 380» by the language —
because the block's template is written in the subset of Blade the playground's preview also reads,
and `number_format` is not in it.

### Groups as tabs

A page «For individuals / For business» with a tab per group asks `categories()` for the catalogue:
the visible groups in their order, each with its published tariffs in the group's own order. A
group with nothing left to show is left out — a tab over nothing is not a group. `in()` narrows the
groups, `only()` and `except()` the tariffs inside, `take()` counts per group.

```blade
@php($groups = tariffs()->categories())

<div role="tablist">
    @foreach ($groups as $group)
        <button role="tab" aria-controls="group-{{ $group['id'] }}">{{ $group['title'] }}</button>
    @endforeach
</div>

@foreach ($groups as $group)
    <section id="group-{{ $group['id'] }}" role="tabpanel">
        @foreach ($group['tariffs'] as $tariff) … @endforeach
    </section>
@endforeach
```

The tabs themselves — which one is open, the keyboard — are the site's script; the package gives
the data in the order the editor dragged it.

## The Tariffs block

The offered type is a heading, the `tariffs` collection, a **Layout**, **Columns**, **Above the
list** (a line such as «This plan includes:») and **Featured label** (the word over the
recommended card):

| Layout     | What it draws                                                                          |
| ---------- | -------------------------------------------------------------------------------------- |
| **Slider** | A ribbon that snaps: one card on a phone, `columns` across wider; arrows and «01 / 03» |
| **Grid**   | `columns` across, stacked when narrow; the cards of one height, the button at the foot |

A setting the editor never touched is `null` in the template, so the template keeps the defaults:
slider, three columns, no line above the list, the recommended card marked without a word.

A card is an `<article id="tariff-7">`: the badge, the name, the price (the symbol and the amount
with the period, or the words), the line above the list and the list — two columns when it is
longer than four lines — the description, the button with the class of its look
(`b-tariffs__button--primary`), and the links to its services. The recommended card has the class
`is-featured` and the label over it. **Without JavaScript everything reads** — the slider is a
ribbon that scrolls sideways; the script adds the arrows and the count, and hides both when one
card, or all of them, fit. There is no autoplay: prices are compared, not watched. The styles are
neutral, on `currentColor` and `em`: it is the site's design, not the panel's.

### The symbol after the number

The offered template puts the symbol **before** the number: «$750». A site that writes «750$» or
«750 ₴» changes one line in **its** copy of the type — **Blocks** → **Tariffs** → **Template**:

```blade
<span class="b-tariffs__amount">{{ $tariff['amount'] }}&nbsp;{{ $tariff['symbol'] }}</span>
```

The symbol is never part of `amount` for this reason: where it stands, and with or without a space,
is the site's layout, not the package's.

## Adding a currency or a button look

The currencies are the config `webx-tariffs.currencies`, ISO code → the symbol the site prints;
the button looks are `webx-tariffs.variants`, key → the name in the panel. The first currency is
what a new tariff starts in; the first look is what a button falls back to when its own was taken
out.

```php
// config/webx-tariffs.php — php artisan vendor:publish --tag=webx-tariffs-config
return [
    'currencies' => [
        'USD' => '$',
        'EUR' => '€',
        'UAH' => '₴',
        'PLN' => 'zł',
        'CHF' => 'CHF',
    ],

    'variants' => [
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'link' => 'Link',
        'outline' => 'Outline',
    ],
];
```

Publish the whole list: the config is merged one level deep, so a published file with only `CHF`
in it would be the only currency the site has. A currency code is three capital letters; any other
key is skipped. The select of the form shows «USD — $», built from the config and not translated.
A look's name may be a translation key (`trans::…`), as for the buttons of [banners](/guide/banners).

A new look needs its style, in the site's copy of the type — **Styles**, beside the others:

```css
.b-tariffs__button--outline {
  border: 1px solid currentColor;
  background: transparent;
}
```

A currency or a look taken **out** of the list does not lock the tariffs that have it: such a
tariff is saved as it was, and prints the code instead of the symbol, or the first look instead of
its own. Choosing it anew is refused.

## Fields of the project

A discount, the old price crossed out, the hours in the package — none is a column of the package.
The site lays a patch over the screen `tariffs.form`, whose empty card has the public id
`project-fields`, and whatever the screen draws that the model has no column for is kept in
`extra`. `resources/screens/tariffs.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "old-price",
      "type": "wx-input-number",
      "name": "old_price",
      "label": "Old price"
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('tariffs.form', resource_path('screens/tariffs.form.json'));
}
```

The field appears in the editor, is checked by its type on every save — the panel's and an
agent's — and reaches the card as `$tariff['fields']['old_price']`. Printing it is a line in the
block's template. A group's screen is `tariffs.category-form`, patched the same way.

## Why there is no page, no markup and no matrix

All three were weighed and left out on purpose; each can come later without breaking anything here.

- **No page of a tariff.** A tariff is read beside the others, not alone: its page would be one card
  on an empty screen. Nor is there a page of the module — the page of prices is a page of
  `module-pages` with a block, and gets its address, SEO and menu entry from there.
- **No schema.org markup.** A table of prices has no rich result in search. An `Offer` needs an
  `itemOffered` and the page of what is sold, and a tariff has none; on a service's page the
  `Service` markup is `module-services`', and a block adding its own `Offer` beside it would repeat
  or contradict it. The right place is `offers` inside the service's markup, from the tariffs
  linked to it — a contract between two modules, for later.
- **No comparison matrix.** «Feature × tariff» with ticks needs lines shared by the whole group and
  a mark at each tariff — a third layout of the block and another shape of data. The lines of
  «what is included» are stored as `{ text }` rows so that the mark «included / not included» fits
  into the same row the day it comes, without touching what is written. So is a pair of prices
  «monthly / yearly» with a switch: today the period is free text, and a yearly price is a second
  tariff.

## The panel

**Tariffs** is a list and an editor side by side (`WxListDetail`), without pages — the list is where
tariffs are put in order, and a drag cannot cross a page boundary. A row is the name, the price in
one line («$750 /mo» or «On request»), a star at the recommended one and a mark when it is not
published. The list narrows to a group, to words, or to the bin; narrowed to a group, the drag is
that group's order. The open tariff is in the address (`?tariff=7`). **New tariff** is a row that
opens an empty form; the tariff is created by its first save. On a phone the editor slides over the
list and draws its own «Back».

The editor is the screen `tariffs.form`: the tariff, the price, what is included, the description,
the button, the settings (groups, services, **Published**) and the project's card. Save with the
button or `Ctrl+S`; leaving with unsaved changes asks first.

**Groups** are the shared category screens: a title, **Shown on the site** and the project's card.

```
GET    /api/cms/tariffs               category, trashed, search — no pages
POST   /api/cms/tariffs               { values } — created from the form
GET    /api/cms/tariffs/{id}          { tariff, values }
PUT    /api/cms/tariffs/{id}          { values } — 422 under the field's name
DELETE /api/cms/tariffs/{id}          to the bin · POST /restore
POST   /api/cms/tariffs/reorder       { ids, category? }
       /api/cms/tariffs/categories/*  the shared category routes
```

A missing name answers under `name.<default language>`; a bad price under `price`; a currency or a
look the site does not have under `currency` or `button_variant`; a label without a link under
`button_link`; a line too long under `features.<n>.text`, `n` counting the rows as the editor sees
them.

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the section is tools too:

| Tool              | What it does                                                                |
| ----------------- | --------------------------------------------------------------------------- |
| `tariffs_list`    | The tariffs in the order of the site, of a group, or words — or the bin     |
| `tariffs_get`     | One tariff in full: every language, the lines, the button, groups, services |
| `tariffs_create`  | A tariff at the end of the list; unpublished unless asked                   |
| `tariffs_update`  | The values — on the site at once, tariffs have no draft                     |
| `tariffs_delete`  | To the bin                                                                  |
| `tariffs_reorder` | The whole order, or the order inside one group                              |

The groups have the tools every module's categories have: `tariff_groups_list`, `_create`,
`_update`, `_delete`, `_reorder`.

A tariff is named by its id; a group by its id or its title in any language. A plain string in a
translated field is the default language; `{ "en": "…", "ru": "…" }` is every language at once.
`price` is a number or `null`; `currency` is a code, and one the site does not have is refused with
the list of those it has. `features` is a list of lines — each a string or a map of languages; an
agent never needs to know about `{ text }`. `button` is `{ label, link, variant }` — `link` an
address or an entity as `menu_add_link` takes it, `variant` a key of the site's looks — and `null`
takes the button away. `services` takes ids or addresses (`"/services/seo"`), and exists only on a
site with services. The currency and the look are checked **before** the form, so the refusal
names the keys an agent can send back. Every tool that changes something takes `dry_run: true`;
the values go through the same form as the panel's, and `tariffs_create` is one transaction: a
refusal leaves nothing behind.

Before writing, an agent reads **`tariffs://catalog`**: the currencies and the looks the site
accepts, then every group in order with its tariffs in the group's order — unpublished ones included
and marked — each with the price in one line, `featured`, `written_in` (the languages of the name
and of the description) and the services it is linked to; the tariffs in no group at the end.

Putting a Tariffs block on a page is not a tariff tool: it is `blocks_edit_content` on the page,
with a `tariffs` block whose `tariffs` value is `{ "categories": [2], "limit": 3 }` (or
`{ "related": { "type": "service", "ids": [], "current": true } }`) and whose `layout` is `slider`
or `grid`.

## Demo content

`php artisan webx:demo` seeds one group, «For business», and three tariffs in it, in the two
languages of the demo as far as the site has them — the sample this module was drawn from:

- **Combo Starter** — «30 HOURS / 25$», $750 /mo, six lines of which one is written only in English,
  so the Russian list is a line shorter;
- **Combo Growth** — recommended, $1,380 /mo, linked to a demo service;
- **Combo Enterprise** — no number, «On request» instead.

The buttons lead to a page of the pages demo, else to the first page the address registry has; a
site without pages gets tariffs without buttons. The offered block type is installed if the site has
not taken it yet, and then:

- with `module-pages` — a page `/pricing` with every tariff in a slider of three columns, «This plan
  includes:» over the lists and «Recommended» over Growth;
- with `module-services` — «Company website» gets a grid of «what it costs», the service of this
  page.

`--remove` takes all of it back out, the block type too when the demo installed it. A site that
already has any tariff leaves the demo alone.
