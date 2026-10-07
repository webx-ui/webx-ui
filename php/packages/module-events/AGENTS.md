# webx-ui/module-events

Events: workshops, meetings and webinars with a date, a place, a price and a booking link — a
page of fixed structure per event, flat categories that are pages of the site, an `.ics` file and
schema.org `Event`. The sections «Events» and «Categories» of the panel and the MCP tools
`events_*`, `event_categories_*` edit it. The addresses are `webx-ui/routing`; the draft,
history, categories and relations `webx-ui/module-admin`; the SEO card `webx-ui/module-seo`; the
photos `webx-ui/module-media`; the languages `webx-ui/localization`; the Services field
`webx-ui/module-services` — read their guides for those.

## What it owns

- **Tables** `events`, `event_categories`, `event_category_event` (`WebxUi\Events\Models\Event`,
  `EventCategory`). Texts are translatable; categories and services wait in the draft too.
- **Address types** `event` and `event-category`, on one level under `config('webx-events.prefix')`
  (`events/spring-cooking-class`, `events/cooking-demonstrations`), `OnConflict::Fail`. The
  prefix is never empty — an empty one stops the boot with an error.
- **Routes** `webx.events.index` at the prefix (only while `webx-events.index` is on) and
  `webx.events.ics` — `{prefix}/{slug}.ics`, one event as a calendar file.
- **Public views** `config('webx-events.views')` — `index`, `category`, `event`, defaults
  `events.*`. `event` is made of parts: `event/gallery`, `heading`, `facts`, `booking`,
  `description`, `highlights`, `services`. The list is `partials/list` with `partials/card`.
- **Lists show only the events to come**; past ones keep their page, without a booking button.
  In Blade: `events()->take(3)`, `events()->past()`, `events()->in('slug')`,
  `events()->relatedTo('service', $service)`.
- **Panel screens** `events.form` (tabs `event`, `settings`, `seo`, `history`) and
  `events.category-form`; API under `/api/cms/events` and `/api/cms/events/categories`;
  permissions `events.view`, `events.manage`, `events.categories.manage`.
- **MCP** tools `events_list`, `events_get`, `events_create`, `events_update`,
  `events_duplicate`, `events_publish`, `events_unpublish`, `events_discard`, `events_delete`,
  `events_restore`, `events_purge`;
  `event_categories_list`, `event_categories_create`, `event_categories_update`,
  `event_categories_delete`, `event_categories_reorder`. Resource
  `events://catalog`. Scopes `events:read`, `events:write`, `event-categories:read`,
  `event-categories:write`.
- Also registered: link sources for events and categories, a relation target (other modules can
  link to events), the index in the sitemap, demo content (`resources/demo`).

## Change it without forking

| You want                             | Do this                                                                                         |
| ------------------------------------ | ----------------------------------------------------------------------------------------------- |
| Events inside the site's header      | `'layout' => 'layout'` in `config/webx-events.php` (`<x-layout>`), or `WEBX_EVENTS_LAYOUT`      |
| A page of your own at `/events`      | `WEBX_EVENTS_INDEX=false`, then a page of `module-pages` with the slug `events`                 |
| Another first segment                | `WEBX_EVENTS_PREFIX`, then `php artisan webx:routes:rebuild --type=event --type=event-category` |
| Prices as a number in the markup     | `WEBX_EVENTS_CURRENCY` (`EUR`, …); without it the markup carries no price                       |
| Another page size                    | `WEBX_EVENTS_PER_PAGE` (24 by default)                                                          |
| One part of the event page different | `php artisan vendor:publish --tag=webx-events-views`, keep only `event/<part>.blade.php`        |
| A page printed by another view       | `WEBX_EVENTS_VIEW_INDEX`, `WEBX_EVENTS_VIEW_CATEGORY`, `WEBX_EVENTS_VIEW_EVENT`                 |
| A field of the project on an event   | a patch: `Screens::extend('events.form', [...])`, printed with `$event->extra('<key>')`         |
| No visible breadcrumbs               | `WEBX_EVENTS_BREADCRUMBS=false` (the BreadcrumbList in `<head>` is module-seo's and stays)      |
| Other words in the panel             | `php artisan vendor:publish --tag=webx-events-lang`                                             |
| A list of events on another page     | `events()` in the template, or a block type that calls it                                       |

The layout component's contract is two slots, `head` and the default one, plus `@stack('head')`
beside `{{ $head }}`; `php artisan webx:panel --sync` sets `layout` when the site has
`resources/views/components/layout.blade.php`.

A screen patch addresses nodes by `id`. Event editor: `event`, `when`, `all-day`, `dates`,
`starts-at`, `ends-at`, `date-note`, `where`, `attendance`, `venue`, `address`, `map-url`,
`booking`, `prices`, `price`, `price-amount`, `booking-url`, `photos`, `gallery`, `about`,
`description`, `highlights`, `settings`, `naming`, `title`, `slug`, `lead`, `taxonomy`,
`categories`, `services`, `project-fields`, `seo`, `history`, `versions`. Category editor:
`content`, `naming`, `title`, `slug`, `is-visible`, `lead`, `presentation`, `cover`,
`project-fields`, `seo`. Patch a project's fields into `project-fields`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-events` or copy it into the site. The rows
  above are the supported ways; if none fits, the package is missing a seam — say so.
- Do not leave the prefix empty to put events at the root: the package refuses to boot, because
  events would fight the pages over every address. Keep a prefix; give the index to a page.
- Do not edit a past event's date to repeat it: its page and history belong to that date. Use
  `events_duplicate` (or «Duplicate»), which makes a draft copy with the next free address.
- Do not send `blocks` to `events_update`: an event has no blocks, its page is the module's view.
  Write its fields; `events_get` lists them.
- Do not send dates without thinking about the zone: ISO 8601, and one without an offset is read
  in the site's timezone. An all-day event takes days (`"2026-10-12"`), not times.
- Do not save without the `revision` you read: a stale one is answered `409` with the event as
  it now is. Read again and redo the change.
- Do not delete rows with SQL: deleting puts the event in the bin and releases its address
  through the registry. A raw delete leaves the address and the links behind.
- Do not print `<title>` in a site's copy of a view: with an empty SEO card the event and the
  category name the page themselves (`seoFallback()` — the name through the title template, the
  lead, the cover or the picture), and the index is called by the section.
  A copy published before still has an `@if ($meta->title === null)` block and the `$seo` /
  `$meta` lines for it: delete them, they never print any more.

## Check your work

- `php artisan webx:doctor` — the layout and its `@stack('head')` among the rest.
- Open the event, its category, the index and `{prefix}/{slug}.ics` on the site; check an event
  with a date on https://validator.schema.org. A draft is seen through the editor's preview link.
- With MCP: read `events://catalog` first, then `events_get` by address; every mutating tool
  takes `dry_run: true`.

## Read more

- [README.md](README.md) in this directory — the page and its parts, dates, SEO, the card fields.
- Guide: https://webx-ui.github.io/webx-ui/guide/events
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_EVENTS.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
