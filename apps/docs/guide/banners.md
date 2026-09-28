# Banners

`@webx-ui/module-banners` is the banners as a section of the panel, and `webx-ui/module-banners`
on the server is what it edits. This page is both, because neither is useful alone.

A banner is a picture (and, if the editor wants, a narrower one for phones), a video over it, a
title, a line or two of text and up to three buttons. Banners stand in **named places**, the way
menu items stand in named menus: a template of the site asks for `banners('hero')`, the editor puts
banners into `hero` and drags them into order. A banner has no page, the module has no public
route, and there is no block for banners: **the site's template draws them**, and the package hands
it data.

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
| `text`         | translatable plain text; the template prints it with its line breaks                                                      |
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

Everything a site gets from the module is two calls:

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

The package draws nothing. This is the whole of a working place — markup, styles and a script —
for a site to copy and change; it was checked against the demo banners on a real site, wide and at
375 px, in two languages. The three layouts share one piece of markup, and the script decides what
each one does.

::: code-group

```blade [partials/banners.blade.php]
@php($banners = banners($place)->get())
@php($layout = banners_layout($place))
@php($shown = $layout['layout'] === 'single' ? array_slice($banners, 0, 1) : $banners)

@if ($shown !== [])
  <section class="banners banners--{{ $layout['layout'] }}"
           style="--ratio: {{ $layout['ratio'] }}; --ratio-mobile: {{ $layout['ratio_mobile'] }}"
           data-banners='@json($layout)'>
    <div class="banners__track">
      @foreach ($shown as $banner)
        <article class="banners__slide" id="{{ $banner['anchor'] }}"
                 @if ($layout['layout'] === 'random' && ! $loop->first) hidden @endif>
          <picture>
            @if ($banner['image_mobile'])
              <source media="(max-width: {{ $layout['breakpoint'] - 1 }}px)"
                      srcset="{{ $banner['image_mobile']['url'] }}">
            @endif
            <img src="{{ $banner['image']['url'] }}" alt="{{ $banner['image']['alt'] ?? '' }}"
                 width="{{ $banner['image']['width'] }}" height="{{ $banner['image']['height'] }}"
                 loading="{{ $loop->first ? 'eager' : 'lazy' }}">
          </picture>

          @if ($banner['video'])
            <video muted playsinline loop preload="none" aria-hidden="true"
                   poster="{{ $banner['image']['url'] }}" data-src="{{ $banner['video']['url'] }}"></video>
          @endif

          @if ($banner['title'] !== '' || $banner['text'] !== '' || $banner['buttons'] !== [])
            <div class="banners__body">
              @if ($banner['title'] !== '')
                <h2 class="banners__title">{{ $banner['title'] }}</h2>
              @endif
              @if ($banner['text'] !== '')
                <p class="banners__text">{!! nl2br(e($banner['text'])) !!}</p>
              @endif
              @if ($banner['buttons'] !== [])
                <p class="banners__buttons">
                  @foreach ($banner['buttons'] as $button)
                    <a class="button button--{{ $button['variant'] }}" href="{{ $button['url'] }}"
                       @if ($button['new_tab']) target="_blank" @endif
                       @if ($button['rel']) rel="{{ $button['rel'] }}" @endif>{{ $button['label'] }}</a>
                  @endforeach
                </p>
              @endif
            </div>
          @endif
        </article>
      @endforeach
    </div>
  </section>
@endif
```

```css [banners.css]
.banners {
  position: relative;
}

.banners__track {
  display: flex;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.banners__track::-webkit-scrollbar {
  display: none;
}

.banners__slide {
  position: relative;
  display: flex;
  flex: 0 0 100%;
  align-items: flex-end;
  aspect-ratio: var(--ratio);
  overflow: hidden;
  scroll-snap-align: start;
}

.banners__slide[hidden] {
  display: none;
}

.banners__slide img,
.banners__slide video {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.banners__body {
  position: relative;
  width: 100%;
  padding: 2rem 4rem 3rem;
  color: #fff;
  background: linear-gradient(transparent, rgb(0 0 0 / 0.6));
}

.banners__title {
  margin: 0 0 0.5rem;
}

.banners__text {
  margin: 0 0 1rem;
}

.banners__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0;
}

.banners__arrow {
  position: absolute;
  top: 50%;
  translate: 0 -50%;
  width: 2.5rem;
  height: 2.5rem;
  border: 0;
  border-radius: 50%;
  background: rgb(255 255 255 / 0.85);
  cursor: pointer;
}

.banners__arrow--prev {
  left: 0.75rem;
}

.banners__arrow--next {
  right: 0.75rem;
}

.banners__dots {
  position: absolute;
  bottom: 0.75rem;
  left: 50%;
  display: flex;
  gap: 0.5rem;
  translate: -50% 0;
}

.banners__dot {
  width: 0.625rem;
  height: 0.625rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: rgb(255 255 255 / 0.5);
  cursor: pointer;
}

.banners__dot[aria-current='true'] {
  background: #fff;
}

/* The same number as `breakpoint` in the config: CSS cannot read it from the attribute. */
@media (max-width: 767px) {
  .banners__slide {
    aspect-ratio: var(--ratio-mobile);
  }

  .banners__body {
    padding: 1.5rem 1rem 2.5rem;
  }

  .banners__arrow {
    display: none;
  }
}
```

