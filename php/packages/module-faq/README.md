# webx-ui/module-faq

Questions and answers as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin
panel: flat categories, two orders, anchors that never move, and a FAQ block any page can show,
with `FAQPage` markup.

The module has no public route. A question reaches the site **in a block**: "the payment questions"
on a service page, "every question with a filter" on an ordinary page at `/faq`. The page brings
the address, the SEO, the menu entry and the sitemap line; the module brings the questions.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-blocks`, `webx-ui/localization`
- `webx-ui/module-seo` for the markup, `webx-ui/module-pages` for a page to put the block on —
  both suggested, neither required

## Install

```bash
composer require webx-ui/module-faq
php artisan migrate
php artisan webx:blocks:offered --install --module=faq
```

The last line puts the offered block type **FAQ** on the site and publishes it. A type the site
already has under the slug `faq` is left alone: the site may have rewritten it. `webx:setup` runs
this line by itself for a new site.

Permissions: `faq.view`, `faq.manage`, `faq.categories.manage`.

## The FAQ block

The block type is a document in `resources/blocks/faq.json`, the same format as
`webx:blocks:export`. Its schema is a heading, a `wx-collection` field on the `faq` source and the
label of the "All" button:

```json
{ "id": "questions", "type": "wx-collection", "label": "Questions", "props": { "source": "faq" } }
```

The editor of the page chooses the categories (none means all), a limit, whether to draw the
filter, and whether to mark up. The template gets the questions already read:

```blade
@foreach ($questions['items'] as $item)
    <details id="{{ $item['anchor'] }}">
        <summary>{{ $item['question'] }}</summary>
        {!! $item['answer'] !!}
    </details>
@endforeach
```

Each item is `id`, `anchor`, `categories` (ids), `question` (text) and `answer` (HTML, library
pictures pointed at where they live now). `$questions['groups']` are the filter's buttons — the
visible categories with something shown in them — and `$questions['filter']` says whether to draw
them.

The accordion is `<details>` and works without JavaScript. The block's script adds the filter and
opens the question a link points at (`/faq#paying-by-card`), on load and when the hash changes.
The styles are neutral — `currentColor` and `em` — so the block stands in any site's design; a
site rewrites them in the panel like any other block type.

**Order.** One chosen category shows that category's order; none or several show the order of the
whole list, each question once.

**Languages.** A question is shown in a language when it has both the question and the answer in
it. No other language stands in: a FAQ with half its answers in another language is worse than a
short one.

## FAQPage markup

A FAQ block puts one `FAQPage` in the `<head>` for the questions it shows — through
`Seo::put('faq', …)` of `webx-ui/module-seo`, so two blocks on one page give one `FAQPage` and a
question they share is in it once.

By default the markup is **on when the block shows all categories and off when it picks some**:
"the payment questions" stand on every service page, and a search engine should not find the same
question marked up on twenty of them. The editor can turn it either way on the block.
`webx-faq.markup` (`WEBX_FAQ_MARKUP`) turns it off for the whole site.

The markup needs the page's content to be rendered before `@webxSeo` in the layout, which is how
`module-pages` and `module-services` render. A layout that prints the `<head>` first gets none.

## Anchors

An anchor is made once, when the question is created, from the question in the site's default
language: `paying-by-card`, then `paying-by-card-2` for the next one. A question with no words in
that language gets `q-<id>`. It never changes after that — not when the question is reworded, not
when it goes to the bin and back — so a link to it keeps working.

## Two orders

`position` on the question is the order of the whole list. `item_position` on the link is its place
inside one category. The panel drags whichever list the editor is looking at. A question newly
filed into a category takes its place there by the whole list.

## Fields of the project

A site adds its own fields with a patch on `faq.form` or `faq.category-form`, not with a migration.
Each screen has a card with the id `project-fields`:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": { "id": "source", "type": "wx-input", "name": "source", "label": "Where it came from" }
  }
]
```

The value lives in the `extra` column: `$question->extra('source')`.

## Panel API

Under `webx-admin.api_path` (`api/cms` by default):

| Method   | Path                         |                                          |
| -------- | ---------------------------- | ---------------------------------------- |
| `GET`    | `faq/questions`              | `?category=`, `?trashed=1`, `?search=`   |
| `POST`   | `faq/questions`              | `{ values }` — created from the form     |
| `GET`    | `faq/questions/{id}`         | `{ question, values }`                   |
| `PUT`    | `faq/questions/{id}`         | `{ values }` — 422 under the field name  |
| `DELETE` | `faq/questions/{id}`         | into the bin                             |
| `POST`   | `faq/questions/{id}/restore` |                                          |
| `POST`   | `faq/questions/reorder`      | `{ ids, category? }`                     |
|          | `faq/categories/*`           | the shared category routes, no addresses |

## MCP

With `webx-ui/mcp` serving the panel to agents, the questions are six tools behind `faq:read` and
`faq:write` — `faq_list`, `faq_get`, `faq_create`, `faq_update`, `faq_delete`, `faq_reorder` — and
the categories are the shared `faq_categories_*`. They go through the same list, form and order code
as the panel: a project's field is refused where the panel would refuse it, and `faq_create` writes
the question, its categories and those fields in one transaction. A question is named by its id or
its anchor, a category by its id or its title.

`faq://catalog` is what an agent reads first: every category with its questions in its own order,
unpublished ones marked, the languages each is seen in, and the questions in no category at the end.

## Demo

`php artisan webx:demo` seeds three categories and ten questions in English and Russian, as far as
the site has them: one question in two categories, one unpublished, one without a translation. It
installs the FAQ block type if the site lacks it, makes a page `/faq` with every category and the
filter when `module-pages` is there, and puts «Questions about payment» into a demo service when
`module-services` is. `--remove` takes it all back.

## Translations

Ten languages ship: `en`, `ru` and `uk` are read by a native speaker; `de`, `pl`, `fr`, `es`, `it`,
`pt` and `tr` are machine translations. Corrections from a native speaker are welcome.

## License

MIT
