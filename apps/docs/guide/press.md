# Press

`@webx-ui/module-press` is «press about us» as a section of the panel, and `webx-ui/module-press`
on the server is what it edits. This page is both, because neither is useful alone.

An **outlet** is a magazine, a paper or a portal that wrote about the site's owner: a logo, a name,
a few words and the outlet's own website. An **article** is what it printed — an interview, a
column, an expert's comment — and it always **leads out**: to the article at its address, or to a
PDF from the library. Every outlet has a page of its own under a prefix (`/press/tatler-asia`)
with its articles on it; the prefix itself is an ordinary page with a block. There are no
categories: what sorts articles is their **kind**.

## Install

```bash
pnpm add @webx-ui/module-press
composer require webx-ui/module-press
php artisan migrate
php artisan webx:blocks:offered --install --module=press
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { press } from '@webx-ui/module-press'
import '@webx-ui/module-press/style.css'

createAdmin({
  modules: [press()],
})
```

`press()` is one section and one entry of the menu — **Press**, without a group of its own. The last
command installs the three block types the module **offers** and publishes them; a type the site
already has under one of those slugs is never touched. `webx:setup` runs it for a new site.
[Offered block types](/guide/collections#the-block-types-a-module-offers) explains the mechanism.

Permissions: `press.view` opens the list, `press.manage` writes — the order too, which is the order
of the strip of logos.

## Outlets and articles

| Outlet        | What it is                                                                  |
| ------------- | --------------------------------------------------------------------------- |
| `logo`        | a picture from the library — kept as its key, never as an address           |
| `title`       | translatable — a proper name, shown from any language that has it           |
| `slug`        | translatable — the address of its page under the prefix                     |
| `summary`     | translatable plain text — under the name, and the description of its page   |
| `website_url` | `http://` or `https://` only                                                |
| `featured`    | in the strip of logos, when the block asks for the marked ones only         |
| `published`   | the whole of an outlet's life: no draft, no history; the bin brings it back |

| Article          | What it is                                                              |
| ---------------- | ----------------------------------------------------------------------- |
| `title`          | translatable — the headline as the outlet printed it                    |
| `excerpt`        | translatable plain text — a sentence or two                             |
| `kind`           | a key of `webx-press.kinds`, or none                                    |
| `published_on`   | a day                                                                   |
| `date_precision` | `day`, `month` or `year` — how much of the date is printed              |
| `url`            | the article on the outlet's site, `http(s)://` only                     |
| `file`           | a PDF from the library                                                  |
| `is_hidden`      | kept with the outlet, shown nowhere — to set one aside without deleting |

An article needs a `url`, a `file`, or both; with both, the title leads to the address and the PDF
is a second link. The articles are edited as cards on the outlet's form and saved with it, in the
order of the cards; in the database they are a table of their own, which is what lets a block list
the latest articles of every outlet by date.

A date is printed as much as it is known, in the language of the page:

| Precision | ru              | en              |
| --------- | --------------- | --------------- |
| `day`     | 12 августа 2023 | August 12, 2023 |
| `month`   | август 2023     | August 2023     |
| `year`    | 2023            | 2023            |

## What a reader sees in a language

Press is the section where languages matter most: a site in three languages rarely has every
interview in all three.

- **An article is seen in a language only when its title is written in it.** No other language
  stands in: an English headline on the Russian page is a link a Russian reader did not ask for. An
  excerpt that is not translated is simply not printed.
- **An outlet is seen in a language when it is published and has at least one article seen there.**
  Its **name** is taken from any language that has it — the default one first: it is a proper
  name, and hiding the outlet until somebody types «Tatler» a second time would be hiding it over a
  formality.
- **An outlet's page without an article in the language is a 404 in that language**, and is not
  in the sitemap or in the hreflang of its page. A language that has articles and no slug of its own
  takes the outlet's slug from another language, so a Russian interview added later gives the
  outlet a Russian page by itself.

The list in the panel says where each outlet is seen, and marks the published one that is seen in no
language — all of its articles hidden, or none titled yet.

## Kinds

What sorts the articles is not a category of the outlet but a **kind** of each article: one magazine
runs both a column by the owner and a quote from them. The kinds are a list in the config:

```php
// config/webx-press.php
'kinds' => ['mention', 'interview', 'expert_comment', 'authored'],
```

The four above are the default. **Adding one of the site's own** is a line there and a word in each
language of the site:

```php
// config/webx-press.php
'kinds' => ['mention', 'interview', 'expert_comment', 'authored', 'podcast'],

// lang/vendor/webx-press/en/kinds.php
return ['podcast' => 'Podcast'];

// lang/vendor/webx-press/ru/kinds.php
return ['podcast' => 'Подкаст'];
```

The form offers it at once, and the blocks print its word as the heading of its group. A key taken
out of the list is «no kind» on every article that had it, and is refused on the next save of one:
the editor picks another.

## The page at the prefix is a page with a block

There is no «press index» to switch on. `/press` is an **ordinary page** of
[`module-pages`](/guide/pages) with a slug equal to the prefix:

1. **Pages** → a new page «Press» with the slug `press`;
2. **Content** → the block **Press logos**, and under it **Press outlets** with **Grouped by kind of
   article** on;
3. publish.

The address, the SEO card, the menu entry and the sitemap line come from the page, and an outlet's
breadcrumbs go through it. Without such a page the trail skips the step — nothing breaks.

## The three blocks

| Block                                 | What it draws                                                       | Settings                             |
| ------------------------------------- | ------------------------------------------------------------------- | ------------------------------------ |
| **Press logos** (`press-logos`)       | A strip of logos, each leading to the outlet's page                 | a heading, marked ones only, a limit |
| **Press outlets** (`press-outlets`)   | The outlets as cards: logo, name, a few words, how many articles    | a heading, grouped by kind, kinds    |
| **Press articles** (`press-articles`) | The latest articles of every outlet by date, with the outlet's logo | a heading, kinds, a limit (six)      |

**Grouped by kind** makes a section per kind with the outlets that ran an article of it — an outlet
that ran two kinds is in both — headed by the kind's word. **Kinds** narrows: in the catalogue it is
which groups and in what order; in the feed, which articles. Empty is every kind. «Featured in» and
«Expert comments» as two groups, the way sites usually split their press, is `group` on and kinds
`authored, expert_comment`.

The styles are neutral, on `currentColor` and `em`: it is the site's design, not the panel's. The
logos are held to one height and never wider than their cell, so a wide wordmark and a square badge
stand in one row.

### Rebuilding the kinds of an installed block

The options of the **Kinds** field are the site's kinds **as they were when the block type was
installed** — the offer is taken once, and from then on the type is the site's. A kind added to the
config later is added to the blocks by hand:

1. **Blocks** → **Press outlets** → **Fields** → the field **Kinds of article**;
2. add an option — the key as its value, the word as its label:

   ```json
   { "value": "podcast", "label": "Podcast" }
   ```

3. publish the type; the same for **Press articles**.

A block with **Kinds** left empty needs none of this: empty is «every kind of the config», read on
every render, so the new kind is already there.

## `press()` in a template

For a template of the site, or a block that wants what the offered ones do not do. It returns cards,
and never shows what a reader may not see in the language of the page. It is a
[`RecordQuery`](./collections#a-helper-for-templates-recordquery), so the shared steps mean what they
mean in `services()` or `team()`.

```blade
@foreach (press()->featured()->take(12) as $outlet)
    <a href="{{ $outlet['link'] }}"><img src="{{ $outlet['logo']['url'] }}" alt="{{ $outlet['title'] }}"></a>
@endforeach

@foreach (press()->articles()->kind('interview')->take(6) as $article)
    <a href="{{ $article['target'] }}">{{ $article['title'] }}</a>
    — {{ $article['outlet']['title'] }}, {{ $article['when'] }}
@endforeach
```

| Step                  | What it does                                                          |
| --------------------- | --------------------------------------------------------------------- |
| `press()->outlets()`  | Outlets in their own order — the default                              |
| `press()->articles()` | Articles of every outlet by date, the latest first, undated last      |
| `featured()`          | Only the outlets marked for the strip; in a feed, only their articles |
| `kind('authored')`    | Articles of this kind; outlets that ran one. A list, or `[]` for all  |
| `only([3, 7])`        | Only these, in this order                                             |
| `except($outlet)`     | All but these                                                         |
| `take(6)`             | At most this many, counted after what is not seen; null or 0 — all    |
| `locale('uk')`        | The language of the cards; by default the page's                      |
| `get()`, `first()`    | The cards, or one; the query itself can be looped over and counted    |
| `models()`            | The outlets or articles behind the cards, for code that needs a model |

An outlet's card:

```php
[
    'id' => 3,
    'url' => 'https://site.test/press/tatler-asia', // its page; null without pages
    'link' => 'https://site.test/press/tatler-asia', // where its logo leads: the page, else its website
    'title' => 'Tatler Asia',
    'summary' => '…',                   // '' when there is none in the language
    'logo' => ['url' => …, 'width' => …, 'height' => …, 'alt' => …], // or null
    'website' => 'https://tatler.example',
    'featured' => true,
    'count' => 4,                       // articles seen in the language
    'kinds' => ['interview', 'authored'], // the kinds of those articles, in the config's order
    'fields' => [],                     // the project's own fields, by name
]
```

An article's card:

```php
[
    'id' => 12,
    'outlet' => ['id' => 3, 'title' => 'Tatler Asia', 'url' => …, 'logo' => 'https://…/logo.svg'],
    'title' => 'Interview: …',
    'excerpt' => '…',
    'kind' => 'interview',
    'kind_label' => 'Interview',        // the word, in the language of the page
    'date' => '2023-08-12',             // or null
    'when' => 'August 2023',            // printed with its precision; '' without a date
    'target' => 'https://…',            // where the title leads: the address, else the PDF
    'url' => 'https://…',               // the address, or null
    'pdf' => 'https://…/scan.pdf',      // the PDF, or null
    'fields' => [],
]
```

A list of any length costs the same few queries. The helper is declared only if the site has no
`press()` of its own; `php artisan webx:doctor` says whose it is.

## The page of an outlet

`webx-press::outlet` with its parts — `outlet/heading`, `outlet/facts`, `outlet/articles` and
`partials/article` — each its own `@include`, so a site publishes them and rewrites the one it wants:

```bash
php artisan vendor:publish --tag=webx-press-views
```

`webx-press.views.outlet` names the view, `webx-press.layout` the Blade component it stands in (the
site's `<x-layout>`, say), `webx-press.breadcrumbs` whether the visible trail is printed. The page
prints the outlet's SEO card — falling back on its name, its summary and its logo — and an
`ItemList` of its articles, each an `Article` with the outlet as its `publisher` and a
`datePublished` only when the day is known. That markup wins no rich result; it ties the site to the
outlets for search engines and agents.

## Without pages

```php
// config/webx-press.php
'pages' => false,
```

For a site that only wants the strip of logos: no route, no address, no sitemap line, nothing for a
menu to point at, and no slug on the form. A logo in a block leads to the outlet's website instead —
the card's `url` is null and its `link` is the website.

The prefix — `webx-press.prefix`, `press` by default — is never empty: outlets at the root of the
site would argue with the tree of pages over every address. Changing it afterwards is
`php artisan webx:routes:rebuild --type=press-outlet`; the old addresses stay behind as aliases that
redirect.

## The panel

**Press** is a list and an editor side by side (`WxListDetail`), without pages. A row is the logo (or
the initials), the name, a star when it is in the strip of logos, how many articles it has and the
languages it is seen in. The drag writes the order of the whole list; while a search narrows it,
there are no grips. **New outlet** is a row that opens an empty form; the outlet is created by its
first save. On a phone the editor slides over the list and draws its own «Back».

The editor is the screen `press.outlet-form` in three tabs — **General** (the logo, the name, the
address, the website, the summary, **Published**, **In the strip of logos** and the project's card),
**Articles** (the cards, each collapsed to «#2 · its title»), **SEO**. Save with the button or
`Ctrl+S`; leaving with unsaved changes asks first. A refused article opens by itself with the error
under its field, and nothing of the save is written.

```
GET    /api/cms/press                  trashed, search — no pages
POST   /api/cms/press                  { values } — created from the form, articles included
GET    /api/cms/press/{id}             { outlet, values, prefix }
PUT    /api/cms/press/{id}             { values } — 422 under articles.<n>.<field>
DELETE /api/cms/press/{id}             to the bin · POST /restore
POST   /api/cms/press/reorder          { ids }
```

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the section is ten tools:

| Tool                    | What it does                                                      |
| ----------------------- | ----------------------------------------------------------------- |
| `press_list`            | Every outlet in its order, or words — or the bin                  |
| `press_get`             | One outlet in full, with its articles and where each is seen      |
| `press_create`          | An outlet at the end of the list, with its articles; unpublished  |
| `press_update`          | The outlet's own values; its articles are not touched here        |
| `press_delete`          | To the bin, its articles with it                                  |
| `press_reorder`         | The order of the outlets                                          |
| `press_articles_add`    | An article at the end of an outlet, or at a `position`            |
| `press_articles_update` | An article's values, by language                                  |
| `press_articles_delete` | An article, for good — `is_hidden` keeps it instead               |
| `press_articles_move`   | An article to another place in its outlet, or into another outlet |

The articles have tools of their own so that an agent adding one interview does not send back the
whole list — and so that an article added in the panel a minute ago is not deleted by a list read
before it. Underneath, each is a save of the outlet's form, the one the panel uses, in one
transaction: a refused article leaves the outlet as it was, and the refusal names it by its id.

An outlet is named by its id or its name in any language, an article by its id. A plain string in a
translated field is the default language; `{ "en": "…", "ru": "…" }` is every language, and `""` for
one language takes it away. A kind is a key of the config — the tools list the site's own. A logo and
a PDF are library keys (`"media/ab/cd/scan.pdf"`), and a key the library does not have is refused.

Before writing, an agent reads **`press://catalog`**: every outlet in its order, unpublished ones
included, with its articles in theirs — each with its kind, when it ran, where it leads, the
languages its title is written in (`written_in`) and the ones a reader sees it in (`visible_in`).

## Demo content

`php artisan webx:demo` puts four logos and a one-page PDF into a **Press** folder of the library,
and four outlets with eight articles in the two languages of the demo, as far as the site has them.
Every kind of the default four is there; one article leads to the PDF only and one to an address
with the PDF beside it; one is hidden; one has a title in English only, so the second language does
not show it; two are dated to the month and one to the year. One outlet is not published, one is
left out of the strip of logos. With `module-pages` there is the page at the prefix: the strip of
the marked logos, and the catalogue in groups under it. Outlets that already exist leave the demo
alone; `--remove` takes all of it back out.
