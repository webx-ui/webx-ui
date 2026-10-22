# Banners

`@webx-ui/module-banners` is the banners as a section of the panel, and `webx-ui/module-banners`
on the server is what it edits. This page is both, because neither is useful alone.

A banner is a picture (and, if the editor wants, a narrower one for phones), a video over it, a
title, a line or two of text and up to three buttons. Banners stand in **named places**, the way
menu items stand in named menus: a template of the site asks for `banners('hero')`, the editor puts
banners into `hero` and drags them into order. A banner has no page, the module has no public
route, and there is no block for banners: **the site's template prints a place** — with
`<x-webx-banners>`, or with markup of its own from the data the package hands it.

## Install

```bash
pnpm add @webx-ui/module-banners
composer require webx-ui/module-banners
php artisan migrate
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { banners } from '@webx-ui/module-banners'
import '@webx-ui/module-banners/style.css'

createAdmin({
  modules: [banners()],
})
```

`banners()` is one module and one entry of the menu, **Banners**, at the top level.

Permissions: `banners.view` opens the places and their banners, `banners.manage` writes — the
banners, their order and the places of somebody's own.

## Places

A place is what a template asks for, by its key. The site's config declares the ones its templates
use; an administrator can add places of their own in the panel for a template that asks for one.

```php
// config/webx-banners.php — php artisan vendor:publish --tag=webx-banners-config
'places' => [
    'hero' => [
        'title' => 'webx-banners::places.hero',   // a string, or a translation key
        'layout' => 'slider',
    ],
    'promo' => [
        'title' => 'webx-banners::places.promo',
        'layout' => 'single',
        'options' => ['ratio' => '3/1', 'ratio_mobile' => '3/2'],
    ],
],
```

- A **declared** place is in the panel from the first day, empty, with a lock: it cannot be renamed
  or deleted there — a template asks for it by this key. Its row in the database appears with its
  first banner; nothing is written when the site boots.
- A place **of somebody's own** is made in the panel with a key and a name, renamed there, and
  deleted there when it is empty — the bin counts, because deleting the place would take the banners
  in the bin with it.
- A place taken out of the config while it still has banners becomes somebody's own: it stays
  visible and can be emptied and deleted.
- A place that is neither in the config nor in the database is not an error: `banners('nowhere')`
  is an empty list, as `menu('nowhere')` is. A header without a banner is better than a white page.

## A banner

