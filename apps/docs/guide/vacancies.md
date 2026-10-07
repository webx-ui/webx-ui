# Vacancies

`@webx-ui/module-vacancies` is vacancies as two sections of the panel, and `webx-ui/module-vacancies`
on the server is what they edit and what prints them. This page is both, because neither is useful
alone.

A vacancy is who the organisation is looking for: the position, where the work is, the kinds of
employment, the salary in words and in numbers, a description, the duties, the requirements and what
is offered. Like an [event](/guide/events) and unlike a service or an article, its page is **not made
of blocks**: it has a fixed structure printed by the module's view, and a site changes how it looks
by publishing that view, one part at a time. The address is the registry of
[`webx-ui/routing`](/guide/routing), the draft and the history are `module-admin`, what a vacancy
says about itself — `JobPosting` included — is [`module-seo`](/guide/seo). The categories are the
panel's shared [categories](/guide/categories), flat and several per vacancy, but **without pages of
their own**: they are the groups and the filter of the careers page. The application form is a
[relation](/guide/relations) to a form of the [inbox](/guide/inbox).

## Install

```bash
pnpm add @webx-ui/module-vacancies
composer require webx-ui/module-vacancies
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { vacancies } from '@webx-ui/module-vacancies'
import '@webx-ui/module-vacancies/style.css'

createAdmin({
  modules: [...vacancies()],
})
```

`vacancies()` returns two modules — **Vacancies** and **Categories** — which arrive under one heading
because the server puts both in the `vacancies` group.

Without [`module-inbox`](/guide/inbox) there is no Application form field. Without
[`module-blocks`](/guide/blocks) there is no preview of a draft. Without [`module-pages`](/guide/pages)
nothing can stand at the prefix in place of the index. Everything else works.

Permissions: `vacancies.view` opens the list, `vacancies.manage` writes,
`vacancies.categories.manage` covers the categories.

## Addresses

| Type       | Address                                    |
| ---------- | ------------------------------------------ |
| `vacancy`  | `/careers/senior-php-developer`            |
| the index  | `/careers`, route `webx.vacancies.index`   |
| a filter   | `/careers?category=development`            |
| a category | none — it is a group of the index, no page |

`webx-vacancies.prefix` (`WEBX_VACANCIES_PREFIX`, `careers` by default) is the prefix, and **it is
never empty**: a flat list of vacancies beside the tree of pages would argue with it over every
address, so the package refuses to boot and says so. A slug another vacancy or a page already
answers at is refused under its own field. Changing the prefix later:

```bash
php artisan webx:routes:rebuild --type=vacancy
```

The old addresses stay behind as aliases that answer with a 301. A vacancy is a
[link source](/guide/menu), so it can stand in a menu.

### The index, or a page in its place

The index is the **open** vacancies **in groups by category**: the categories in their order, the
vacancies of each in the one order vacancies have. A vacancy in two categories stands in both
groups; the ones in no visible category are the last group, «Other vacancies»; an empty group is not
printed. Above the groups is a filter of links, `?category=<key>`, which leaves one group — a key
nobody has is an empty list and a 200, not a 404. The canonical address of a filtered index is the
index itself, and the index is an `ItemList` of what it shows. No vacancies at all is a page that
says «There are no open vacancies right now», not a 404.

There are no pages: the order is set by hand, and a site has dozens of vacancies, not thousands.

A site that wants more around the list — an introduction, the office, a general form — switches the
index off and puts a page there:

