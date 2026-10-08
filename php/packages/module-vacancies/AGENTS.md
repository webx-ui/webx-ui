# webx-ui/module-vacancies

Open positions of the organisation, each a page of fixed structure — where the work is, the kinds
of employment, the salary in words and numbers, a description, duties, requirements and benefits
— with schema.org `JobPosting`, flat categories as groups and a filter, closing by hand or by
date, and an application form chosen from `webx-ui/module-inbox`. The section «Vacancies» of the
panel and the MCP tools `vacancies_*` edit it. The address is `webx-ui/routing`, the draft,
history and categories `webx-ui/module-admin`, the SEO card `webx-ui/module-seo`, the form
`webx-ui/module-inbox` — read their guides when the question is about one of those.

## What it owns

- **Tables** `vacancies` (`WebxUi\Vacancies\Models\Vacancy`), `vacancy_categories`
  (`WebxUi\Vacancies\Models\VacancyCategory`) and the link `vacancy_category_vacancy`.
- **Address type** `vacancy` under `config('webx-vacancies.prefix')` (default `careers`, never
  empty), `OnConflict::Fail`. Categories have no addresses: their slug is the key of the
  `?category=` filter and of `vacancies()->in()`.
- **Index route** `webx.vacancies.index` at the prefix, while `webx-vacancies.index` is on: the
  open vacancies grouped by category, with a filter of links.
- **Closed** = `is_closed` set by hand, or `valid_through` (a date) behind us. A closed vacancy
  leaves the lists, keeps its page with a note, loses `JobPosting` and says `noindex`. `posted_at`
  is set by the first publication when empty.
- **Public views** `config('webx-vacancies.views.index' | '.vacancy')`, defaults
  `vacancies.index`, `vacancies.vacancy`. The vacancy view is made of parts `vacancy/heading`,
  `vacancy/facts`, `vacancy/description`, `vacancy/lists`, `vacancy/apply` (empty on purpose).
- **Helper** `vacancies()` — `open()` by default, `closed()`, `all()`, `in()`, `groups()`.
- **Panel screens** `vacancies.form` (nodes include `tabs`, `vacancy`, `where`, `workplace`,
  `terms`, `employment-types`, `salary`, `about`, `description`, `duties`, `requirements`,
  `benefits`, `settings`, `naming`, `title`, `slug`, `lead`, `taxonomy`, `categories`, `form`,
  `hiring`, `is-closed`, `valid-through`, `posted-at`, `project-fields`, `seo`, `history`,
  `versions`) and `vacancies.category-form` (`naming`, `title`, `slug`, `is-visible`,
  `project-fields`). The `form` field is there only with `webx-ui/module-inbox`.
- **API** under `/api/cms/vacancies` and `/api/cms/vacancies/categories`; permissions
  `vacancies.view`, `vacancies.manage`, `vacancies.categories.manage`.
- **MCP** tools `vacancies_list`, `vacancies_get`, `vacancies_create`, `vacancies_update`,
  `vacancies_duplicate`, `vacancies_publish`, `vacancies_unpublish`, `vacancies_discard`, `vacancies_close`,
  `vacancies_reopen`, `vacancies_delete`, `vacancies_reorder`, and `vacancy_categories_list`,
  `_create`, `_update`, `_delete`, `_reorder`; resource `vacancies://catalog`. Scopes
  `vacancies:read`, `vacancies:write`, `vacancy-categories:read`, `vacancy-categories:write`.
- Also registered: a link source, the panel group `vacancies`, demo content (`resources/demo`).

## Change it without forking

