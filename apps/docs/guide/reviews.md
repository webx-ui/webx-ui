# Reviews

`@webx-ui/module-reviews` is reviews as two sections of the panel, and `webx-ui/module-reviews` on
the server is what they edit. This page is both, because neither is useful alone.

A review is a photo, a name, a job title, the stars and a text. It has **no page of its own**, and
the module has **no public route**: reviews reach the site inside a block — «the clinic's reviews»
as a grid on the home page, «reviews about implants» as a slider on a service page — or through
`reviews()` in a template of the site. The block is a [collection](/guide/collections), as the
[FAQ](/guide/faq) is, and the categories are the panel's shared [categories](/guide/categories),
without addresses.

## Install

```bash
pnpm add @webx-ui/module-reviews
composer require webx-ui/module-reviews
php artisan migrate
php artisan webx:blocks:offered --install --module=reviews
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { reviews } from '@webx-ui/module-reviews'
import '@webx-ui/module-reviews/style.css'

createAdmin({
  modules: [...reviews()],
})
```

`reviews()` returns two modules — **Reviews** and **Categories** — which arrive under one heading
because the server puts both in the `reviews` group.

The last command installs the block type the module **offers** — **Reviews**, slug `reviews` — and
publishes it. A type the site already has under that slug is never touched: once installed, the
type is the site's to rewrite. `webx:setup` runs the command for a new site; on a site that already
has a panel it is the one line to run by hand.
[Offered block types](/guide/collections#the-block-types-a-module-offers) explains the mechanism.

Permissions: `reviews.view` opens the list, `reviews.manage` writes (the order too), and
`reviews.categories.manage` covers the categories.

## A review

| Field         | What it is                                                                   |
| ------------- | ---------------------------------------------------------------------------- |
| `photo`       | a picture from the library — kept as its key, never as an address            |
| `name`        | translatable                                                                 |
| `job_title`   | translatable — or the company, or the city: what is printed under the name   |
| `text`        | translatable plain text; the block prints it with its line breaks            |
| `rating`      | a whole number from 1 to 5, or none — then the block draws no stars          |
| `reviewed_on` | a date; printed if the template wants it, it orders and hides nothing        |
| `profile_url` | `http://` or `https://` only; the block links the name with `rel="nofollow"` |
| `published`   | the whole of a review's life: no draft, no history; the bin brings it back   |

**A review is shown in a language only when its text is written in it.** No other language stands
in for the text: half a block of reviews in another language is worse than a shorter one. The name
and the job title are different: when they are not written in the language, the default one is
used — a person's name is mostly the same everywhere, and hiding a review until somebody types it
again would be hiding it over a formality. The list in the panel says where each review is seen,
and marks the published one that is seen nowhere.

## The page of reviews is a page with a block

There is no «reviews index» to switch on. The site's page of reviews is an **ordinary page** of
[`module-pages`](/guide/pages) with the Reviews block on it:

1. **Pages** → a new page «Reviews» with the slug `reviews`;
2. **Content** → add the block **Reviews**; leave **Categories** empty (that is «all»), turn the
   filter on, pick **Grid**;
3. publish.

The address, the SEO card, the menu entry and the sitemap line come from the page. The same block
with a category chosen is «reviews about X» anywhere else. One chosen category shows that
category's own order; none or several show the order of the whole list, each review once.

A category meant only for picking — «On the home page» — is a category like any other, hidden:
still pickable in a block, never a button of the filter.

## Two roads into a template

Both give **the same card**, so a block can move from one to the other without its markup changing.

**The `reviews` collection** — what the offered block uses. A `wx-collection` field with
`"source": "reviews"` lets the editor of the page choose the categories (none means all), a limit
and the filter; the template gets the reviews already read and filtered:

```blade
@foreach ($reviews['items'] as $review)
    <figure>
        <blockquote>{!! nl2br(e($review['text'])) !!}</blockquote>
        <figcaption>{{ $review['name'] }}</figcaption>
    </figure>
@endforeach
```

`$reviews['groups']` are the categories for the filter's buttons, `$reviews['filter']` whether the
editor asked for it — as with the FAQ block.

**`reviews()`** — for a template of the site, or a block that wants what the field does not do. It
returns a query that never shows what a reader may not see: unpublished, in the bin, or without a
text in the language of the page.

```blade
@foreach (reviews()->in($categories)->take($limit ?: 6) as $review) … @endforeach
```

| Step                 | What it does                                                             |
| -------------------- | ------------------------------------------------------------------------ |
| `in($categories)`    | An id, a category or a list of them; one category lists in its own order |
| `in(null)`, `in([])` | No filter — what an editor's untouched field sends means «every review»  |
| `only([12, 7])`      | These and no others, in this order                                       |
| `except($review)`    | All but these                                                            |
| `take(6)`            | At most six; null or zero — all                                          |
| `locale('uk')`       | The language of the cards; by default the one being rendered             |
| `categories()`       | The catalogue: visible categories, each with its `reviews`; empty drop   |
| `get()`, `first()`   | A list of cards, or one; the query itself can be looped over and counted |

Review categories have no slugs, so a string is read only when it is an id; any other string is a
filter nothing passes — a typo must not turn «reviews about implants» into every review. The limit
counts what is shown: «the first six» on the Russian page are six Russian reviews, not six reviews
minus the ones without a translation.

The card:

```php
[
    'id' => 12,
    'anchor' => 'review-12',        // for a link to #review-12
    'categories' => [3, 5],         // ids
    'name' => 'Anna Petrova',
    'initials' => 'AP',             // what stands in for a missing photo
    'job_title' => 'CEO, Acme',     // '' when there is none
    'text' => "…\n…",               // plain text, in the language of the page
    'rating' => 5,                  // or null
    'date' => '2026-09-20',         // or null
    'profile' => 'https://…',       // or null
    'photo' => ['url' => …, 'thumb' => …, 'width' => …, 'height' => …, 'alt' => …], // or null
    'fields' => ['city' => 'Kyiv'], // the project's own fields, by name
]
```

Print the initials from the card rather than cutting the name in the template: a byte cut of a
Cyrillic name is half a letter. A list of any length costs the same few queries. The helper is
declared only if the site has no `reviews()` of its own; `php artisan webx:doctor` says whose it is.

### Which categories did the editor pick?

A block on the collection **does not need to know**: its `items` are already the chosen ones. The
question only comes up for a block that calls `reviews()` itself — and then the block asks for the
categories with a field of its own in its schema:

```json
{
  "id": "categories",
  "type": "wx-categories",
  "label": "Categories",
  "props": { "source": "reviews/categories", "main": false }
}
```

`$categories` in the template is a list of ids — `[]` when nothing is chosen — and goes straight
into `reviews()->in($categories)`, which reads `[]` as «all». The same pattern is in
[Services in a block](/guide/services#services-in-a-block).

## The Reviews block

The offered type is a heading, the `reviews` collection, a **Layout**, and the settings that
layout reads — the editor sees only the ones that apply:

| Layout      | What it draws                                                        | Settings                |
| ----------- | -------------------------------------------------------------------- | ----------------------- |
| **One**     | The first review, large                                              | —                       |
| **Grid**    | Up to `columns` across, fewer when the block is narrow; the default  | `columns`               |
| **Slider**  | A ribbon that snaps, with arrows and — if asked — autoplay every 5 s | `columns`, `autoplay`   |
| **Marquee** | A ribbon that runs by itself, pausing under the pointer              | `speed` (px per second) |

A setting the editor never touched is `null` in the template, so the template keeps the defaults:
grid, three columns, 40 px/s, «All» on the filter's first button.

**Without JavaScript everything reads:** the grid is CSS grid, the slider and the marquee are
ribbons that scroll sideways. The script adds the arrows, autoplay, the marquee's run (a copy of the
ribbon, hidden from screen readers) and the filter. `prefers-reduced-motion` turns autoplay and the
run off. A card is a `<figure>`: the photo or the initials, the stars (`aria-label="4 / 5"`), the text
in a `<blockquote>`, and the name — linked to the profile — the job title and the date. The styles
are neutral, on `currentColor` and `em`: it is the site's design, not the panel's.

### Adding a layout of your own

A layout is an option of one field and a branch of the template and the styles — not a new module
or a new block type. The one ribbon of cards is the same in every layout; the layout is only the
attribute `data-reviews-layout` on the block's root, and the styles hang off it. Say the site wants
**List** — reviews one under another, the photo on the left:

1. **Blocks** → **Reviews** → **Fields**: add an option to `layout`,

   ```json
   { "value": "list", "label": "List" }
   ```

   and, if a setting belongs to it, a field with `"visible": { "when": "layout", "is": "list" }`;

2. **Styles**: the look of that layout,

   ```css
   .b-reviews[data-reviews-layout='list'] .b-reviews__track {
     grid-template-columns: 1fr;
   }
   .b-reviews[data-reviews-layout='list'] .b-reviews__card {
     display: grid;
     grid-template-columns: 3.5em 1fr;
     column-gap: 1em;
   }
   .b-reviews[data-reviews-layout='list']
     .b-reviews__card
     > :not(.b-reviews__photo, .b-reviews__initials) {
     grid-column: 2;
   }
   .b-reviews[data-reviews-layout='list'] :is(.b-reviews__photo, .b-reviews__initials) {
     grid-row: span 3;
   }
   ```

3. **Template**, only if the markup itself differs: `@if ($layout === 'list') … @endif` around what
   the other layouts do not have.

The type is the site's from the moment it was installed, so this is an edit in the panel, with the
preview beside it; no deploy, and a module update never writes over it.

## Why there is no markup

The module prints no schema.org `Review` or `AggregateRating`, on purpose. The stars in search
results come only from `AggregateRating`, and since 2019 Google does not show them for reviews an
organisation publishes about itself on its own site («self-serving reviews»). Markup that cannot
win anything can still earn a manual action, so the block has no flag for it — unlike the FAQ, whose
markup does something. If a site ever gets reviews from somewhere else (imported from a review
platform), that is the moment to revisit it.

## Fields of the project

A clinic wants «Treatment» on a review, a shop wants «City». Neither is a column of the package:
the site lays a patch over the screen, and whatever the screen draws that the model has no column
for is kept in `extra`.

Both screens — `reviews.form` for a review, `reviews.category-form` for a category — keep an empty
card with the public id `project-fields`. `resources/screens/reviews.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "city",
      "type": "wx-input",
      "name": "city",
      "label": "City",
      "localized": true
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('reviews.form', resource_path('screens/reviews.form.json'));
}
```

The field appears in the editor, is checked by its type on every save — the panel's and an
agent's — and reaches the card as `$review['fields']['city']`, in the language of the page because
the field is localized. On the model it is `$review->extra('city')`. Printing it is a line in the
block's template:

```blade
@if ($review['fields']['city'] ?? null)
    <span class="b-reviews__city">{{ $review['fields']['city'] }}</span>
@endif
```

## The panel

**Reviews** is a list and an editor side by side (`WxListDetail`), without pages. A row is the photo
(or the initials), the name, the stars and the languages the review is seen in. Filters: a category
and words. Without a category the drag writes the order of the whole list; with one, only that
category's order. While a search narrows the list there are no grips. **New review** is a row that
opens an empty form; the review is created by its first save. On a phone the editor slides over the
list and draws its own «Back».

The editor is the screen `reviews.form`: the photo, the name, the job title, the text, the stars,
the date, the profile link, the categories, **Published** and the project's card. Save with the
button or `Ctrl+S`; leaving with unsaved changes asks first.

**Categories** is the shared category list and page (`reviews.category-form`): the name, whether it
is shown, the project's card. A category with reviews in it refuses to go into the bin and says how
many.

```
GET    /api/cms/reviews                  category, trashed, search — no pages
POST   /api/cms/reviews                  { values } — created from the form
GET    /api/cms/reviews/{id}             { review, values }
PUT    /api/cms/reviews/{id}             { values } — 422 under the field's name
DELETE /api/cms/reviews/{id}             to the bin · POST /restore
POST   /api/cms/reviews/reorder          { ids, category? }

GET    /api/cms/reviews/categories       the shared categories API
```

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), both sections are tools too:

| Tool                     | What it does                                                                |
| ------------------------ | --------------------------------------------------------------------------- |
| `reviews_list`           | Every review, or a category in its order, or words — or the bin             |
| `reviews_get`            | One review in full: every language, the photo, the project's fields         |
| `reviews_create`         | A review at the end of the list; unpublished unless asked                   |
| `reviews_update`         | The values — on the site at once, a review has no draft                     |
| `reviews_delete`         | To the bin                                                                  |
| `reviews_reorder`        | The whole list's order, or one category's with `category`                   |
| `review_categories_list` | The categories in their order, with how many reviews each holds             |
| `review_categories_*`    | `create`, `update`, `delete`, `reorder` — behind the categories' permission |

A review is named by its id — names repeat, two Annas are two reviews — and a category by its id or
its title in any language. A plain string in `name`, `job_title` or `text` is the default language;
`{ "en": "…", "ru": "…" }` is every language at once. The photo is a library key
(`"media/ab/cd/anna.jpg"`), and a key the library does not have is refused rather than left to draw
the initials. Every tool that changes something takes `dry_run: true`. The values go through the
same form as the panel's — the stars, the profile link and the project's fields are refused where
the panel would refuse them — and `reviews_create` is one transaction: a refusal leaves nothing
behind.

Before writing, an agent reads **`reviews://catalog`**: every category in its order, hidden ones
too, with its reviews in that category's order, unpublished ones included and marked, and the
reviews in no category at the end. Each row says `visible_in` — where a reader sees it — and
`written_in` — where its text is — so «published but not translated» is told apart from «on the
site».

Putting a Reviews block on a page is not a reviews tool: it is `blocks_edit_content` on the page,
with a `reviews` block whose `reviews` value is `{ "categories": [3], "limit": 6, "filter": false,
"markup": false }` and whose `layout` is one of `single`, `grid`, `slider`, `marquee`.

## Demo content

`php artisan webx:demo` seeds two categories and eight reviews in the two languages of the demo, as
far as the site has them. One review is in both categories and stands in a different place in each;
one is not published; one has its text in English only, so the second language does not show it;
one has its name in English only and is shown under it everywhere; one has no stars; one links to a
profile. None has a photo — the initials stand in, and the demo is where that is seen. The offered
block type is installed if the site has not taken it yet, and then the block goes where a site
would put it:

- with `module-pages` — a page `/reviews` with every review in a grid and the filter;
- with `module-services` — a slider of the «Websites» reviews, with autoplay, at the end of one demo
  service.

`--remove` takes all of it back out, the block type too when the demo installed it. Reviews that
already exist leave the demo alone.
