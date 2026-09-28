# webx-ui/module-vacancies

Vacancies as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: open
positions of the organisation, each a page of fixed structure — where the work is, the kinds of
employment, the salary in words and in numbers, a description, duties, requirements and what is
offered — with schema.org `JobPosting`, flat categories as groups and a filter, closed vacancies,
and an application form chosen from `module-inbox`.

The address is `webx-ui/routing`, the draft, the history, the categories and the relations are
`webx-ui/module-admin`, what a page says about itself is `webx-ui/module-seo`, the languages are
`webx-ui/localization`. What this package adds is the vacancy and its page.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-seo`, `webx-ui/routing`, `webx-ui/localization`,
  `webx-ui/mcp`
- Optional: `webx-ui/module-inbox` (the Application form field), `webx-ui/module-blocks` (the
  preview of a draft), `webx-ui/module-pages` (a page at the prefix)

## Install

```bash
composer require webx-ui/module-vacancies
php artisan migrate
```

Permissions: `vacancies.view`, `vacancies.manage`, `vacancies.categories.manage`.

## Config

```php
// config/webx-vacancies.php
'prefix' => env('WEBX_VACANCIES_PREFIX', 'careers'),   // never empty
'index' => (bool) env('WEBX_VACANCIES_INDEX', true),
'country' => env('WEBX_VACANCIES_COUNTRY'),            // ISO alpha-2 of a new vacancy: 'UA'
'currencies' => ['USD' => '$', 'EUR' => '€', 'UAH' => '₴', 'PLN' => 'zł'],
'views' => ['index' => 'vacancies.index', 'vacancy' => 'vacancies.vacancy'],
'layout' => env('WEBX_VACANCIES_LAYOUT'),
'breadcrumbs' => (bool) env('WEBX_VACANCIES_BREADCRUMBS', true),
'middleware' => ['web', 'webx.locale'],
```

`currencies` is ISO 4217 code => the symbol a page prints; the first one is the currency of a new
vacancy. A site that publishes the config keeps its own list whole. A currency taken out of the
list does not lock the vacancies that have it — they save as they are and print the code — but it
cannot be chosen for another one.

## Addresses

One type in the registry, `vacancy`, under the prefix: `careers/senior-php-developer`. The prefix
is **never empty** — vacancies at the root would argue with the pages over every address, so the
package refuses to boot. A clash is an error under the slug (`OnConflict::Fail`). Changing the
prefix later:

```bash
php artisan webx:routes:rebuild --type=vacancy
```

**The index** is a route at the prefix, `webx.vacancies.index`: the open vacancies in groups by
category, in the order of the categories, each group in the order of the list; a vacancy in two
categories stands in both, the ones in no visible category are the last group, "Other vacancies".
Above the groups is a filter of links, `?category=<key>`, which leaves one group; a key nobody has
is an empty list, not a 404. The canonical is the index itself. Switched off
(`WEBX_VACANCIES_INDEX=false`), the route is not registered and a page of `module-pages` with the
slug `careers` takes the address and becomes the first step of every vacancy's trail.

**Categories have no addresses.** Their key (the slug) is kept all the same — made from the title,
one per language among the categories of vacancies — because it is the key of the filter and of
`vacancies()->in('development')`, and the address of a category page the day a site asks for one.

## Open and closed

A vacancy is **closed** when it is closed by hand (`is_closed`) or its last day (`valid_through`,
a date) is behind us — in the application's timezone: it is open the whole of its last day. One
expression for SQL and for php: the `open()` and `closed()` scopes and `isClosed()`;
`closedReason()` says `manual` or `expired`, `manual` when both are true.

A closed vacancy leaves the lists and `vacancies()`, **keeps its page** with a note, loses the
`JobPosting` and says `noindex` — so it leaves the sitemap by itself. A rule an editor writes in
`seo_urls` for its address beats that `noindex`. Taken off the site, it is a 404, which is
something else.

"Posted on" (`posted_at`) is set by the first publication when nobody has set it, and stays.

## The page

`vacancy.blade.php`, each part its own `@include` — publish and rewrite one part and leave the
rest:

| Part                  | What                                                                     |
| --------------------- | ------------------------------------------------------------------------ |
| `vacancy/heading`     | the position, the lead, "This vacancy is closed."                        |
| `vacancy/facts`       | where, employment, salary, open until, categories                        |
| `vacancy/description` | the description, HTML as stored                                          |
| `vacancy/lists`       | duties, requirements, what we offer — the lines written in this language |
| `vacancy/apply`       | **empty** — the place for the application form; not drawn when closed    |

The salary is the editor's words; without them, the numbers said — "40 000–60 000 ₴ per month".

```bash
php artisan vendor:publish --tag=webx-vacancies-views
```

## The application form

A form of `module-inbox` is chosen in the vacancy (a relation to `inbox-form`; no permission of the
inbox needed to choose one). The package does not print it yet: the page and the card hand over
`$form` — the slug of the chosen form when it is switched on, or `null` — and a site prints it in
its own `vacancy/apply`:

```blade
@if ($form)
    <x-webx-inbox::form :slug="$form" :values="['vacancy' => $title]" />
