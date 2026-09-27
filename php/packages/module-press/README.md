# webx-ui/module-press

"Press about us" as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel:
the **outlets** that wrote about the site — a magazine, a paper, a portal: its logo, its name, a
few words and its website — and the **articles** in them: an interview, a column, an expert's
comment. An article always leads out: to the article at its address, or to a PDF from the library.

Every outlet has a page of its own under a prefix (`/press/tatler-asia`) with its articles on it.
The prefix itself is not the module's: `/press` is a page of `module-pages` with the catalogue
block on it.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-media`, `webx-ui/module-seo`, `webx-ui/module-blocks`,
  `webx-ui/routing`, `webx-ui/localization`
- `webx-ui/module-pages` for the page at the prefix — suggested, not required

## Install

```bash
composer require webx-ui/module-press
php artisan migrate
php artisan webx:blocks:offered --install --module=press
```

The last line puts the three offered block types on the site and publishes them. A type the site
already has under the same slug is left alone: the site may have rewritten it. `webx:setup` runs
this line by itself for a new site.

Permissions: `press.view`, `press.manage`.

## An outlet and its articles

| Outlet        | Stored as                                                            |
| ------------- | -------------------------------------------------------------------- |
| `logo`        | the value of a `wx-media` field — a library key, never an address    |
| `title`       | translatable — a proper name, shown from any language that has it    |
| `slug`        | translatable — the address of its page                               |
| `summary`     | translatable plain text — under the name, and the page's description |
| `website_url` | `http://` or `https://` only                                         |
| `featured`    | in the strip of logos, when the block asks for the marked ones       |
| `published`   | the whole of an outlet's life: no draft, no history                  |

| Article          | Stored as                                                               |
| ---------------- | ----------------------------------------------------------------------- |
| `title`          | translatable — without one in a language, the article is not seen there |
| `excerpt`        | translatable plain text                                                 |
| `kind`           | a key of `webx-press.kinds`, or none                                    |
| `published_on`   | a day                                                                   |
| `date_precision` | `day`, `month` or `year` — how much of the date is printed              |
| `url`            | the article on the outlet's site, `http(s)://` only                     |
| `file`           | a PDF from the library                                                  |
| `is_hidden`      | kept in the panel, shown nowhere                                        |

An article needs a `url` or a `file`. With both, the title leads to the address and the PDF is a
second link. The articles are edited as rows on the outlet's form and saved with it, in the order
of the rows; in the database they are a table of their own.

**Languages.** An article is seen in a language when it has a title in it — no other language
stands in. An outlet is seen in a language when it is published and has at least one article seen
there; otherwise its page is a 404 in that language and it is not in the sitemap or in the hreflang
of its page. Its name is taken from any language that has it. A language that has articles and no
slug of its own takes the outlet's slug from another language.

## Kinds

```php
// config/webx-press.php
'kinds' => ['mention', 'interview', 'expert_comment', 'authored', 'podcast'],
```

The words are `webx-press::kinds.<key>` — a kind of the site's own is a line in
`lang/vendor/webx-press/<locale>/kinds.php`. A key taken out of the list is "no kind" on every
article that had it, and refused on the next save of one.

The offered blocks take their choice of kinds from this list **when they are installed**. A kind
added later is added to the installed types in the panel: Blocks → the type → the field "Kinds of
article" → its options.

## The date

| Precision | ru              | en              |
| --------- | --------------- | --------------- |
| `day`     | 12 августа 2023 | August 12, 2023 |
| `month`   | август 2023     | August 2023     |
| `year`    | 2023            | 2023            |

`WebxUi\Press\Rendering\When::of($article, $locale)` prints it; the cards carry it as `when`.

## The page of an outlet

`resources/views/outlet.blade.php` with its parts — `outlet/heading`, `outlet/facts`,
`outlet/articles` and `partials/article` — each its own `@include`, so a site publishes and rewrites
one of them and leaves the rest:

```bash
php artisan vendor:publish --tag=webx-press-views
```

The page prints the SEO card of the outlet, falls back to its name, its summary and its logo for
the title, the description and `og:image`, and carries an `ItemList` of its articles, each an
`Article` with `publisher` the outlet as an `Organization`. The trail goes through whatever page
stands at the prefix.

