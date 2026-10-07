# webx-ui/module-services

A catalogue of services: a service is built like a page — an address, content of blocks, a draft
and publications, SEO — and is filed into flat categories that are pages of the site too, with an
index page above them. The section «Services» of the panel and the MCP tools `services_*` edit it.
Almost nothing here is written from scratch: the address is `webx-ui/routing`, the content
`webx-ui/module-blocks`, the draft, history and categories `webx-ui/module-admin`, the SEO card
`webx-ui/module-seo`, the covers `webx-ui/module-media` — read their guides when the question is
about one of those.

## What it owns

- **Tables** `services` (`WebxUi\Services\Models\Service`), `service_categories`
  (`WebxUi\Services\Models\ServiceCategory`) and the link `service_category_service`. `position` is the
  order of the whole list, `item_position` the order inside one category.
- **Address types** `service` and `service-category` in the routing registry, both on one level
  under `config('webx-services.prefix')` (default `services`), `OnConflict::Fail` — a category
  and a service that want the same slug are refused, the second under its own field. A service's
  address does not contain its category.
- **Index route** `webx.services.index` at the prefix, while `webx-services.index` is on and the
  prefix is not empty.
- **Public views** `config('webx-services.views.index' | '.category' | '.service')`, defaults
  `services.index`, `services.category`, `services.service`; until the site has them the
  package prints its own bare documents.
- **Helper** `services()`, collection source `services`, offered block type `services`, relation
  target `service` (other modules link tariffs, people, recipes to services through it).
- **Panel screens** `services.form` (nodes `tabs`, `content`, `blocks`, `settings`, `naming`,
  `title`, `slug`, `lead`, `presentation`, `cover`, `taxonomy`, `categories`, `project-fields`,
  `seo`, `history`, `versions`) and `services.category-form` (`content`, `naming`, `title`,
  `slug`, `is-visible`, `lead`, `presentation`, `cover`, `project-fields`, `seo`; plus
  `blocks-tab`, `blocks` when category blocks are on).
- **API** under `/api/cms/services` and `/api/cms/services/categories`; permissions
  `services.view`, `services.manage`, `services.categories.manage`.
- **MCP** tools `services_list`, `services_get`, `services_create`, `services_update`,
  `services_publish`, `services_unpublish`, `services_discard`, `services_versions`,
  `services_version_restore`, `services_delete`, `services_reorder`, and
  `service_categories_list`, `service_categories_create`, `service_categories_update`,
  `service_categories_delete`, `service_categories_reorder`; resource `services://catalog`.
  Scopes `services:read`, `services:write`, `service-categories:read`, `service-categories:write`.
- Also registered: link sources (services and categories in every link picker), the sitemap entry
  of the index, demo content (`resources/demo`).

## Change it without forking

| You want                                 | Do this                                                                                                       |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Services inside the site's header/footer | `WEBX_SERVICES_LAYOUT=layout` (`<x-layout>`, slots `head` and default, `@stack('head')` in it)                |
| Different markup of the three pages      | write `resources/views/services/{index,category,service}.blade.php`, or `WEBX_SERVICES_VIEW_*`                |
| Change the package's own views           | `php artisan vendor:publish --tag=webx-services-views`, keep only the files you change                        |
| Another first segment, or none           | `WEBX_SERVICES_PREFIX`, then `php artisan webx:routes:rebuild --type=service --type=service-category`         |
| The index page built of blocks           | `WEBX_SERVICES_INDEX=false` and a page with the slug of the prefix in `webx-ui/module-pages`                  |
| Category pages as landing pages          | `WEBX_SERVICES_CATEGORY_BLOCKS=true` — adds the Blocks tab; your category view must print them                |
| No visible breadcrumbs                   | `WEBX_SERVICES_BREADCRUMBS=false` (the BreadcrumbList in `<head>` stays, it is module-seo's)                  |
| A field or tab in the editor             | a patch: `Screens::extend('services.form', [...])` into `project-fields`; read `$service->extra()`            |
| Service cards in a block                 | `services()->in('implants')->except($service)->take(6)`, or `webx:blocks:offered --install --module=services` |
| Other words in the panel                 | `php artisan vendor:publish --tag=webx-services-lang`                                                         |

All keys live in `config/webx-services.php` (`vendor:publish --tag=webx-services-config`). The
`index` view is handed `$categories` and `$uncategorised`, `category` gets `$category` and
`$services`, `service` gets `$service` and `$category` (the main one, or null). A screen patch
whose target is gone throws when the screen is first built.

## Do not

- Do not edit anything in `vendor/webx-ui/module-services`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not change `prefix` without `webx:routes:rebuild`: the stored addresses stay at the old
  prefix until rebuilt; the rebuild also leaves redirecting aliases on the old ones.
- Do not add a route for the prefix or for a service in `routes/web.php`: the registry owns those
  addresses. To take over the index, switch `index` off and make a page there.
- Do not write blocks through `services_update`: content goes through `blocks_edit_content`,
  which changes one node and leaves the rest alone. A `blocks` key there is refused.
- Do not save without the `revision` you read: a stale one is answered `409`. Read again with
  `services_get` and redo the change; do not retry blindly.
- Do not add a migration for a project field: the patch and the `extra` column are the place.
- Do not bin a category that still has services — it is refused with the count. Move the services
  out first. Do not delete rows with SQL: a raw delete leaves addresses in the routing registry.
- Do not print `<title>` in a site's copy of a view: with an empty SEO card the service and the
  category name the page themselves (`seoFallback()` — the name through the title template, the
  lead, the cover), and the index is called by the section.
  A copy published before still has an `@if ($meta->title === null)` block and the `$seo` /
  `$meta` lines for it: delete them, they never print any more.

## Check your work

- `php artisan webx:doctor` — the layout and its `@stack('head')`, and whose `services()` it is.
- Open the service on the site after publishing it: a draft is a 404 and absent from the sitemap.
  A service in a hidden category still answers at its own address.
- With MCP: read `services://catalog` first, then `services_get`; every tool that changes
  something takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — addresses, the two orders, SEO, views, `services()`.
- Guide: https://webx-ui.github.io/webx-ui/guide/services
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_SERVICES.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
