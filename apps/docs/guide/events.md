# Events

`@webx-ui/module-events` is events as two sections of the panel, and `webx-ui/module-events` on the
server is what they edit and what prints them. This page is both, because neither is useful alone.

An event is a workshop, a meeting, a webinar: a date and a time, a place, a price and a link to book.
Like a [recipe](/guide/recipes) and unlike a service or an article, its page is **not made of
blocks**: it has a fixed structure printed by the module's view, and a site changes how it looks by
publishing that view, one part at a time. The address is the registry of
[`webx-ui/routing`](/guide/routing), the draft and the history are `module-admin`, what an event says
about itself is [`module-seo`](/guide/seo), the photos are [`module-media`](/guide/media). The
categories are the panel's shared [categories](/guide/categories) — flat, several per event, each a
page of the site. The services an event belongs to are a [relation](/guide/relations).

## Install

```bash
pnpm add @webx-ui/module-events
composer require webx-ui/module-events
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { events } from '@webx-ui/module-events'
import '@webx-ui/module-events/style.css'

createAdmin({
  modules: [...events()],
})
```

`events()` returns two modules — **Events** and **Categories** — which arrive under one heading
because the server puts both in the `events` group.

Without [`module-services`](/guide/services) there is no Services field and no services on an
event's page. Without [`module-blocks`](/guide/blocks) there is no preview of a draft. Without
[`module-pages`](/guide/pages) nothing can stand at the prefix in place of the index. Everything
else works.

Permissions: `events.view` opens the list, `events.manage` writes, `events.categories.manage` covers
the categories.

## Addresses

An event and a category live **on one level**, under one prefix:

| Type             | Address                                               |
| ---------------- | ----------------------------------------------------- |
| `event`          | `/events/spring-cooking-class`                        |
| `event-category` | `/events/cooking-classes`                             |
| the index        | `/events`, route `webx.events.index`                  |
| a calendar file  | `/events/spring-cooking-class.ics`, `webx.events.ics` |

An event in two categories has one address. Their slugs share the level: a category and an event
that want the same slug are refused, the second one under its own field.

`webx-events.prefix` (`WEBX_EVENTS_PREFIX`) is the prefix, and **it is never empty**: a flat list of
events beside the tree of pages would argue with it over every address, so the package refuses to
boot and says so. Changing the prefix later:

```bash
php artisan webx:routes:rebuild --type=event --type=event-category
```

The old addresses stay behind as aliases that answer with a 301.

### The index, or a page in its place

The index is the events to come, 24 to a page (`webx-events.per-page`), and the categories as links.
A site that wants more around it — an introduction, the past events below, a form — switches the
index off and puts a page there:

1. `WEBX_EVENTS_INDEX=false` — the route is not registered, and the address `/events` is free;
2. **Pages** → a new page with the slug `events`;
3. write it, and publish.

The page takes the address, and the first step of every event's and every category's breadcrumbs
becomes that page, named the way it names itself. While nothing is there — or only a draft — the
trail has no such step. The calendar files stay: `webx.events.ics` does not depend on the index.