| Field          | What it is                                                                                                                |
| -------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `image`        | a picture from the library, kept as its key — **required**, the one field that is                                         |
| `image_mobile` | a narrower crop for phones, shown below the breakpoint instead of `image`                                                 |
| `video`        | a video from the library, played silently over the picture; the picture is its poster                                     |
| `title`        | translatable                                                                                                              |
| `text`         | translatable plain text; the template prints it with its line breaks; takes shortcodes                                    |
| `buttons`      | up to three: a translatable label, a [link](/guide/menu#an-item-points-at-one-of-three-things) and a look from the config |
| `enabled`      | the whole of «on the site»: no draft, no schedule; a new banner starts **off**                                            |

The picture is required because every layout stands on it: the frame's proportions, the poster of
the video and what is seen without JavaScript. A video without a picture is refused under `image`.

**A banner with words is seen only in their languages.** A banner whose title or text is written in
Russian is on the Russian pages; one written only in English is not, and there is no fallback
language — English on a Russian page's picture is worse than no picture. A banner with no words at
all (a picture and a button) is seen everywhere. A button without a label in the language is left
out there, and the banner stays.

Media are not translated; the `alt` and `title` of each picture are, as everywhere in the library.

## Two helpers

The data of a place is two calls — what the components print, and what a template of its own reads:

```php
banners('hero')->get();                // the cards of a place, in its order
banners(['hero', 'promo'])->take(3);   // places in the order named, then their order
banners()->only([5, 2]);               // these, in this order
banners('hero')->except($banner);
banners('hero')->locale('uk');         // by default, the language the page is drawn in
banners('hero')->first();              // one card, or null

banners_layout('hero');                // ['layout' => 'slider', 'interval' => 6000, …]
banners_layout('hero', 'random');      // the same place, another layout for this template
```

A card:

```php
[
    'id' => 5,
    'anchor' => 'banner-5',
    'place' => 'hero',
    'title' => 'Spring sale',             // in the page's language, else ''
    'text' => "…",                         // print with nl2br(e())
    'image' => ['url' => …, 'thumb' => …, 'width' => 1920, 'height' => 720, 'alt' => …, 'mime' => …],
    'image_mobile' => [ … ] | null,        // null — take image
    'video' => ['url' => …, 'mime' => 'video/mp4'] | null,
    'buttons' => [
        ['label' => 'Book now', 'url' => '/booking', 'new_tab' => false, 'rel' => null, 'variant' => 'primary'],
    ],
    'fields' => ['badge' => 'New'],        // the project's own fields, by name
]
```

- A button is left out when its link leads nowhere any more — the page is in the bin or unpublished:
  a button to a 404 is worse than no button. Its `url` already has the language prefix.
- `rel` already carries `noopener noreferrer` for a new tab: print it as it is.
- A banner whose picture is no longer in the library is left out, before `take()` counts — a hole in
  a slider is worse than one slide fewer.

`banners_layout()` merges the package's options, `webx-banners.options`, the place's `options` and
the second argument, in that order, and adds `layout` (`single`, `random` or `slider`; anything else
is the place's). Every key is read against the package's default, so a published config with one
option keeps the others. A key the package does not know is handed to the template as it is — a
site can add its own.

| Option            | Default | Meaning                                                   |
| ----------------- | ------- | --------------------------------------------------------- |
| `interval`        | `6000`  | ms between slides                                         |
| `autoplay`        | `true`  | off under `prefers-reduced-motion` regardless             |
| `loop`            | `true`  | after the last slide, the first                           |
| `arrows`, `dots`  | `true`  |                                                           |
| `pause_on_hover`  | `true`  | and on focus inside the slider                            |
| `ratio`           | `16/6`  | `aspect-ratio` of the frame on wide screens               |
| `ratio_mobile`    | `4/5`   | below the breakpoint                                      |
| `breakpoint`      | `768`   | px: below it `image_mobile`, `ratio_mobile`, and no video |
| `video_on_mobile` | `false` | below the breakpoint the poster rather than the video     |

## A template

`<x-webx-banners place="hero" />` prints a place whole, in its layout — see
[On the site: two components](#on-the-site-two-components). What it does is what a template of
the site's own should do too, if it draws a place itself from `banners()` and `banners_layout()`:

```blade
@php($layout = banners_layout($place))
@foreach (banners($place)->get() as $banner)
  <article id="{{ $banner['anchor'] }}" @if ($layout['layout'] !== 'slider' && ! $loop->first) hidden @endif>
    <picture>
      @if ($banner['image_mobile'])
        <source media="(max-width: {{ $layout['breakpoint'] - 1 }}px)" srcset="{{ $banner['image_mobile']['url'] }}">
      @endif
      <img src="{{ $banner['image']['url'] }}" alt="{{ $banner['image']['alt'] ?? '' }}"
           width="{{ $banner['image']['width'] }}" height="{{ $banner['image']['height'] }}"
           loading="{{ $loop->first ? 'eager' : 'lazy' }}">
    </picture>
    {{-- the video, the title, nl2br(e($banner['text'])), the buttons with their `rel` as it is --}}
  </article>
@endforeach
```

- **The picture** is a `<picture>`: `image_mobile` under the breakpoint, `image` otherwise, with its
  `width` and `height` so the page does not jump. The first banner loads at once, the others
  `lazy`. The frame is `aspect-ratio` from `ratio` and `ratio_mobile`.
- **single** prints the first banner only.
- **random** prints them all, every one but the first `hidden`, and a script opens a random one.
  The choice is the browser's, not the server's: it is made on every view, even when the site
  caches the page's HTML, and the hidden pictures are `lazy`, so they are not loaded. Without
  JavaScript the first one is seen.
- **slider** is a strip with `scroll-snap`, so it swipes on a phone without any script at all. The
  script adds the arrows and the dots (none for a single banner — a slider of one is a picture),
  autoplay with the interval, pause on hover and on focus, and the loop. No autoplay under
  `prefers-reduced-motion`.
- **The video** has no `src` in the markup (`data-src`): the script gives it one on a wide screen
  (or when `video_on_mobile`) and not under `prefers-reduced-motion`. Everywhere else, and without
  JavaScript, the picture is what is seen, and the video's bytes are never fetched.

## Layers

A place declared with `'layers' => true` gives its banners the **layer editor**: texts, pictures
and buttons placed on the picture by hand — a big title on the left, a figure with its caption on
the right, a round picture with a ring of words. `hero` is declared so.

```php
'places' => [
    'hero' => ['layout' => 'slider', 'layers' => true],
    // A layer may step out of the frame — below the banner, over the next block.
    'promo' => ['layout' => 'single', 'layers' => true, 'overflow' => true],
],
```

**Every length is a percent of the picture's width** — the position, the width of a layer, its
font size, its padding; `y` too, not of the height. A banner at 1920 and at 1024 pixels is one
composition, scaled as a picture is, with no script. Letter spacing is in `em`, rotation in
degrees.

**The layers stand on the picture, not on the frame.** The frame of a place has its `ratio` and
crops the picture (`cover`); the layers keep to the picture's own pixels whatever the crop. What
the frame cuts away, the editor shades.

**Two layouts.** The phone's picture (`image_mobile`) is usually another shape, and a size that
reads on a computer is a speck on a phone — so the position and the sizes are kept apart for the
computer and the phone; the look is shared. A layer without a phone layout uses the computer's.
The site switches at the place's `breakpoint`, by the window — the width `<picture>` switches its
source at.

| Layer     | What it is                                                                      |
| --------- | ------------------------------------------------------------------------------- |
| `text`    | words in a tag (`p`, `h2`, `h3`, `span`; a place may add `h1` under its `tags`) |
| `image`   | a picture from the library — a decoration with an empty `alt` takes no clicks   |
| `button`  | a link with its label and look, as a button of the banner has                   |
| `buttons` | the banner's own buttons, in a row — one such layer at most                     |

**The banner's fields are put in.** `{title}` and `{text}` in a layer's words become the
banner's title and text in the language of the page — so the words stay where they always were
(the form, `banners_update`, who sees a banner in which language), and a layer only places them. A
banner opened in a place with layers starts with three layers — `{title}`, `{text}` and the
banner's buttons — in a column on the left; delete any of them as any layer. Taking every layer
away is kept: the three are not laid back the next time.

**A layer's own words fall back.** Empty in the language of the page, a layer shows the site's
default language, else the first one written — a hole in a composition is worse than another
language. Whether a banner is seen in a language is still decided by its title and text alone.

**Motion.** An entrance (`fade`, `rise`, `slide-start`, `slide-end`, `zoom`) with a delay and a
duration plays when the banner's slide becomes the active one; a loop (`spin`, `float`) runs on
a decoration. Nothing moves for a reader who asked for less motion.

### On the site: two components

```blade
{{-- the whole first screen --}}
<x-webx-banners place="hero" />

{{-- a part of it: the theme's hero prints its own words, the banners stand in a column --}}
<section class="b-hero">
  <div class="b-hero__text">…</div>
  <x-webx-banners place="hero" class="b-hero__media" />
</section>

{{-- one banner the template chose itself --}}
<x-webx-banner :banner="banners('promo')->first()" />
```

`<x-webx-banners>` prints a place whole, in its layout: a slider is `<x-webx-slider
variant="hero">` of the widgets on a site with a theme, else a strip of its own that snaps, plays
and has arrows and dots; `single` is the first banner, `random` opens one at random. Its width is
its parent's and its height the place's `ratio`, so it is the whole first screen or one column of
it alike. An empty place prints nothing, not even the wrapper. `<x-webx-banner>` prints one card:
the frame, the picture, the video, the layers — and for a banner without layers its words and
buttons in a corner.

The CSS (`resources/css/layers.css`) comes with the first banner of the page, once; the panel's
canvas draws with the same file, so what the editor shows is what the site prints. Both views are
published under `webx-banners-views` for a theme to override.

### In the panel

The card «Layers» of a banner in such a place: the picture with the frame's crop shaded, the
layers on it, their list (the top one is in front) and the fields of the one selected. Click to
select, drag to move, the handles resize; arrows move by 0.05 and by 0.5 with Shift, Delete
removes, Ctrl+D duplicates, Ctrl+Z and Ctrl+Shift+Z undo and redo; Alt while dragging stops the
snapping to the edges and middles. The fonts and the palette are the theme's: a colour picked
from the palette follows the theme when it changes.

## Button looks

```php
'variants' => [
    'primary' => 'Primary',
    'secondary' => 'Secondary',
    'link' => 'Link',
],
```

The key is what the template turns into a class (`button--primary`); the name is the panel's, a
plain string or a translation key. A look taken out of the list does not lock a banner that has it:
the saved button keeps it and saves as it is, it only cannot be chosen for a new one — and the card
gives it the first look of the list, because a button matters more than its look.

## Why there is no block and no schedule

Both were weighed and left out on purpose; each can come later without breaking anything here.

- **No block.** A place is part of the layout — the head of the home page, a strip above the
  footer — and a layout is written in the site's templates, not put on a page by an editor. A block
  would also need a second way to say «which place» inside `wx-collection`, where an empty choice
  means «everything» and for places it has to mean «nothing». When a site needs banners inside a
  page's content, that comes as a block of its own.
- **No schedule.** A banner is on while it is on. A start and an end date sound simple and are not:
  a site that caches its pages would show a banner for hours after its end. They come together with
  an answer to the cache.
- **Layers only through the component.** A place without layers may be drawn by the site's own
  template — every site draws a hero its own way. A banner with layers is `<x-webx-banners>`: the
  geometry of the stage is one for every site, and the panel has to draw exactly what the site prints.

## Fields of the project

The editor is the described screen `banners.form`, so a project adds a field with a patch into the
card whose public id is `project-fields`. It is saved in `extra` and reaches the card under
`fields`:

```php
use WebxUi\Admin\Facades\Screens;

Screens::extend('banners.form', [[
    'op' => 'add',
    'target' => 'project-fields',
    'node' => ['id' => 'badge', 'type' => 'wx-input', 'name' => 'badge', 'label' => 'Badge', 'localized' => true],
]]);
```

```blade
@if ($banner['fields']['badge'] ?? null)
    <span class="banners__badge">{{ $banner['fields']['badge'] }}</span>
@endif
```

## The panel

**Banners** is the places and their banners side by side (`WxListDetail`), as menus are. On the
left, the places: name, key, how many banners, a lock on a declared one, and under the key how a
template asks for it — `banners('hero')`. **New place** makes one of your own; its row menu renames
and deletes it (deleting is off while the place has anything in it, the bin included). The open
place is in the address (`?place=hero`).

On the right, the banners of the place: a thumbnail, the title, a mark for a video, dimmed when it
is off; drag them into order by the grip, or move the grip with the keyboard. The row menu opens a
banner, turns it on or off and puts it in the bin; the bin is a switch above the list, with
**Restore** there. On a phone the banners slide over the places with their own «Back».

A banner opens as its own page, `/banners/{id}` (a new one is `/banners/new?place=hero`): the
place — a select above the form, because the list of places is alive — then the screen: pictures
and video, words, buttons, settings, the project's card. Moving a banner to another place puts it
last there. Save with the button or `Ctrl+S`; leaving with unsaved changes asks first.

```
GET    /api/cms/banners/places                 { data: [{ id|null, key, title, declared, layout, count, titles? }] }
POST   /api/cms/banners/places                 { key, title } → 201
PUT    /api/cms/banners/places/{key}           { title } — somebody's own only; languages merged
DELETE /api/cms/banners/places/{key}           somebody's own and empty; otherwise 422 with count
GET    /api/cms/banners/places/{key}/banners   ?trashed=1
POST   /api/cms/banners/places/{key}/banners   { values } → 201 { data: { banner, values } }
POST   /api/cms/banners/places/{key}/reorder   { ids } → 204
GET|PUT|DELETE /api/cms/banners/{id}           { values, place? }
POST   /api/cms/banners/{id}/restore
```

A refusal is a 422 under the field: `image` (no picture, or a video without one),
`image_mobile`/`video` (the wrong kind of file), `buttons` (more than three), `buttons.<n>.link` and
`buttons.<n>.variant` (row `n` as the editor sees the rows), `place`.

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the section is tools too:

| Tool                   | What it does                                                                     |
| ---------------------- | -------------------------------------------------------------------------------- |
| `banners_places`       | The places — declared and made — with their layout and count                     |
| `banners_place_create` | A place of your own                                                              |
| `banners_place_delete` | A place of your own, when it is empty — never a declared one                     |
| `banners_list`         | The banners of a place, or of all of them, in the order of the site — or the bin |
| `banners_get`          | One banner in full: every language, the media by key, the buttons                |
| `banners_create`       | A banner at the end of a place; off unless asked                                 |
| `banners_update`       | The values, or another place (the banner goes last there)                        |
| `banners_delete`       | To the bin                                                                       |
| `banners_reorder`      | The order of a place — the banners named first, the rest where they were         |

A place is named by its key, a banner by its id. Media are library keys (`"media/ab/cd/spring.jpg"`),
and a key the library does not have is refused. A plain string in `title`, `text` or a button's
`label` is the default language; `{ "en": "…", "ru": "…" }` is every language at once. A button is
`{ label, link, variant }`: `link` is an address (`"/booking"`) or an entity in the form
`menu_add_link` takes (`{ "target": "entity", "entity_type": "page", "entity_id": 3 }`), and a look
the site does not have is refused with the list of those it has. Every tool that changes something
takes `dry_run: true`. The values go through the same form as the panel's, in one transaction: a
refused create leaves neither a banner nor the row of a declared place.

Before writing, an agent reads **`banners://catalog`**: the looks and the layouts, and every place
with its banners in order — off ones included and marked — each with `written_in` (the languages of
its words) and `has_video`. It also says what an agent tends to look for in vain: there is no block
to put on a page — the site shows a place where its template asks for it.

## Demo content

`php artisan webx:demo` fills the two declared places with the library's demo pictures:

- `hero` — three banners that are on, with both pictures and buttons: one to a page of the site
  (the pages demo's, else the first page the site has), one to an address; one of the three has no
  Russian words and is not on the Russian pages. A fourth is **off**. The demo library has no
  video, so no banner carries one.
- `promo` — one banner with one button.

No page and no template is touched: the banners are seen where the site's template asks for their
place. `--remove` takes them back out, the rows of the two places too. A site that already has a
banner leaves the demo alone.

## What is deferred

- A block for banners inside a page's content, with its source for `wx-collection`.
- A link on the whole banner rather than on a button.
- A separate video for phones.
- A schedule, together with an answer to the cached page.
- Click and view statistics, A/B.