## `press()` in a template

```blade
@foreach (press()->featured()->take(12) as $outlet)
    <a href="{{ $outlet['link'] }}"><img src="{{ $outlet['logo']['url'] }}" alt="{{ $outlet['title'] }}"></a>
@endforeach

@foreach (press()->articles()->kind('interview')->take(6) as $article)
    <a href="{{ $article['target'] }}">{{ $article['title'] }}</a> — {{ $article['outlet']['title'] }}, {{ $article['when'] }}
@endforeach
```

| Step                  | What it does                                                     |
| --------------------- | ---------------------------------------------------------------- |
| `press()->outlets()`  | Outlets in their own order (the default)                         |
| `press()->articles()` | Articles of every outlet by date, the latest first, undated last |
| `featured()`          | Only the outlets marked for the strip (and their articles)       |
| `kind('authored')`    | Articles of this kind; outlets that ran one                      |
| `only([3, 7])`        | Only these, in this order                                        |
| `except($outlet)`     | All but these                                                    |
| `take(6)`             | At most this many, counted after what is not seen                |
| `locale('uk')`        | The language of the cards; by default the page's                 |
| `get()`, `first()`    | The cards, or one                                                |

An outlet's card: `id`, `url` (its page, null without pages), `link` (where its logo leads: the
page, else its website), `title`, `summary`, `logo`, `website`, `featured`, `count`, `kinds`,
`fields`. An article's card: `id`, `outlet` (`id`, `title`, `url`, `logo`), `title`, `excerpt`,
`kind`, `kind_label`, `date`, `when`, `target`, `url`, `pdf`, `fields`.

## The offered blocks

- **Press logos** (`press-logos`) — a strip of logos, the marked outlets or all of them.
- **Press outlets** (`press-outlets`) — the outlets as cards, or grouped by the kinds of article
  they ran; an outlet that ran two kinds is in both groups.
- **Press articles** (`press-articles`) — the latest articles of every outlet, six by default.

## Without pages

```php
'pages' => false,
```

No route, no address, no sitemap, nothing for a menu to point at, and no slug on the form. A logo
in a block leads to the outlet's website.

## Changing the prefix

```bash
php artisan webx:routes:rebuild --type=press-outlet
```

The old addresses stay behind as aliases that redirect.

## For an agent: MCP

With the panel's MCP server on, the section is ten tools behind `press:read` and `press:write`:

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

An outlet is named by its id or by its name in any language; an article by its id. A plain string
in a translated field is the default language, `{ "en": "…", "ru": "…" }` is every language at
once. A kind is a key of `webx-press.kinds`, and the tools list the site's own. A logo and a PDF
are library keys (`"media/ab/cd/scan.pdf"`), and a key the library does not have is refused.

Every write — the articles' too — is a save of the outlet's form, the one the panel uses, in one
transaction: a refused article leaves the outlet as it was, and is refused where the panel would
refuse it, named by the article's id. Moving an article into another outlet keeps its id.

Before writing, an agent reads **`press://catalog`**: every outlet in its order, unpublished ones
included, with its articles in theirs — each with its kind, when it ran, where it leads, the
languages its title is written in (`written_in`) and the ones a reader sees it in (`visible_in`).

## Demo content

`php artisan webx:demo` puts four logos and a one-page PDF into a **Press** folder of the library,
and four outlets with eight articles in English and Russian, as far as the site has them. Every
kind of the default four is there; one article leads to the PDF only and one to an address with the
PDF beside it; one is hidden; one has a title in English only, so the Russian site does not show it;
two are dated to the month and one to the year. One outlet is not published, one is left out of
the strip of logos.

The offered block types are installed if the site has not taken them yet; then, with
`webx-ui/module-pages`, a page at the prefix under the home page: the strip of the marked logos, and
the catalogue in groups by kind under it. An outlet that already exists leaves the demo alone;
`--remove` takes all of it back out.

## Translations

Ten languages ship: `en`, `ru` and `uk` are read by a native speaker; `de`, `pl`, `fr`, `es`, `it`,
`pt` and `tr` are machine translations. Corrections from a native speaker are welcome.

## License

MIT
