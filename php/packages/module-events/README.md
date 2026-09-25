# webx-ui/module-events

Events as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: workshops,
meetings and webinars with a date, a place, a price and a link to book — a page of fixed structure
per event, flat categories that are pages of the site, related services, an `.ics` file and
schema.org `Event`.

The address is `webx-ui/routing`, the draft, the history, the categories and the relations are
`webx-ui/module-admin`, what a page says about itself is `webx-ui/module-seo`, the photos are
`webx-ui/module-media`, the languages are `webx-ui/localization`. What this package adds is the
event and its page.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-media`, `webx-ui/module-seo`, `webx-ui/routing`,
  `webx-ui/localization`, `webx-ui/mcp`
- Optional: `webx-ui/module-services` (the Services field), `webx-ui/module-blocks` (the preview
  of a draft), `webx-ui/module-pages` (a page at the prefix)

## Install

```bash
composer require webx-ui/module-events
php artisan migrate
```

Permissions: `events.view`, `events.manage`, `events.categories.manage`. The currency of the
prices in the markup is `WEBX_EVENTS_CURRENCY` (`EUR`, `HKD`); without it the markup has no price.

## Addresses

Two types in the registry, **on one level** under one prefix:

| Type             | Example                         |
| ---------------- | ------------------------------- |
| `event`          | `events/spring-cooking-class`   |
| `event-category` | `events/cooking-demonstrations` |

The prefix is `webx-events.prefix` (`WEBX_EVENTS_PREFIX`, `events` by default) and is **never
empty**: events at the root of the site would argue with the pages over every address, so the
package refuses to boot. A clash between an event and a category is an error under the slug
(`OnConflict::Fail`). Changing the prefix later:

```bash
php artisan webx:routes:rebuild --type=event --type=event-category
```

**The index** is a route at the prefix, `webx.events.index`, while `webx-events.index` is on.
Switched off (`WEBX_EVENTS_INDEX=false`), the route is not registered and the address is free: a
page of `module-pages` with the slug `events` takes it and becomes the first step of every event's
trail.

**The calendar file** is `{prefix}/{slug}.ics` (`webx.events.ics`) — one `VEVENT`, for a visible
event with a date; anything else is a 404. It stays when the index is off.

## To come and over

An event is **over** when its end — its start where it names none — is behind us. An event without
a start is one whose date is still to be settled ("every Saturday", "dates to be announced" in
**Date in words**): it is always among the ones to come, first of them, and never over.

The index and a category page list **only the events to come**, from the nearest,
`webx-events.per-page` (24) to a page. The past ones keep their pages — with a note that the event
is over and no button to book — and stay in the sitemap; a site prints them where it wants them,
with `events()->past()`.

An **event of days** (All day) covers them whole: from the midnight of its first day to the end of
its last. The page prints the dates without a time, the markup gives days, the `.ics` file
`VALUE=DATE` with the day after the last as its end.

## The page

`event.blade.php` is one view of parts, each its own `@include`:

```
event/gallery · event/heading · event/facts · event/booking · event/description ·
event/highlights · event/services
```

Publish the views and keep only the part you rewrite — the rest falls through to the package:

```bash
php artisan vendor:publish --tag=webx-events-views
```

What every part is handed is listed in `Rendering\EventPage`. A field of the project (a group
size, a line above the booking button) is a patch on `events.form` and a line in the part that
prints it: `{{ $event->extra('group-size') }}`.

**The list** — `partials/list.blade.php` with `partials/card.blade.php` — is one fragment for the
index and a category page.

## Draft

Everything the editor chooses waits in the draft and goes on the site with "Publish" — the text,
the dates, and also the categories and the services. **Duplicate** makes the next event of a
series: a draft with every field, category, service and the SEO card of this one, the address with
the next free `-2`, `-3` in every language, and no history.

## SEO

The SEO card on the event and the category (a patch from `module-seo`), the sitemap, `hreflang`,
the trail, and schema.org `Event` for an event with a date: `startDate`/`endDate` with the offset
(days alone for an event of days), `eventAttendanceMode` and a `Place` or a `VirtualLocation` by
how people attend, the gallery as `image`, the site's organisation as `organizer`, and an `Offer`
with the booking link and — with the site's currency — the price as a number. A price of zero is
`isAccessibleForFree`. The price on the page is words, as the editor wrote them.

The index and a category page are an `ItemList`. Check an event on https://validator.schema.org
and in the Rich Results Test — Google shows events to any site.

## In a template

```blade
@foreach (events()->take(3) as $event)
    <a href="{{ $event['url'] }}">{{ $event['title'] }}</a> — {{ $event['when'] }}
@endforeach

events()->past()->take(6)
events()->in('cooking-classes')
events()->relatedTo('service', $service)
events()->all()
```

A card: `id`, `url`, `title`, `lead`, `cover`, `gallery`, `starts_at`, `ends_at` (ISO 8601),
`all_day`, `when` (the date in words), `past`, `attendance`, `venue`, `price`, `booking_url`,
`ics_url`, `categories` (ids), `category_links`, `fields`.

## For an agent: MCP

With the panel's MCP server on, the two sections are tools behind `events:read` / `events:write`
and the categories' scopes:

| Tool                 | What it does                                                                         |
| -------------------- | ------------------------------------------------------------------------------------ |
| `events_list`        | The events to come (by default), the past ones or all, a page at a time — or the bin |
| `events_get`         | One event in full: the values, the revision, a preview link                          |
| `events_create`      | A new event as a draft, the row and its values in one transaction                    |
| `events_update`      | The values into the draft, guarded by the revision, in one transaction               |
| `events_duplicate`   | The panel's "Duplicate": a draft copy with the next free `-2`, `-3` address          |
| `events_publish`     | The draft onto the site · `events_unpublish` takes it off                            |
| `events_delete`      | To the bin, and its address is released                                              |
| `event_categories_*` | `list`, `create`, `update`, `delete`, `reorder`                                      |

An event is named by its id or its address, a service in `services` the same way, a category by
its id or its slug. **Dates are ISO 8601**; one without an offset is read in the site's timezone,
and the tools that write name that zone in their description, so an agent does not guess. An event
of days takes the first and the last day (`"2026-10-12"`, `"2026-10-14"`). Read `events://catalog`
first: every category with its events to come — drafts included, `when` as the site prints it —
and a count of the past ones, the events in no category, the timezone and the currency.

## Demo content

`php artisan webx:demo` seeds three categories — breakfast meetups, cooking classes, private
events — and six events (English and Russian, as far as the site has them), each showing one rule:
one next week with its hours and a price as a number, one of three whole days in two categories,
one online and free, one with no date and "Every Saturday, 9:00" instead, one a month ago with
photos — its report — and one draft. **The dates are counted from the moment of seeding**, so the
demo does not drift into the past. With `module-services` two events are linked to the demo
services. `--remove` takes it back out; a site that already has events is left alone.

## License

MIT
