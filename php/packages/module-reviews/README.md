# webx-ui/module-reviews

Reviews as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a photo, a
name, a job title, a rating and a text, in flat categories with two orders — shown on any page as
a block (one, a grid, a slider or a marquee) and in the site's own templates through `reviews()`.

The module has no public route and no page of its own. A review reaches the site **in a block**:
"the clinic's reviews" as a grid on the home page, "reviews about implants" as a slider on a
service page. The page brings the address, the SEO and the menu entry; the module brings the
reviews.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-blocks`, `webx-ui/module-media`, `webx-ui/localization`
- `webx-ui/module-pages` for a page to put the block on — suggested, not required

## Install

```bash
composer require webx-ui/module-reviews
php artisan migrate
php artisan webx:blocks:offered --install --module=reviews
```

The last line puts the offered block type **Reviews** on the site and publishes it. A type the
site already has under the slug `reviews` is left alone: the site may have rewritten it.
`webx:setup` runs this line by itself for a new site.

Permissions: `reviews.view`, `reviews.manage`, `reviews.categories.manage`.

## A review

| Field         | Stored as                                                                  |
| ------------- | -------------------------------------------------------------------------- |
| `photo`       | the value of a `wx-media` field — a library key, never an address          |
| `name`        | translatable                                                               |
| `job_title`   | translatable — or the company, or the city: what is printed under the name |
| `text`        | translatable plain text; printed with its line breaks                      |
| `rating`      | a whole number from 1 to 5, or none                                        |
| `reviewed_on` | a date — printed if the template wants it, and it decides nothing else     |
| `profile_url` | `http://` or `https://` only; the offered block links the name to it       |
| `published`   | the whole of a review's life: no draft, no history                         |

**Languages.** A review is shown in a language only when it has a text in it — no other language
stands in. A name or a job title that is not written in the language is taken from the default
one: a person's name is mostly the same everywhere, and hiding a review until somebody types it
again would be hiding it over a formality.

## Two roads into a template

**The block.** The offered type has a `wx-collection` field on the `reviews` source. The editor of
the page chooses the categories (none means all), a limit and whether to draw the filter; the
template gets the reviews already read:

```blade
@foreach ($reviews['items'] as $review)
    <figure>
        <blockquote>{!! nl2br(e($review['text'])) !!}</blockquote>
        <figcaption>{{ $review['name'] }}</figcaption>
    </figure>
@endforeach
```

**`reviews()`.** A template of the site, or a block that wants something the field does not do,
asks for them itself:

```blade
@foreach (reviews()->in($categories)->take($limit ?: 6) as $review) … @endforeach
```

| Step                 | What it does                                                        |
| -------------------- | ------------------------------------------------------------------- |
| `in($categories)`    | an id, a category or a list of them; one category — its own order   |
| `in(null)`, `in([])` | no filter: that is what an untouched field sends, and it means all  |
| `only([12, 7])`      | these and no others, in this order                                  |
| `except($review)`    | all but these                                                       |
| `take(6)`            | at most six; null or zero — all                                     |
| `locale('uk')`       | the language of the cards; by default the one the page is drawn in  |
| `categories()`       | the visible categories, each with its `reviews`; empty ones drop    |
| `get()`, `first()`   | a list of cards, or one; the query itself can be looped and counted |

Review categories have no slugs, so a string is read only when it is an id; any other string is a
filter nothing passes — a typo must not turn "the reviews about implants" into every review.
The limit counts what is shown: "the first six" in Russian are six Russian reviews.

Both roads hand over the same card:

```php
[
    'id' => 12,
    'anchor' => 'review-12',
    'categories' => [3, 5],
    'name' => 'Anna Petrova',
    'initials' => 'AP',          // what stands in for a missing photo
    'job_title' => 'CEO, Acme',  // '' when there is none
    'text' => "…\n…",
    'rating' => 5,               // or null
    'date' => '2026-09-20',      // or null
    'profile' => 'https://…',    // or null
    'photo' => ['url' => …, 'thumb' => …, 'width' => …, 'height' => …, 'alt' => …], // or null
    'fields' => ['city' => 'Kyiv'], // the project's own fields, by name
]
```

A list of any length is the same few queries. `reviews()` is declared only if the site has no
function of that name; `php artisan webx:doctor` says whose it is.

**Choosing categories for `reviews()` in a block.** A block on `wx-collection` never needs to know
what was chosen — its items are already filtered. A block that calls `reviews()` itself adds a
categories field of its own to its schema and passes it on:

```json
{
  "id": "categories",
  "type": "wx-categories",
  "props": { "source": "reviews/categories", "main": false }
}
```

`$categories` is then a list of ids — `[]` when nothing is chosen, which `in()` reads as all.

## The Reviews block