@endif
```

`:values` fills a hidden field named `vacancy`, if the form has one, so a submission says which
vacancy it answered.

Without `module-inbox` the field is not on the screen and the rest works as it does anywhere.

## SEO

The SEO card comes from `module-seo`. `JobPosting` on an open vacancy: `title`, `description` (the
description and the three lists), `responsibilities`, `qualifications`, `jobBenefits`,
`datePosted`, `validThrough` (the end of the last day with the site's offset), `employmentType`,
`hiringOrganization` (the organisation of the SEO settings — **no markup at all** without one),
`jobLocation` for on site and hybrid, `jobLocationType: TELECOMMUTE` with
`applicantLocationRequirements` for remote and hybrid, `baseSalary` when there is a currency, a
unit and a number. The index pushes an `ItemList`.

## In a template

```blade
@foreach (vacancies()->take(6) as $vacancy)
    <a href="{{ $vacancy['url'] }}">{{ $vacancy['title'] }}</a> — {{ $vacancy['city'] }}
@endforeach

@foreach (vacancies()->groups() as $group)
    <h2>{{ $group['title'] }}</h2> …
@endforeach
```

`open()` (the default), `closed()`, `all()`, `in($categories)` by id, key or model, `only([12, 7])`,
`except($vacancy)`, `take(6)`, `locale('uk')`, `get()`, `first()`, `groups()` — with `take()`
counted per group. A card is plain data: `id`, `url`, `title`, `lead`, `workplace`, `city`,
`address`, `employment_types`, `employment` (in words), `salary`, `salary_range` (`min`, `max`,
`unit`, `currency`, `symbol` — or null), `valid_through`, `posted_at`, `closed`, `categories`,
`category_names`, `form`, `fields` (the project's own fields).

## MCP

Eleven tools behind `vacancies:read` and `vacancies:write`, through the same doors as the panel —
the screen checks an agent's values, and a create, a save, a copy and closing are each one
transaction:

`vacancies_list` (`state`: `open` by default, `closed`, `all`; or the bin), `vacancies_get`,
`vacancies_create`, `vacancies_update`, `vacancies_duplicate`, `vacancies_publish`,
`vacancies_unpublish`, `vacancies_close`, `vacancies_reopen`, `vacancies_delete`,
`vacancies_reorder`; and `vacancy_categories_*` — the shared category tools.

A vacancy is its id or its address, a category its id or its key. Days are `YYYY-MM-DD`; the kinds
of employment and the unit are schema.org codes; the currency is one of the site's list, and a
value outside its list is refused with the list. `form` is the slug or id of an inbox form — and
without `module-inbox` the tools say nothing about a form and refuse one. `vacancies://catalog` is
what to read first: the categories with their keys and open vacancies, the closed ones counted, the
currencies and the country.

## Demo

`php artisan webx:demo` seeds three categories — development, sales, support — and seven vacancies,
each showing one rule (a monthly range in UAH, a remote contract by the hour in USD in English only
and in no category, a salary in words only, two categories, closed by hand, expired, a draft). The
days are counted from the moment of seeding. With `module-inbox` the demo makes the form
`job-application` — name, email, phone, a CV, a letter and a hidden `vacancy` — and chooses it in
the open vacancies; a form with that slug already there is chosen and left alone. `--remove` takes
the rest back out.

## License

MIT