| You want                                  | Do this                                                                                         |
| ----------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Vacancies inside the site's header/footer | `WEBX_VACANCIES_LAYOUT=layout` (`<x-layout>`, slots `head` and default, `@stack('head')` in it) |
| One part of the page different            | `php artisan vendor:publish --tag=webx-vacancies-views`, keep only the part you rewrite         |
| The application form on the page          | rewrite `vacancy/apply`: `<x-webx-inbox::form :slug="$form" :values="['vacancy' => $title]" />` |
| Whole pages of your own                   | `WEBX_VACANCIES_VIEW_INDEX`, `WEBX_VACANCIES_VIEW_VACANCY`                                      |
| Another first segment                     | `WEBX_VACANCIES_PREFIX`, then `php artisan webx:routes:rebuild --type=vacancy`                  |
| The index page built of blocks            | `WEBX_VACANCIES_INDEX=false` and a page with the slug of the prefix in `webx-ui/module-pages`   |
| The country of a new vacancy              | `WEBX_VACANCIES_COUNTRY` — ISO alpha-2                                                          |
| Another salary currency                   | `currencies` in `config/webx-vacancies.php` (ISO 4217 → symbol; the first is the default)       |
| No visible breadcrumbs                    | `WEBX_VACANCIES_BREADCRUMBS=false` (the BreadcrumbList in `<head>` stays, it is module-seo's)   |
| A field of the site's own                 | a patch: `Screens::extend('vacancies.form', [...])` into `project-fields`; read `extra`         |
| Vacancies in a template                   | `vacancies()->in('development')->take(6)`, or `vacancies()->groups()`                           |
| Other words in the panel or the site      | `php artisan vendor:publish --tag=webx-vacancies-lang`                                          |

All keys live in `config/webx-vacancies.php` (`vendor:publish --tag=webx-vacancies-config`). The
page and the card hand over `$form` — the slug of the chosen inbox form, or `null`. A screen patch
whose target is gone throws when the screen is first built.

## Do not

- Do not edit anything in `vendor/webx-ui/module-vacancies`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not set the prefix empty: vacancies at the root would fight the pages over every address, so
  the package refuses to boot. Change it with `webx:routes:rebuild`.
- Do not unpublish a vacancy to end hiring: that makes its page a 404. Close it
  (`vacancies_close`, or `is-closed` / `valid-through` in the editor) — the page stays with a note.
- Do not add `JobPosting` markup of your own: the package prints it for open vacancies only, and
  none at all while the SEO settings have no organisation. Fill those in instead.
- Do not send `blocks` to `vacancies_update`: a vacancy has no blocks, its page is the module's
  view. Write its fields.
- Do not write without the `revision` vacancies_get gave you: `vacancies_update` refuses a write
  with none and refuses a stale one. Read again and redo the change; do not retry blindly.
  `force: true` writes without one and is for a script that means to overwrite, not for an
  agent working beside people. `vacancies_get` names in `being_edited_by` who has the vacancy open
  in the panel right now — tell your user before writing under them. Their editor merges your
  write with theirs field by field, and a draft written over by somebody else is kept: the
  panel's History lists it under Drafts, and «Restore» puts it back.
- `vacancies_publish`, `vacancies_unpublish`, `vacancies_discard`, `vacancies_close`, `vacancies_reopen`, `vacancies_delete` act on whatever the draft holds now, so they take the `revision` too: a stale
  one is refused, and while somebody has the vacancy open in the panel (`being_edited_by`) one is
  required — the refusal names who. `force: true` goes ahead regardless; nobody there, no revision needed.
- Do not choose a currency outside `webx-vacancies.currencies`: it is refused with the list. Add
  it to the config first.
- Do not delete rows with SQL: deleting bins a vacancy; a raw delete leaves its address in the
  routing registry.
- Do not print `<title>` in a site's copy of a view: with an empty SEO card the vacancy names the
  page itself (`seoFallback()` — the name through the title template, the lead), and the index is
  called by the section.
  A copy published before still has an `@if ($meta->title === null)` block and the `$seo` /
  `$meta` lines for it: delete them, they never print any more.

## Check your work

- `php artisan webx:doctor` — the layout and its `@stack('head')`, and whose `vacancies()` it is.
- Open the vacancy on the site after publishing it; check `JobPosting` on
  https://validator.schema.org. A closed one should show the note and no markup.
- With MCP: read `vacancies://catalog` first (categories with their keys, currencies, country),
  then `vacancies_get`; every tool that changes something takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — open and closed, the page parts, the form, SEO.
- Guide: https://webx-ui.github.io/webx-ui/guide/vacancies
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_VACANCIES.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