The type is a document in `resources/blocks/reviews.json`, the same format as `webx:blocks:export`:
a heading, the `reviews` collection, a `layout`, and the settings that layout reads — `columns` for
a grid and a slider, `autoplay` for a slider, `speed` for a marquee — and the label of the "All"
button of the filter.

- **One** — the first review, large. **Grid** — up to `columns` across (3 when not set), fewer
  when the block is narrow. **Slider** — a ribbon that snaps, with arrows and, if asked,
  autoplay. **Marquee** — a ribbon that runs, pausing under the pointer.
- **Without JavaScript everything reads:** the slider and the marquee are ribbons that scroll
  sideways. The script adds the arrows, autoplay, the marquee's run and the filter;
  `prefers-reduced-motion` turns autoplay and the run off.
- A card is a `<figure>`: the photo or the initials, the stars, the text in a `<blockquote>`, and
  the name — linked to the profile with `rel="nofollow noopener"` — job title and date.
- The styles are neutral — `currentColor` and `em` — so the block stands in any site's design. A
  new layout is a new option and a new branch in the template: the site rewrites the type in the
  panel like any other.

**No markup.** Stars in search results come only from `AggregateRating`, and Google has not shown
them for what an organisation says about itself on its own site since 2019. Markup with nothing to
gain is only a risk, so the module prints none.

## Two orders

`position` on the review is the order of the whole list. `item_position` on the link is its place
inside one category. The panel drags whichever list the editor is looking at; a new review goes to
the end of the list.

## Fields of the project

A site adds its own fields with a patch on `reviews.form` or `reviews.category-form`, not with a
migration. Each screen has a card with the id `project-fields`:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": { "id": "city", "type": "wx-input", "name": "city", "label": "City" }
  }
]
```

The value lives in the `extra` column: `$review->extra('city')`, and `fields.city` on the card.

## Panel API

Under `webx-admin.api_path` (`api/cms` by default):

| Method   | Path                   |                                                  |
| -------- | ---------------------- | ------------------------------------------------ |
| `GET`    | `reviews`              | `?category=`, `?trashed=1`, `?search=`; no pages |
| `POST`   | `reviews`              | `{ values }` → 201 `{ review, values }`          |
| `GET`    | `reviews/{id}`         | `{ review, values }`                             |
| `PUT`    | `reviews/{id}`         | `{ values }` — 422 under the field name          |
| `DELETE` | `reviews/{id}`         | into the bin                                     |
| `POST`   | `reviews/{id}/restore` | the row of the list                              |
| `POST`   | `reviews/reorder`      | `{ ids, category? }`                             |
|          | `reviews/categories/*` | the shared category routes, no addresses         |

## For an agent: MCP

With `webx-ui/mcp` on, both sections are tools too:

| Tool                     | What it does                                                           |
| ------------------------ | ---------------------------------------------------------------------- |
| `reviews_list`           | every review, or a category in its order, or words — or the bin        |
| `reviews_get`            | one review in full: every language, the photo, the project's fields    |
| `reviews_create`         | a review at the end of the list; unpublished unless asked              |
| `reviews_update`         | the values — on the site at once, a review has no draft                |
| `reviews_delete`         | into the bin                                                           |
| `reviews_reorder`        | the whole list's order, or one category's with `category`              |
| `review_categories_list` | the categories in their order, with how many reviews each holds        |
| `review_categories_*`    | `create`, `update`, `delete`, `reorder` — behind the categories' right |

A review is named by its id, a category by its id or its title in any language. A plain string in
`name`, `job_title` or `text` is the default language; `{ "en": "…", "ru": "…" }` is every language
at once. The photo is a library key (`media/ab/cd/anna.jpg`, as `media_list_files` gives it), and a
key the library does not have is refused rather than left to draw the initials. Everything goes
through the panel's form: the stars, the profile link and the project's fields are refused where
the panel would refuse them, and `reviews_create` is one transaction — a refusal leaves no review.
Every tool that changes something takes `dry_run: true`.

Before writing, an agent reads **`reviews://catalog`**: every category in its order, hidden ones
too, with its reviews in that category's order, and the reviews in no category at the end — each
with `visible_in` (where a reader sees it) and `written_in` (where its text is).

## Demo content

`php artisan webx:demo` seeds two categories and eight reviews in English and Russian, as far as the
site has them: one review in both categories, one unpublished, one with its text in English only,
one with a name in English only, one without stars, one with a link to a profile, and no photos —
the initials stand in. The offered block type is installed if the site has not taken it yet; then,
with `webx-ui/module-pages`, a page `/reviews` with every review in a grid and the filter, and with
`webx-ui/module-services`, a slider of one category at the end of a demo service. Reviews that
already exist leave the demo alone; `--remove` takes it back out.

## Translations

Ten languages ship: `en`, `ru` and `uk` are read by a native speaker; `de`, `pl`, `fr`, `es`, `it`,
`pt` and `tr` are machine translations. Corrections from a native speaker are welcome.

## License

MIT