```js [banners.js]
// resources/js/banners.js — every place on the page, whatever its layout.
for (const root of document.querySelectorAll('[data-banners]')) {
  const options = JSON.parse(root.dataset.banners)
  const track = root.querySelector('.banners__track')
  const slides = [...root.querySelectorAll('.banners__slide')]
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches
  const wide = matchMedia(`(min-width: ${options.breakpoint}px)`).matches

  // The video only where it is wanted; everywhere else the picture under it is the poster.
  if (!still && (wide || options.video_on_mobile)) {
    for (const video of root.querySelectorAll('video[data-src]')) {
      video.src = video.dataset.src
      video.play().catch(() => {})
    }
  }

  // Random: every banner is printed, the first one shown; a script picks on every view.
  if (options.layout === 'random') {
    const pick = slides[Math.floor(Math.random() * slides.length)]
    for (const slide of slides) slide.hidden = slide !== pick
    continue
  }

  if (options.layout !== 'slider' || slides.length < 2) continue

  let current = 0
  let paused = false
  const dots = []

  const mark = () => {
    dots.forEach((dot, index) => dot.setAttribute('aria-current', String(index === current)))
  }

  const go = (index) => {
    const last = slides.length - 1
    current = options.loop
      ? (index + slides.length) % slides.length
      : Math.max(0, Math.min(index, last))
    track.scrollTo({ left: current * track.clientWidth, behavior: still ? 'auto' : 'smooth' })
    mark()
  }

  const button = (className, label, onClick) => {
    const element = document.createElement('button')
    element.type = 'button'
    element.className = className
    element.setAttribute('aria-label', label)
    element.addEventListener('click', onClick)
    return element
  }

  if (options.arrows) {
    root.append(
      button('banners__arrow banners__arrow--prev', 'Previous', () => go(current - 1)),
      button('banners__arrow banners__arrow--next', 'Next', () => go(current + 1)),
    )
  }

  if (options.dots) {
    const nav = document.createElement('div')
    nav.className = 'banners__dots'
    slides.forEach((slide, index) => {
      dots.push(nav.appendChild(button('banners__dot', `Slide ${index + 1}`, () => go(index))))
    })
    root.append(nav)
  }

  // A swipe moves the track by itself: the dots follow wherever it stopped. A track not laid
  // out yet is 0 wide, and 0 / 0 would leave no dot current at all.
  track.addEventListener(
    'scroll',
    () => {
      if (track.clientWidth === 0) return
      current = Math.round(track.scrollLeft / track.clientWidth)
      mark()
    },
    { passive: true },
  )
  mark()

  if (options.pause_on_hover) {
    root.addEventListener('mouseenter', () => (paused = true))
    root.addEventListener('mouseleave', () => (paused = false))
    root.addEventListener('focusin', () => (paused = true))
    root.addEventListener('focusout', () => (paused = false))
  }

  if (options.autoplay && !still) {
    const timer = setInterval(() => {
      if (paused) return
      if (!options.loop && current === slides.length - 1) return clearInterval(timer)
      go(current + 1)
    }, options.interval)
  }
}
```

:::

```blade
{{-- the home page --}}
@include('partials.banners', ['place' => 'hero'])
```

What it stands on:

- **The picture** is a `<picture>`: `image_mobile` under the breakpoint, `image` otherwise, with its
  `width` and `height` so the page does not jump. The first banner loads at once, the others
  `lazy`. The frame is `aspect-ratio` from `ratio` and `ratio_mobile`.
- **single** prints the first banner only.
- **random** prints them all, every one but the first `hidden`, and the script opens a random one.
  The choice is the browser's, not the server's: it is made on every view, even when the site
  caches the page's HTML, and the hidden pictures are `lazy`, so they are not loaded. Without
  JavaScript the first one is seen.
- **slider** is a strip with `scroll-snap`, so it swipes on a phone without any script at all. The
  script adds the arrows and the dots (none for a single banner — a slider of one is a picture),
  autoplay with the interval, pause on hover and on focus, and the loop. No autoplay under
  `prefers-reduced-motion`.
- **The video** has no `src` in the markup: the script gives it one on a wide screen (or when
  `video_on_mobile`) and not under `prefers-reduced-motion`. Everywhere else, and without
  JavaScript, the picture is what is seen, and the video's bytes are never fetched.
- **The breakpoint** is in two places: the config, which the markup and the script read, and the
  `@media` of the CSS, which cannot read an attribute. Change both.

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

## Why there is no block, no schedule and no component

All three were weighed and left out on purpose; each can come later without breaking anything here.

- **No block.** A place is part of the layout — the head of the home page, a strip above the
  footer — and a layout is written in the site's templates, not put on a page by an editor. A block
  would also need a second way to say «which place» inside `wx-collection`, where an empty choice
  means «everything» and for places it has to mean «nothing». When a site needs banners inside a
  page's content, that comes as a block of its own.
- **No schedule.** A banner is on while it is on. A start and an end date sound simple and are not:
  a site that caches its pages would show a banner for hours after its end. They come together with
  an answer to the cache.
- **No component.** The package ships no view and no Blade component: every site draws a hero its
  own way, and a component general enough for all of them would be the template above with twenty
  props. If sites turn out to copy it word for word, it becomes a component then.

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
