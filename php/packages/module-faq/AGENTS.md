# webx-ui/module-faq

Questions and answers: flat categories, two orders, anchors that never move, and a FAQ block any
page can show, with `FAQPage` markup. The module has **no public route** — a question reaches the
site in a block, and the page around it brings the address, SEO and menu entry. The sections
«Questions» and «Categories» of the panel and the MCP tools `faq_*`, `faq_categories_*` edit it.
The block and its template are `webx-ui/module-blocks`, the markup `webx-ui/module-seo`, the page
`webx-ui/module-pages`, categories `webx-ui/module-admin` — read their guides for those.

## What it owns

- **Tables** `faq_questions`, `faq_categories`, `faq_category_question`
  (`WebxUi\Faq\Models\Question`, `FaqCategory`). `question` and `answer` are translatable; a
  question is shown in a language only when it has both there — no other language stands in.
- **Anchors**: made once from the question in the default language (`paying-by-card`, then
  `-2`, or `q-<id>`), never changed afterwards, so `/faq#paying-by-card` keeps working.
- **Two orders**: `position` on the question is the whole list, `item_position` on the link is the
  place inside one category.
- **Collection source** `faq` and the offered **block type** `faq` (`resources/blocks/faq.json`): a
  heading, a `wx-collection` on the `faq` source, the label of the «All» button.
- **Markup**: one `FAQPage` per page in the `<head>`, however many FAQ blocks it has; by default on
  when the block shows all categories, off when it picks some. `config('webx-faq.markup')` is the
  site-wide switch.
- **Panel screens** `faq.form` and `faq.category-form`; API under `/api/cms/faq/questions` and
  `/api/cms/faq/categories`; permissions `faq.view`, `faq.manage`, `faq.categories.manage`.
- **MCP** tools `faq_list`, `faq_get`, `faq_create`, `faq_update`, `faq_delete`, `faq_reorder`;
  `faq_categories_list`, `faq_categories_create`, `faq_categories_update`,
  `faq_categories_delete`, `faq_categories_reorder`. Resource `faq://catalog`. Scopes `faq:read`,
  `faq:write`, `faq-categories:read`, `faq-categories:write`.
- Also registered: demo content (`resources/demo`) — with `module-pages` a `/faq` page.

## Change it without forking

| You want                                  | Do this                                                                               |
| ----------------------------------------- | ------------------------------------------------------------------------------------- |
| The FAQ block on the site                 | `php artisan webx:blocks:offered --install --module=faq` (`webx:setup` runs it)       |
| A FAQ page at `/faq`                      | a page of `module-pages` with the FAQ block, every category and the filter on         |
| «The payment questions» on a service page | the FAQ block with that category picked — markup stays off there by default           |
| Different markup or styles of the block   | rewrite the block type `faq` in the panel; the site's copy is never overwritten       |
| No `FAQPage` markup anywhere              | `WEBX_FAQ_MARKUP=false` (publish the config: `--tag=webx-faq-config`)                 |
| A field of the project on a question      | a patch on `faq.form` into `project-fields`; read it with `$question->extra('<key>')` |
| A field of the project on a category      | a patch on `faq.category-form` into `project-fields`                                  |
| Other words in the panel                  | `php artisan vendor:publish --tag=webx-faq-lang`                                      |

A screen patch is `Screens::extend('<screen>', [...])` in `AppServiceProvider::boot()` and
addresses nodes by `id`. Question editor: `content`, `question`, `answer`, `settings`,
`published`, `categories`, `anchor`, `project-fields`. Category editor: `naming`, `title`,
`is-visible`, `project-fields`.

The markup needs the page's content rendered before `@webxSeo` in the layout, which is how
`module-pages` and `module-services` render. A layout that prints the `<head>` first gets none.

## Do not

- Do not edit anything in `vendor/webx-ui/module-faq` or copy it into the site. The rows above
  are the supported ways; if none fits, the package is missing a seam — say so.
- Do not add a route or a controller for `/faq`: the module has no public half on purpose. Make
  a page and put the FAQ block on it, so the page owns the address, SEO and menu entry.
- Do not turn the markup on for blocks that pick categories and stand on many pages: a search
  engine would find the same question marked up twenty times. Mark up the one full FAQ page.
- Do not rename or rewrite an anchor to match a reworded question: links to it from mail, ads and
  other pages stop working. The anchor is permanent by design; leave it.
- Do not add a migration for a project's field: patch `project-fields`; the value lives in the
  `extra` column and is validated with the rest of the form.
- Do not delete rows with SQL: `faq_delete` puts a question in the bin and the panel restores it
  with its anchor. A raw delete leaves the links in `faq_category_question` behind.

## Check your work

- Open the page with the FAQ block: the accordion is `<details>`, a link with `#anchor` opens its
  question, the filter shows only the categories with something visible in them.
- Check the `FAQPage` in the page source or the Rich Results Test; a question missing in the
  current language is simply not there.
- With MCP: read `faq://catalog` first, then `faq_get` by id or anchor; every mutating tool takes
  `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the block's template data, the panel API, the demo.
- Guide: https://webx-ui.github.io/webx-ui/guide/faq
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_FAQ.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