1. `WEBX_VACANCIES_INDEX=false` — the route is not registered, and `/careers` is free;
2. **Pages** → a new page with the slug `careers`;
3. write it, publish it, and print the vacancies in its view with
   [`vacancies()`](#in-a-template-vacancies).

The page takes the address, and the first step of every vacancy's breadcrumbs becomes that page,
named the way it names itself. While nothing is there — or only a draft — the trail has no such
step. Categories are never a step: they have no pages.

## Open and closed

A vacancy is **closed** when the hiring was closed by hand (**The hiring is closed**, `is_closed`) or
its last day (**Open until**, `valid_through`) is behind us. The last day is a **day**, not a
moment: a vacancy is open the whole of it in the application's timezone (`app.timezone`), wherever
the reader or the server is. One rule, one expression in the database and in PHP — the same on
sqlite, MariaDB and PostgreSQL.

| List                    | What is in it                         |
| ----------------------- | ------------------------------------- |
| the index               | the open ones only, in groups         |
| `vacancies()`           | the open ones                         |
| `vacancies()->closed()` | the closed ones — by hand and expired |
| `vacancies()->all()`    | every one                             |

**A closed vacancy leaves the lists, not the site.** Its page stays — job boards and old posts link
to it — with «This vacancy is closed», without the place for the form, **without `JobPosting` and
with `noindex`**, which also takes it out of the sitemap: Google asks exactly that of a posting that
is over. A rule an editor writes for that address in **SEO → Addresses** beats the `noindex`. An
expired vacancy is closed the same way and in the same words: a reader does not care why.

**Taking a vacancy off the site is something else**: it answers 404. Close a filled position; take
off one that should never have been there.

**Posted on** (`posted_at`, `datePosted` in the markup) is the day the vacancy was put up. The
first publication sets it when nobody has, and later publications leave it alone — change it by hand
when the hiring opens again.

## A vacancy's page

`vacancy.blade.php` is one view of parts, each its own `@include`, in this order:

| Part                  | What it prints                                                                 |
| --------------------- | ------------------------------------------------------------------------------ |
| `vacancy/heading`     | the position, «This vacancy is closed» when it is, the lead                    |
| `vacancy/facts`       | where, the employment in words, the salary, open until, the categories         |
| `vacancy/description` | the description, HTML as stored                                                |
| `vacancy/lists`       | «Duties», «Requirements», «What we offer» — the lines written in this language |
| `vacancy/apply`       | **empty** — the place for the application form; not drawn when closed          |

Where the work is: **On site** prints the city and the address, **Remote** prints «Remote»,
**Hybrid** prints the city and «remote work possible». A line of a list that is not written in the
language of the page is left out — a list half in a foreign language is worse than one line fewer.

```bash
php artisan vendor:publish --tag=webx-vacancies-views
```

copies every view into `resources/views/vendor/webx-vacancies`. **Keep only what you rewrite and
delete the rest**: a part that is not there falls through to the package's, and gets its fixes with
every update. `partials/card.blade.php` and `partials/group.blade.php` are the index's. What each
part of the page is handed:

| Variable                                | What it is                                                         |
| --------------------------------------- | ------------------------------------------------------------------ |
| `$vacancy`                              | the model — for `extra()` and anything else a site's part wants    |
| `$title`, `$lead`                       | in the language of the page                                        |
| `$closed`                               | whether it is closed, by hand or expired                           |
| `$workplace`, `$city`, `$address`       | `onsite`, `remote` or `hybrid`; the place is empty for remote work |
| `$employment`                           | the kinds of employment in words                                   |
| `$salary`                               | the editor's words, or the line made of the numbers                |
| `$valid_through`                        | the last day as the page prints it, or `''`                        |
| `$categories`                           | the visible categories' names                                      |
| `$description`                          | HTML as stored — the field type cleaned it on the way in           |
| `$duties`, `$requirements`, `$benefits` | the lines in this language                                         |
| `$form`                                 | the slug of the chosen application form, or `null`                 |

The views stand in the site's layout the way every module's do: `webx-vacancies.layout` names the
component, the same seam as the [blog's](/guide/blog#the-layout).

## The salary: words and numbers

**Salary** is words, per language, and it is what the page prints: «from ₴60,000 a month», «after
the interview», «$25–40 an hour».

**From**, **To**, **Per** and **Currency** are optional numbers — for search engines and for the
cards. They go into the markup's `baseSalary` when there is a currency, a unit and at least one
number: two numbers are `minValue` and `maxValue`, one is `value`. A vacancy with numbers and no
words prints a line made of them — «40 000–60 000 ₴ per month». A number without a unit is refused
under **Per**, **To** below **From** under **To**.

The currencies are the site's list, `webx-vacancies.currencies` — ISO 4217 code → the symbol a page
prints; the first is the currency of a new vacancy:

```php
// config/webx-vacancies.php
'currencies' => [
    'UAH' => '₴',
    'EUR' => '€',
],
```

A site that publishes the config keeps its **whole** list: the package's defaults are not merged
under it. A currency taken out of the list does not lock the vacancies that have it — they save as
they are and print the code in place of the symbol — but it cannot be chosen for another one.

**Country** (`country`, `UA`, `PL`) is only for the markup: the page prints the city. A new vacancy
takes `webx-vacancies.country` (`WEBX_VACANCIES_COUNTRY`). Two countries on one site are simply two
vacancies with different codes.

Nobody types a `<title>` into a view. With an empty SEO card a vacancy names its page itself: the
name through the site's title template, the lead as the description, and, with no picture of its
own, the site's default social image. The index is called by the section's name the same way. A copy
of a view published before this still has an `@if ($meta->title === null)` block and the `$seo` /
`$meta` lines for it — delete them, they never print any more.

## The JobPosting markup, and how to check it

An **open** vacancy describes itself as a schema.org `JobPosting`; a closed one has none.

| Property                                            | From                                                                   |
| --------------------------------------------------- | ---------------------------------------------------------------------- |
| `title`                                             | the position                                                           |
| `description`                                       | the description and the three lists under their headings, as HTML      |
| `responsibilities`, `qualifications`, `jobBenefits` | the three lists, a line each                                           |
| `datePosted`                                        | **Posted on**                                                          |
| `validThrough`                                      | the end of the last day, with the site's offset                        |
| `employmentType`                                    | the kinds of employment, Google's codes                                |
| `hiringOrganization`                                | the site's `Organization` from the SEO settings, by `@id`              |
| `jobLocation`                                       | a `Place` with the city, the address and the country — on site, hybrid |
| `jobLocationType`, `applicantLocationRequirements`  | `TELECOMMUTE` and the country — remote, hybrid                         |
| `baseSalary`                                        | the numbers, the unit and the currency                                 |

**Without an organisation there is no markup at all**: Google refuses a posting without
`hiringOrganization`, and a page is better off with none than with half of one. Fill **Settings → SEO
→ Organization** first. The trail is a `BreadcrumbList`.

Check an open vacancy in both:

- https://validator.schema.org — is the markup valid;
- the [Rich Results Test](https://search.google.com/test/rich-results) — is it a job posting to
  Google. Unlike the FAQ, job postings are a rich result Google shows for any site, so the test
  should list **Job postings**.

And a closed one in the page's source: no `JobPosting`, `<meta name="robots" content="noindex…">`,
not in `/sitemap.xml`.

## The application form

**Application form** on the Settings tab chooses a form of [`module-inbox`](/guide/inbox) — one, from
a picker with a search. Choosing needs no permission of the inbox: the names of forms are no secret,
and whoever writes vacancies need not read the submissions. A form that is switched off stays chosen
and is marked in the picker; a form that is deleted takes the choice with it. Like everything else,
the choice waits in the draft and goes on the site with **Publish**.

**The package does not print the form yet.** The page and every card hand over `$form` — the slug of
the chosen form when it is switched on, or `null` — and a site prints it in its own `vacancy/apply`.
Publish the views, keep only `resources/views/vendor/webx-vacancies/vacancy/apply.blade.php`, and
write:

```blade
@if ($form)
    <section class="wx-vacancy__apply" id="apply">
        <h2>{{ __('Apply') }}</h2>
        <x-webx-inbox::form :slug="$form" :values="['vacancy' => $title]" />
    </section>
@endif
```

The part is not drawn at all on a closed vacancy. One form usually serves every vacancy, so a
submission says which one it answered only if the form has a place for it: a **hidden** field named
`vacancy`, which `:values` fills with the position — shown as a column of the submissions when it is
marked for the table. The demo form has one.

Without `module-inbox` the field is not on the screen, `form` is not in the values, a save that names
one does not fail, and the rest works as it does anywhere.

## Duplicate

«The same position in Lviv» is a copy, not a vacancy from nothing. **Duplicate**, in the row menu and
in the editor's bar, makes a draft with every field, the categories, the application form and the SEO
card, the same title, and the address with the next free `-2`, `-3` in every language. It stands
right after the original. The copy is not closed, has no **Posted on** and no history, and is not on
the site: change the city, publish it. One transaction — a copy refused half way leaves nothing
behind.

## In a template: `vacancies()`

```blade
@foreach (vacancies()->in('development')->take(3) as $vacancy)
    <a href="{{ $vacancy['url'] }}">{{ $vacancy['title'] }}</a> · {{ $vacancy['city'] }}
@endforeach
```

| Step               | What it does                                                  |
| ------------------ | ------------------------------------------------------------- |
| `open()`           | The open ones — the default                                   |
| `closed()`         | The closed ones, by hand and expired                          |
| `all()`            | Every one                                                     |
| `in($categories)`  | An id, a key, a category or a list; empty — all               |
| `only([12, 7])`    | These and no others, in this order                            |
| `except($vacancy)` | «Other vacancies» on a vacancy's page                         |
| `take(6)`          | At most six; null or zero — all                               |
| `locale('uk')`     | The language of the cards; by default the one being rendered  |
| `get()`, `first()` | A list of cards, or one; the query can be looped and counted  |
| `groups()`         | The index's catalogue by category — `take()` counts per group |

A card is plain data:

```php
[
    'id' => 12,
    'url' => 'https://example.com/careers/senior-php-developer',
    'title' => 'Senior PHP developer',
    'lead' => '…',
    'workplace' => 'hybrid',
    'city' => 'Kyiv', 'address' => '',
    'employment_types' => ['FULL_TIME'],
    'employment' => ['Full-time'],            // in words, in the language of the card
    'salary' => 'from $3,000',                // the editor's words; '' when none
    'salary_range' => ['min' => 3000.0, 'max' => null, 'unit' => 'MONTH',
                       'currency' => 'USD', 'symbol' => '$'],   // or null
    'valid_through' => '2026-11-30',          // or null
    'posted_at' => '2026-09-28',
    'closed' => false,
    'categories' => [3, 5],
    'category_names' => ['Development', 'Remote'],
    'form' => 'job-application',              // the switched-on form's slug, or null
    'fields' => ['recruiter' => 'Olena'],     // the project's fields by name
]
```

`groups()` is `[{ id, slug, title, vacancies }]`; the last group, «Other vacancies», has
`id: null` and `slug: null`, and is not there when the query is narrowed to categories.
`php artisan webx:doctor` says whether `vacancies()` is there.

### The careers page of the site

With the index switched off and a page at `/careers`, the page's view of the site can print the
vacancies under its content — here in groups, with the key of each group as an anchor:

```blade
@php($groups = vacancies()->groups())

@if ($groups === [])
    <p>{{ __('There are no open vacancies right now.') }}</p>
@endif

@foreach ($groups as $group)
    <section class="careers-group" @if ($group['slug']) id="{{ $group['slug'] }}" @endif>
        <h2>{{ $group['title'] }}</h2>
        <ul>
            @foreach ($group['vacancies'] as $vacancy)
                <li>
                    <a href="{{ $vacancy['url'] }}">{{ $vacancy['title'] }}</a>
                    <span>{{ $vacancy['workplace'] === 'remote' ? __('Remote') : $vacancy['city'] }}</span>
                    <span>{{ implode(', ', $vacancy['employment']) }}</span>
                    @if ($vacancy['salary'] !== '')
                        <strong>{{ $vacancy['salary'] }}</strong>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
```

## Fields of the project

An agency wants the recruiter's name on every vacancy. It is not a column of the package: the site
lays a patch over the screen, and whatever the screen draws that the model has no column for is kept
in `extra`.

`vacancies.form` and `vacancies.category-form` keep an empty card with the public id
`project-fields`. `resources/screens/vacancies.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "recruiter",
      "type": "wx-input",
      "name": "recruiter",
      "label": "Recruiter",
      "props": { "maxlength": 80, "placeholder": "Olena Kovalenko" }
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('vacancies.form', resource_path('screens/vacancies.form.json'));
}
```

The field appears on the **Settings** tab, is checked by its type on every save — the panel's and an
agent's — waits in the draft with the rest, and is copied by **Duplicate**. Printing it is the one
part you publish: `resources/views/vendor/webx-vacancies/vacancy/facts.blade.php`, with the
package's markup and one more pair:

```blade
@if ($recruiter = $vacancy->extra('recruiter'))
    <dt>Recruiter</dt>
    <dd>{{ $recruiter }}</dd>
@endif
```

On a card it is `$vacancy['fields']['recruiter']`.

## The panel

**Vacancies** is the whole list, no pages, with tabs: **Open** (the default) · **Closed** · **All** ·
**Bin**. A row is the position with the address; where — the city, «Remote», or the city and
«Hybrid»; the employment in words; «until» the last day; the categories as chips; a **Closed** or
**Expired** badge with the reason in its tip; the state and the row menu — open, duplicate, open on
the site, close or reopen the hiring, unpublish, to the bin. In **All** the closed ones are dimmed.
Behind the funnel: a category and a state; and words.

**The order is dragged by its handle** on **Open** and **All** with no search and no filter: a
vacancy has one place, the same in every group of the careers page. On **Open** the closed ones
between the open ones keep their places. **Closed** has no handles — a closed vacancy is on no list
of the site.

**Close the hiring** and **Reopen the hiring** in the row menu are a save and a publication in one
step, so they are offered only for a vacancy on the site with no edits waiting. Reopening an expired
vacancy clears its last day, or it would stay closed; one that has only expired is reopened by a new
**Open until** in the editor.

Four states, as for services: **draft**, **published**, **published with edits**, **unpublished**.

**The editor** is the screen `vacancies.form`, four tabs. **Vacancy** — **Where** (on site, remote or
hybrid; the city and the address, hidden for remote work; the country), **Terms** (the kinds of
employment; the salary in words; **From**, **To**, **Per**, **Currency**), the **Description**, and
**Duties**, **Requirements**, **What we offer** — lines, the same lines in every language, sorted by
hand. **Settings** — the position, the address with the prefix in front, the lead with a counter,
the categories, the application form, **The hiring is closed**, **Open until**, **Posted on**, the
project's card. **SEO** and **History** as for services. Everything — the categories, the form and
**The hiring is closed** included — waits in the draft and goes on the site with **Publish**. A save
over somebody else's is refused with a `409`.

**Open until** and **Posted on** are days, `YYYY-MM-DD` both ways: a browser in any timezone shows
and sends the same calendar date.

**Categories** — the name, the **key** (the slug: `?category=…` in the address of the filter), whether
it is on the site, the project's card. The key is made from the name and is one per language among
the categories of vacancies. A category refuses to go into the bin while vacancies are in it.

```
GET    /api/cms/vacancies                   state, category, status, q, trashed
POST   /api/cms/vacancies                   { title, slug? }
GET    /api/cms/vacancies/{id}              values, the revision, the prefix, a preview link
PUT    /api/cms/vacancies/{id}              the draft: { values, revision }
POST   /api/cms/vacancies/{id}/duplicate    the form of the copy
POST   /api/cms/vacancies/{id}/close        · /reopen — 409 with edits waiting or off the site
POST   /api/cms/vacancies/{id}/discard      · /publish · /unpublish · /restore
DELETE /api/cms/vacancies/{id}              to the bin
GET    /api/cms/vacancies/{id}/versions     · POST /versions/{n}/restore
POST   /api/cms/vacancies/reorder           { ids } — they take the places they hold, in this order

GET    /api/cms/vacancies/categories        the shared categories API
GET    /api/cms/relations/inbox-form        the picker of the application form
```

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the two sections are tools too:

| Tool                   | What it does                                                      |
| ---------------------- | ----------------------------------------------------------------- |
| `vacancies_list`       | The open ones (by default), the closed ones or all — or the bin   |
| `vacancies_get`        | One vacancy in full: the values, the revision, a preview link     |
| `vacancies_create`     | A new vacancy as a draft, in one transaction                      |
| `vacancies_update`     | The values into the draft, guarded by the revision                |
| `vacancies_duplicate`  | **Duplicate**: a draft copy with the next free address            |
| `vacancies_publish`    | The draft onto the site · `vacancies_unpublish` takes it off      |
| `vacancies_close`      | **Close the hiring** · `vacancies_reopen` opens it again          |
| `vacancies_delete`     | To the bin, and its address is released                           |
| `vacancies_reorder`    | The order: the named ones take their own places, in the new order |
| `vacancy_categories_*` | `list`, `create`, `update`, `delete`, `reorder`                   |

A vacancy is named by its id or its address (`"/careers/php-developer"`), a category by its id or its
key. A plain string in a translated field is the default language — also inside the lines of
`duties`, `requirements` and `benefits`, which take strings, maps of languages or `{ "text": … }`.
**Days are `YYYY-MM-DD`.** The kinds of employment and the unit are schema.org's codes, the currency
one of the site's — and a value outside its list is refused with the list, which the tools that write
also carry in their description. The application form is `form`: the slug or the id of a form
(`inbox_forms_list` names them), or `null` — and on a site without `module-inbox` the tools say
nothing about a form and refuse one.

Before writing, an agent reads **`vacancies://catalog`**: every category in its order with its key
and its open vacancies — drafts included, each with its address, where the work is, the city,
`written_in` (the languages it has a title in) and its form — and how many of its vacancies are
closed; the vacancies in no category; the site's currencies and its country. The same position in
another city is `vacancies_duplicate`, not a second `vacancies_create`.

## Demo content

`php artisan webx:demo` seeds three categories — development, sales, support — and seven vacancies in
the two languages of the demo, as far as the site has them. Each shows one rule: an office job with a
monthly range in hryvnias; a remote contract paid by the hour in dollars, written in English only and
in no category — so «Other vacancies» shows; a hybrid part-time job with its salary in words only;
one in two categories; one closed by hand; one whose last day was the day before yesterday; one
draft. **The days are counted from the moment of seeding**, so the demo does not close itself a month
later.

With `module-inbox`, the demo also makes the form «Job application» — name, email, phone, a CV (PDF
or Word), a cover letter and a hidden `vacancy` — and chooses it in the open vacancies; a form with that slug already on the
site is chosen instead, and left alone by `--remove`. `--remove` takes the rest back out. Vacancies or
categories that already exist leave the demo alone.

## Config

`config/webx-vacancies.php`:

| Key           | Default              | What it is                                           |
| ------------- | -------------------- | ---------------------------------------------------- |
| `prefix`      | `careers`            | The first segment of every address; never empty      |
| `index`       | `true`               | The package answers the prefix with its own list     |
| `country`     |                      | ISO 3166-1 alpha-2 of a new vacancy, for the markup  |
| `currencies`  | USD, EUR, UAH, PLN   | ISO 4217 code → symbol; the first is a new vacancy's |
| `views.*`     |                      | The views the index and a vacancy are printed with   |
| `layout`      |                      | The Blade component those views stand in             |
| `breadcrumbs` | `true`               | The package views print the visible trail            |
| `middleware`  | `web`, `webx.locale` | What the public routes run through                   |
