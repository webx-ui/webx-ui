# webx-ui/module-press

"Press about us": the **outlets** that wrote about the site — logo, name, a few words, website —
and the **articles** in them, each leading out to an address or to a PDF from the library. Every
outlet has a page under a prefix (`/press/some-magazine`); the prefix itself is a page of
`webx-ui/module-pages` with an offered block on it. The section «Press» of the panel and the MCP
tools `press_*` edit it. Logos and PDFs are `webx-ui/module-media`, the blocks
`webx-ui/module-blocks`, addresses `webx-ui/routing`, the SEO card `webx-ui/module-seo` — read
their guides for those.

## What it owns

- **Tables** `press_outlets` and `press_articles` (`WebxUi\Press\Models\Outlet`, `Article`). An
  outlet has no draft: `published` is its whole life. Articles are rows on the outlet's form,
  saved with it. An article is seen in a language only when it has a title there.
- **Address type** `press-outlet` under `config('webx-press.prefix')`, `OnConflict::Fail`. With
  `webx-press.pages` off there is no route, no address and no slug field at all.
- **Kinds** of article: `config('webx-press.kinds')` — `mention`, `interview`, `expert_comment`,
  `authored`; words in `webx-press::kinds.<key>`.
- **Public view** `config('webx-press.views.outlet')`, default `press.outlet`, made of parts
  `outlet/heading`, `outlet/facts`, `outlet/articles`, `partials/article`.
- **Offered block types** `press-logos`, `press-outlets`, `press-articles`, and in Blade
  `press()` — `press()->featured()->take(12)`, `press()->articles()->kind('interview')`.
- **Panel screen** `press.outlet-form` (tabs `general`, `articles-tab`, `seo`); API under
  `/api/cms/press`; permissions `press.view`, `press.manage`.
- **MCP** tools `press_list`, `press_get`, `press_create`, `press_update`, `press_delete`,
  `press_reorder`, `press_articles_add`, `press_articles_update`, `press_articles_delete`,
  `press_articles_move`. Resource `press://catalog`. Scopes `press:read`, `press:write`.
- Also registered: a link source for outlets, demo content (`resources/demo`).

## Change it without forking

| You want                              | Do this                                                                                                                                                                   |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| The press blocks on the site          | `php artisan webx:blocks:offered --install --module=press` (`webx:setup` runs it)                                                                                         |
| A page at `/press`                    | a page of `module-pages` with the slug `press` and the logos or outlets block on it                                                                                       |
| Outlet pages inside the site's header | `'layout' => 'layout'` in `config/webx-press.php` (`<x-layout>`), or `WEBX_PRESS_LAYOUT`                                                                                  |
| One part of the outlet page different | `php artisan vendor:publish --tag=webx-press-views`, keep only that part                                                                                                  |
| The outlet page from another view     | `WEBX_PRESS_VIEW_OUTLET`                                                                                                                                                  |
| No outlet pages at all                | `WEBX_PRESS_PAGES=false` — logos then lead to the outlet's website                                                                                                        |
| Another first segment                 | `WEBX_PRESS_PREFIX`, then `php artisan webx:routes:rebuild --type=press-outlet`                                                                                           |
| A kind of the site's own              | add its key to `kinds` (`--tag=webx-press-config`) and its word to `lang/vendor/webx-press/<locale>/kinds.php`; add it to the installed block types' options in the panel |
| No visible breadcrumbs                | `WEBX_PRESS_BREADCRUMBS=false` (the BreadcrumbList in `<head>` is module-seo's and stays)                                                                                 |
| A field of the project on an outlet   | a patch: `Screens::extend('press.outlet-form', [...])` into `project-fields`                                                                                              |
| Other words in the panel              | `php artisan vendor:publish --tag=webx-press-lang`                                                                                                                        |
| Logos or articles anywhere else       | `press()` in a template, or one of the three block types                                                                                                                  |

The layout component's contract is two slots, `head` and the default one, plus `@stack('head')`
beside `{{ $head }}`; `php artisan webx:panel --sync` sets `layout` when the site has
`resources/views/components/layout.blade.php`.

A screen patch addresses nodes by `id`: `general`, `outlet`, `logo`, `title`, `slug`,
`website-url`, `summary`, `settings`, `published`, `featured`, `project-fields`, `articles-tab`,
`articles` (the rows: `article-title`, `article-excerpt`, `article-kind`,
`article-published-on`, `article-date-precision`, `article-url`, `article-file`,
`article-hidden`), `seo`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-press` or copy it into the site. The rows above
  are the supported ways; if none fits, the package is missing a seam — say so.
- Do not add a route for the prefix: the outlets' list belongs to a page with a block, which
  also brings the SEO, the menu entry and the first step of every outlet's trail.
- Do not take a key out of `kinds` lightly: every article with it becomes "no kind" and is refused
  on its next save. Rename the word in the lang file instead.
- Do not write a logo or a PDF as a URL: they are library keys (`media/...`), and a key the
  library does not have is refused. Upload into the library first.
- Do not save an article without a `url` or a `file`: it is refused — an article always leads out.
  To keep one off the site, set `is_hidden`; `press_articles_delete` deletes for good.
- Do not move an article by deleting and adding it: `press_articles_move` keeps its id, also into
  another outlet.
- Do not delete rows with SQL: `press_delete` puts the outlet in the bin with its articles, and
  the panel restores it. A raw delete leaves its address in the routing registry behind.

## Check your work

- `php artisan webx:doctor` — the layout and its `@stack('head')` among the rest.
- Open the outlet page in each language: an outlet with no article titled in a language is a 404
  there and is not in the sitemap. Open the page at the prefix with its block.
- With MCP: read `press://catalog` first (`written_in`, `visible_in` per article), then
  `press_get`; every mutating tool takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — fields, dates, `press()` and its cards, the blocks.
- Guide: https://webx-ui.github.io/webx-ui/guide/press
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PRESS.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