Blocks for events — «Upcoming events», «Past events» — are not there yet. Until they are, such a
page prints its events with [`events()`](#in-a-template-events) in a view of the site.

## To come, and over

An event is **over** when its end — its start, if it names no end — is behind us. One rule, one
expression in the database, the same on sqlite, MariaDB and PostgreSQL. An event that has begun and
not ended is still to come: somebody may be on the way to it.

The **date is optional**. An event without one is «dates to be announced» or «every Saturday» — the
words go into **Date in words** (`date_note`). It is always among the events to come, **first** of
them, and never over.

| List                       | What is in it                | Order                           |
| -------------------------- | ---------------------------- | ------------------------------- |
| the index, a category page | the events to come only      | no date first, then the nearest |
| `events()`                 | the events to come, as above | the same                        |
| `events()->past()`         | the events that are over     | the last first                  |
| `events()->all()`          | every one                    | no date first, then the latest  |

**The past ones leave the lists, not the site.** An event that is over keeps its page — people link
to it, and with its photos it is a report — with the line «This event is over» and no button to
book. It stays in the sitemap. There is nothing to do when an event is over: it moves by itself.

There is no order to drag. Events stand in the order of their dates, and a list in the panel is a
page at a time, because the past ones pile up for years.

## A date, printed

One helper prints a date for the page, the cards, the panel and the agents alike, so they never
disagree. Months are in the language of the page, time is `H:i`, the zone is the application's
(`app.timezone`):

| The event                     | Printed                                            |
| ----------------------------- | -------------------------------------------------- |
| has **Date in words**         | the words — over everything below                  |
| has no date                   | nothing                                            |
| one day, with hours           | 12 October 2026, 10:00–12:30                       |
| one day, with a start only    | 12 October 2026, 10:00                             |
| **All day**, one day          | 12 October 2026                                    |
| several days (all day or not) | 12–14 October 2026 · 30 September – 2 October 2026 |

The words override the printing, **not the data**: an event with a date and «every Saturday»
still sorts, goes out of date, gets a calendar file and markup by its date.

**All day** is an event of days: from the midnight of its first day to the last second of its last,
in the application's zone. The panel shows those days as the same calendar dates in every browser,
whatever zone it is in.

## An event's page

`event.blade.php` is one view of parts, each its own `@include`, in this order:

| Part                | What it prints                                                                      |
| ------------------- | ----------------------------------------------------------------------------------- |
| `event/gallery`     | the first picture large, the rest as a strip                                        |
| `event/heading`     | the title, «This event is over» when it is, the lead                                |
| `event/facts`       | when, where (the venue, the address, the map link; «Online»), the price, categories |
| `event/booking`     | «Book» to the booking link, «Add to calendar»; over — neither                       |
| `event/description` | the description, HTML as stored                                                     |
| `event/highlights`  | «What to expect»: the cards written in this language; none — no part                |
| `event/services`    | cards of the visible related services, with `module-services`                       |

The place is printed for an event people come to (**In person** or **Both**); an **Online** event
says «Online» instead, and has no link to a stream — that goes to the people who booked, not to
everyone.

```bash
php artisan vendor:publish --tag=webx-events-views
```

copies every view into `resources/views/vendor/webx-events`. **Keep only what you rewrite and delete
the rest**: a part that is not there falls through to the package's, and gets its fixes with every
update. `partials/list.blade.php` with `partials/card.blade.php` is the one fragment of the index and
a category page — rewrite it once and both change. What each part is handed:

| Variable                         | What it is                                                      |
| -------------------------------- | --------------------------------------------------------------- |
| `$event`                         | the model — for `extra()` and anything else a site's part wants |
| `$pictures`                      | the gallery, resolved; the first is the cover                   |
| `$title`, `$lead`                | in the language of the page                                     |
| `$when`, `$past`                 | the date as printed above, and whether the event is over        |
| `$online`                        | whether the event is online only                                |
| `$venue`, `$address`, `$map_url` | empty for an online event                                       |
| `$price`                         | the price in words                                              |
| `$booking_url`, `$ics_url`       | null when there is nothing to book or no date                   |
| `$categories`                    | `[{ id, title, url }]` — visible, with an address               |
| `$description`                   | HTML as stored — the field type cleaned it on the way in        |
| `$highlights`                    | `[{ title, text }]` in this language, empty cards left out      |
| `$services`                      | cards, as `services()` gives them                               |

The views stand in the site's layout the way every module's do: `webx-events.layout` names the
component, the same seam as the [blog's](/guide/blog#the-layout).

## The calendar file

«Add to calendar» on an event's page is `{address}.ics` — one `VEVENT` a phone or a desktop
calendar opens as it is: the id and the host as `UID`, the start and the end, the title, the lead as
the description, the venue and the address as the location, the page as `URL`. An event of days is
`VALUE=DATE`, and its end is the day **after** the last one, as RFC 5545 counts. Long lines are
folded at 75 octets and `,`, `;` and `\` are escaped — the test checks both, not the eye.

No date, not published, in the bin — the file answers 404, and the page has no link to it.

## The price: words and a number

**Price** is words, per language, and it is what the page prints: «HK$480 per person», «On request»,
«Free». **Price as a number** is optional and only for search engines: it goes into the markup's
`offers` with the site's one currency, `webx-events.currency` (`WEBX_EVENTS_CURRENCY`, `EUR`, `HKD`).
No number or no currency — no price in the markup. **Zero** is `isAccessibleForFree`.

## The Event markup, and how to check it

An event **with a date** describes itself as a schema.org `Event`; one without has none, because
`startDate` is required.

| Property               | From                                                              |
| ---------------------- | ----------------------------------------------------------------- |
| `name`, `description`  | the title, the lead                                               |
| `image`                | every picture of the gallery                                      |
| `startDate`, `endDate` | ISO 8601 with the offset; days only for an event of days          |
| `eventStatus`          | `EventScheduled`                                                  |
| `eventAttendanceMode`  | offline, online or mixed, from **Format**                         |
| `location`             | a `Place` (the venue, the address) and/or a `VirtualLocation`     |
| `offers`               | an `Offer` with the booking link, and the price with the currency |
| `organizer`            | the site's `Organization` from the SEO settings, by `@id`         |

The index and a category page are an `ItemList` of the events to come, the trail is
`BreadcrumbList`. Check an event in both:

- https://validator.schema.org — is the markup valid;
- the [Rich Results Test](https://search.google.com/test/rich-results) — is it an event to Google.

Events, like recipes, are a rich result Google shows for any site, so the Rich Results Test should
list **Events** with no errors. «Missing field `performer`» is a warning, not an error: the module
has no performer.

## Duplicate

A series — the same class every month — is not an entity here: every date is its own event, and
the next one starts as a copy of the last. **Duplicate**, in the row menu and in the editor's bar,
makes a draft with every field, the categories, the services and the SEO card of the event, the
same title, and the address with the next free `-2`, `-3` in every language. The copy has no history
and is not on the site: change its dates, publish it. One transaction — a copy refused half way
leaves nothing behind.

## Services and events

The field **Services** on an event is a [relation](/guide/relations) to the services of
`module-services`: the event's page prints the chosen services, the visible ones, in the order
chosen. The other way round is a template:

```blade
@foreach (events()->relatedTo('service', $service)->take(3) as $event)
    <a href="{{ $event['url'] }}">{{ $event['title'] }}</a> — {{ $event['when'] }}
@endforeach
```

Events are a relation target too (`event`), so reviews and questions can point at them later.

## In a template: `events()`

```blade
@foreach (events()->in('cooking-classes')->take(3) as $event)
    <a href="{{ $event['url'] }}">{{ $event['title'] }}</a> · {{ $event['when'] }}
@endforeach
```

| Step                      | What it does                                                 |
| ------------------------- | ------------------------------------------------------------ |
| `upcoming()`              | The events to come — the default                             |
| `past()`                  | The events that are over, the last first                     |
| `all()`                   | Every one                                                    |
| `in($categories)`         | An id, a slug, a category or a list; null or empty — all     |
| `relatedTo('service', …)` | Only the ones related to these services                      |
| `only([12, 7])`           | These and no others, in this order                           |
| `except($event)`          | «Other events» on an event's page                            |
| `take(6)`                 | At most six; null or zero — all                              |
| `locale('uk')`            | The language of the cards; by default the one being rendered |
| `get()`, `first()`        | A list of cards, or one; the query can be looped and counted |

A card is `id`, `url`, `title`, `lead`, `cover`, `gallery`, `starts_at`, `ends_at` (ISO 8601),
`all_day`, `when` (the date as printed), `past`, `attendance`, `venue`, `price`, `booking_url`,
`ics_url`, `categories` (ids), `category_links` and `fields` — the project's own fields by name.
`php artisan webx:doctor` says whether `events()` is there.

### «Past events» in a view of the site

The index lists only what is to come. The past ones — the reports — go where the site wants them;
with the index switched off and a page at `/events`, the page's view of the site can put them under
the content:

```blade
@php($past = events()->past()->take(6))

@if (! $past->isEmpty())
    <section class="past-events">
        <h2>{{ __('Past events') }}</h2>
        <ul>
            @foreach ($past as $event)
                <li>
                    <a href="{{ $event['url'] }}">
                        @if ($event['cover'])
                            <img src="{{ $event['cover']['url'] }}" alt="" loading="lazy">
                        @endif
                        {{ $event['title'] }}
                    </a>
                    <time datetime="{{ $event['starts_at'] }}">{{ $event['when'] }}</time>
                </li>
            @endforeach
        </ul>
    </section>
@endif
```

## Fields of the project

A studio wants «Group size» next to the price and a line above the booking button. Neither is a
column of the package: the site lays a patch over the screen, and whatever the screen draws that the
model has no column for is kept in `extra`.

`events.form` and `events.category-form` keep an empty card with the public id `project-fields`.
`resources/screens/events.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "group-size",
      "type": "wx-input",
      "name": "group-size",
      "label": "Group size",
      "localized": true,
      "props": { "maxlength": 60, "placeholder": "Up to 12 people" }
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('events.form', resource_path('screens/events.form.json'));
}
```

The field appears on the **Settings** tab, is checked by its type on every save — the panel's and
an agent's — waits in the draft with the rest, and is copied by **Duplicate**. Printing it is the one
part you publish: `resources/views/vendor/webx-events/event/facts.blade.php`, with the package's
markup and one more pair:

```blade
@if ($size = $event->extra('group-size'))
    <dt>Group</dt>
    <dd>{{ $size }}</dd>
@endif
```

`extra()` reads it in the language of the page, because the field is localized. On a card it is
`$event['fields']['group-size']`.

## The panel

**Events** is a page at a time, with tabs for **when**: **Upcoming** (the default) · **Past** ·
**All** · **Bin**. A row is the cover, the title with the address, when — the line the site prints,
with the exact moments in the tip — the categories as chips, the state and the row menu (open,
duplicate, unpublish, to the bin). In **All** the past ones are dimmed. Behind the funnel: a
category, a service (with `module-services`), a state; and words.

Four states, as for services: **draft**, **published**, **published with edits**, **unpublished**.

**The editor** is the screen `events.form`, four tabs. **Event** — **When** (the start and the end,
**All day** — which turns both into days — and **Date in words**), **Where** (**Format**; the venue,
the address and the map link, hidden for an online event), **Booking** (the price, the price as a
number, the booking link), the **Photos** (the first is the cover), the **Description**, and
**What to expect** — cards of a heading and a line, the same cards in every language. **Settings** —
the title, the address with the prefix in front, the lead with a counter, the categories, the
services, the project's card. **SEO** and **History** as for services. Everything — the categories
and the services included — waits in the draft and goes on the site with **Publish**. A save over
somebody else's is refused with a `409`; an end before the start is refused under **Ends**.

**Categories** — the name, the address, whether it is on the site, the introduction, the cover, the
SEO card, the project's card. A category refuses to go into the bin while events are in it.

```
GET    /api/cms/events                  when, category, service, status, q, trashed, page, per_page
POST   /api/cms/events                  { title, slug? }
GET    /api/cms/events/{id}             values, the revision, the prefix, a preview link
PUT    /api/cms/events/{id}             the draft: { values, revision }
POST   /api/cms/events/{id}/duplicate   the form of the copy
POST   /api/cms/events/{id}/discard     · /publish · /unpublish · /restore
DELETE /api/cms/events/{id}             to the bin
GET    /api/cms/events/{id}/versions    · POST /versions/{n}/restore

GET    /api/cms/events/categories       the shared categories API
```

The list is Laravel's `->paginate()` as it is. The dates go both ways with their offset
(`2026-10-12T10:00:00+08:00`) and are kept in the application's zone.

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the two sections are tools too:

| Tool                 | What it does                                                                         |
| -------------------- | ------------------------------------------------------------------------------------ |
| `events_list`        | The events to come (by default), the past ones or all, a page at a time — or the bin |
| `events_get`         | One event in full: the values, the revision, a preview link                          |
| `events_create`      | A new event as a draft, in one transaction                                           |
| `events_update`      | The values into the draft, guarded by the revision                                   |
| `events_duplicate`   | **Duplicate**: a draft copy with the next free address                               |
| `events_publish`     | The draft onto the site · `events_unpublish` takes it off                            |
| `events_delete`      | To the bin, and its address is released                                              |
| `event_categories_*` | `list`, `create`, `update`, `delete`, `reorder`                                      |

An event is named by its id or its address (`"/events/spring-cooking-class"`), a category by its id
or slug, a service in `services` by its id or address. A plain string in a translated field is the
default language — also inside the cards of `highlights`. **Dates are ISO 8601**: with an offset,
or without one, which is read in the application's zone — and the tools that write name that zone
in their description, so an agent does not have to guess. An event of days takes the first and the
last day, `"2026-10-12"` and `"2026-10-14"`.

Before writing, an agent reads **`events://catalog`**: every category in its order with its events
to come — drafts included, each with `when` as the site prints it, its address and `written_in`, the
languages it has a title in — and how many of its events are over; the events in no category; the
zone and the currency. The next date of an existing event is `events_duplicate`, not a second
`events_create`.

## Demo content

`php artisan webx:demo` seeds three categories — breakfast meetups, cooking classes, private
events — and six events in the two languages of the demo, as far as the site has them. Each shows
one rule: one a week from now with its hours and a price as a number; one of three whole days, in
two categories; one online and free; one without a date, «Every Saturday, 9:00» instead; one a month
ago with the photos of the library's demo — a report; one a draft. **The dates are counted from the
moment of seeding**, so the demo does not drift into the past a month later.

With `module-services`, two events are linked to the demo services. `--remove` takes all of it back
out. Events or categories that already exist leave the demo alone.

## Config

`config/webx-events.php`:

| Key           | Default  | What it is                                                    |
| ------------- | -------- | ------------------------------------------------------------- |
| `prefix`      | `events` | The first segment of every address; never empty               |
| `index`       | `true`   | The package answers the prefix with its own list              |
| `per-page`    | `24`     | Events on a page of the index and of a category               |
| `currency`    |          | ISO 4217 for the price in the markup; empty — no price there  |
| `views.*`     |          | The views the index, a category and an event are printed with |
| `layout`      |          | The Blade component those views stand in                      |
| `breadcrumbs` | `true`   | The package views print the visible trail                     |
| `middleware`  |          | What the public routes run through                            |
