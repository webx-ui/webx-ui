# webx-ui/module-blog

A blog: articles made of blocks, rubrics and tags, each with its own address, scheduled
publication by date, a feed and an RSS. The sections «Articles», «Rubrics» and «Tags» of the
panel and the MCP tools `articles_*`, `rubrics_*`, `tags_*` edit it. Most of it is borrowed: the
addresses are `webx-ui/routing`, the body `webx-ui/module-blocks`, the draft, versions and shared
categories `webx-ui/module-admin`, the SEO card and redirects `webx-ui/module-seo`, the covers
`webx-ui/module-media`, the languages `webx-ui/localization` — read their guides for those.

## What it owns

- **Tables** `blog_articles`, `blog_rubrics`, `blog_tags` and the links `blog_article_rubric`,
  `blog_article_tag`, `blog_article_related` (`WebxUi\Blog\Models\Article`, `Rubric`, `Tag`).
  Rubrics are the blog's categories of the panel's shared kind. `title` and `slug` are translatable.
- **Address types** `article`, `rubric` (`blog/repairs`) and `tag` (`blog/tag/belts`), all under
  `config('webx-blog.prefix')` in one flat namespace, `OnConflict::Fail` on all three — a taken
  address is an error on the slug field. The rubric is not part of an article's address.
- **Routes** `webx.blog.feed` (the prefix itself; not registered when the prefix is empty) and
  `webx.blog.rss` (`{prefix}/rss`). Page two of a listing is `?page=2`, not an address.
- **Public views** `config('webx-blog.views')` — `article`, `feed`, `rubric`, `tag`, `rss`,
  defaults `blog.*`; until the site has them the package prints `webx-blog::*`, bare documents.
- **Publication is the date**: `published_at` in the future is scheduled, in the past is live.
  There is no queue and no separate flag.
- **Panel screens** `blog.article-form` (tabs `content`, `settings`, `seo`, `history`) and
  `blog.category-form` for a rubric; API under `/api/cms/blog` (`articles`, `rubrics`, `tags`);
  permissions `blog.articles.view`, `blog.articles.manage`, `blog.taxonomy.manage`.
- **MCP** tools `articles_list`, `articles_get`, `articles_create`, `articles_update`,
  `articles_publish`, `articles_unpublish`, `articles_discard`, `articles_delete`; `rubrics_list`, `rubrics_create`,
  `rubrics_update`, `rubrics_delete`, `rubrics_reorder`; `tags_list`, `tags_merge`. Resource
  `blog://feed`, prompt `write_article`. Scopes `articles:read`, `articles:write`,
  `rubrics:read`, `rubrics:write`, `tags:read`, `tags:write`.
- Also registered: link sources for articles, rubrics and tags, an SEO source for tag pages
  (`noindex` by default), the feed in the sitemap, demo content (`resources/demo`).

## Change it without forking

| You want                                | Do this                                                                                            |
| --------------------------------------- | -------------------------------------------------------------------------------------------------- |
| The blog inside the site's header       | `'layout' => 'layout'` in `config/webx-blog.php` (`<x-layout>`), or `WEBX_BLOG_LAYOUT`             |
| Different markup of a page              | write `resources/views/blog/article.blade.php` (or `feed`, `rubric`, `tag`, `rss`)                 |
| A page printed by another view          | `WEBX_BLOG_VIEW_ARTICLE`, `_FEED`, `_RUBRIC`, `_TAG`, `_RSS`                                       |
| Change the package's own views          | `php artisan vendor:publish --tag=webx-blog-views`, keep only the files you change                 |
| Another first segment, or none          | `WEBX_BLOG_PREFIX`, then `php artisan webx:routes:rebuild --type=article --type=rubric --type=tag` |
| More or fewer articles per listing      | `per_page` in `config/webx-blog.php` (publish it: `--tag=webx-blog-config`)                        |
| No automatic "read next"                | `'related' => 0` — the articles pinned by hand stay                                                |
| New tags open to the index              | `'tags' => ['noindex' => false]`; one tag: clear its flag or write a rule in `seo_urls`            |
| No visible breadcrumbs                  | `WEBX_BLOG_BREADCRUMBS=false` (the BreadcrumbList in `<head>` is module-seo's and stays)           |
| A field on the article or rubric editor | a patch: `Screens::extend('blog.article-form', [...])` in `AppServiceProvider::boot()`             |
| Other words in the panel                | `php artisan vendor:publish --tag=webx-blog-lang`                                                  |
| What an article looks like inside       | blocks — a block type in `webx-ui/module-blocks`, not a template here                              |

The layout component's contract is two slots, `head` and the default one, plus `@stack('head')`
beside `{{ $head }}`; `php artisan webx:panel --sync` sets `layout` when the site has
`resources/views/components/layout.blade.php`. The RSS has no layout.

A screen patch addresses nodes by `id`. Article editor: `content`, `blocks`, `settings`,
`naming`, `title`, `slug`, `lead`, `presentation`, `cover`, `publication`, `published-at`,
`unpublish`, `author`, `pinned`, `taxonomy`, `rubrics`, `tags`, `relations`, `related`,
`project-fields`, `seo`, `history`, `versions`. Rubric editor: `content`, `naming`, `title`,
`slug`, `is-visible`, `lead`, `presentation`, `cover`, `project-fields`, `seo`. Put a project's
own fields into `project-fields`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-blog` or copy it into the site. The rows above
  are the supported ways; if none fits, the package is missing a seam — say so.
- Do not set `published_at` while "just filling in the settings": a past date puts the article
  on the site at once. Leave it empty and call `articles_publish` (with `at` for a later day).
- Do not write the body through `articles_update`: a `blocks` key is refused. Content goes
  through `blocks_edit_content`, which changes one node and leaves the rest alone.
- Do not save without the `revision` you read: a stale one is answered `409` with the article as
  it now is. Read again and redo the change.
- Do not change the prefix by editing rows: run `webx:routes:rebuild` for the three types, which
  recomputes the paths and keeps the old ones as redirecting aliases. A raw update leaves dead
  addresses behind.
- Do not invent tags while writing: `tags_list` first and reuse a word that exists. Duplicates
  are untangled with `tags_merge`, which is irreversible — run it with `dry_run: true` first.
- Do not delete a rubric that still holds articles — it is refused with the count. Move the
  articles to another rubric, or hide the rubric (`is_visible`) instead.
- Do not put a list of articles on `/` through this package: with an empty prefix the feed route
  is not registered, because `/` belongs to the site. Make that a page with a block.

## Check your work

- `php artisan webx:doctor` — the layout and its `@stack('head')` among the rest.
- Open the article, its rubric, the feed and `{prefix}/rss` on the site. A scheduled article is a
  404 to readers until its date; a draft is seen through the preview link the editor gives.
- With MCP: `articles_list`, then `articles_get` by address (`"/blog/how-to-choose"`) — its
  `preview_url` is the draft as the site prints it; every mutating tool takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the PHP API: publishing, tags and the index, merging.
- Guide: https://webx-ui.github.io/webx-ui/guide/blog
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_BLOG.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
