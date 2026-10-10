# webx-ui/module-banners

Banners as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a picture
(and one for phones), a video, a title, a text and up to three buttons, kept in **named places**
the way menus are. A template of the site asks for a place — `banners('hero')` — and prints the
banners the way the site's design wants them.

The module has no public route, no page, no block and no view. What reaches the site is data:
the cards of `banners()` and the layout options of `banners_layout()`. The markup, the styles and
the slider's script are the site's own; a template is below, and a complete one with its styles
and script is in the guide: https://webx-ui.github.io/webx-ui/guide/banners.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-media`, `webx-ui/localization`, `webx-ui/mcp`
- `webx-ui/module-pages` for a button to point at a page — suggested, not required

## Install

```bash
composer require webx-ui/module-banners
php artisan migrate
```

Permissions: `banners.view`, `banners.manage`. The section is one entry of the panel's menu,
**Banners**, at the top level.

## Places

A place is what a template asks for by key. The configuration declares the ones the site's
templates use; they are in the panel from the first day, empty, and their row in `banner_places`
appears with their first banner. An administrator adds places of their own in the panel, renames
them and deletes them when they are empty — the bin counts. A declared place cannot be renamed or
deleted there: a template asks for it by its key.

```bash
php artisan vendor:publish --tag=webx-banners-config
```

```php
// config/webx-banners.php
'places' => [
    'hero' => ['title' => 'webx-banners::places.hero', 'layout' => 'slider'],
    'promo' => [
        'title' => 'webx-banners::places.promo',
        'layout' => 'single',
        'options' => ['ratio' => '3/1', 'ratio_mobile' => '3/2'],
    ],
    // Words only: the announcement bar of webx-ui/widgets prints its first banner.
    'notice' => ['title' => 'webx-banners::places.notice', 'layout' => 'single', 'image' => false],
],
```

A title is a plain string or a translation key. A banner needs a picture, except in a declared
place with `'image' => false`: a place of words only, such as `notice`, which
`<x-webx-notice-bar>` of [`webx-ui/widgets`](../widgets/README.md) shows above the header. A place asked for that does not exist is an empty
list, not an exception: a header without a banner is better than a white page.

## A banner

| Field          | Stored as                                                                       |
| -------------- | ------------------------------------------------------------------------------- |
| `image`        | the value of a `wx-media` field (images) — required, the one required field     |
| `image_mobile` | the same, shown below the breakpoint instead of `image`                         |
| `video`        | the value of a `wx-media` field (video); `image` is its poster and its fallback |
| `title`        | translatable                                                                    |
| `text`         | translatable, plain text                                                        |
| `buttons`      | up to three: `label` (translatable), `link` (a `wx-link` value), `variant`      |
| `enabled`      | off for a new banner — the first save of an unfinished one is not on the site   |
| `extra`        | the fields a project patched onto `banners.form`                                |

Media are not translated; words are. **A banner with words is shown only in the languages its
title or text is written in**; a banner with no words at all is shown everywhere. A button
without a label in a language is not shown in it, and a button to a page that is not on the site
now (a draft, the bin) is not shown at all.

## Two helpers

```php
banners('hero')->get();                // the cards of a place, in its order
banners(['hero', 'promo'])->take(3);   // places in the order named, then position
banners()->only([5, 2]);               // these, in this order
banners('hero')->except($banner);
banners('hero')->locale('uk');         // by default, the language the page is drawn in

banners_layout('hero');                // ['layout' => 'slider', 'interval' => 6000, …]
banners_layout('hero', 'random');      // the same place, another layout for this template
```

A card:

```php
[
    'id' => 5,
    'anchor' => 'banner-5',
    'place' => 'hero',
    'title' => 'Spring sale',            // in the page's language, else ''
    'text' => "…",                        // print with nl2br(e())
    'image' => ['url' => …, 'thumb' => …, 'width' => 1920, 'height' => 720, 'alt' => …, 'mime' => …],
    'image_mobile' => [ … ] | null,       // null — take image
    'video' => ['url' => …, 'mime' => 'video/mp4'] | null,
    'buttons' => [
        ['label' => 'Book now', 'url' => '/booking', 'new_tab' => false, 'rel' => null, 'variant' => 'primary'],
    ],
    'fields' => ['badge' => 'New'],       // the project's own fields, by name
]
```

`rel` already carries `noopener noreferrer` for a new tab. A variant that was taken out of the
config comes back as the first one of the config: a button outranks its look.

`banners_layout()` merges the package's options, `webx-banners.options`, the place's `options`
and the argument, in that order, and adds `layout` (`single`, `random` or `slider`). Every key is
read against the package's default, so a published config with one option keeps the others. A
key the package does not know is handed to the template as it is.

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

The package draws nothing; this is what a site copies and changes.

```blade
@php($banners = banners('hero')->get())
@php($layout = banners_layout('hero'))

@if ($banners !== [])
  <section class="hero hero--{{ $layout['layout'] }}"
           style="--ratio: {{ $layout['ratio'] }}; --ratio-mobile: {{ $layout['ratio_mobile'] }}"
           data-banners='@json($layout)'>
    @foreach ($banners as $banner)
      <article class="hero__slide" id="{{ $banner['anchor'] }}"
               @if ($layout['layout'] === 'random' && ! $loop->first) hidden @endif>
        <picture>
          @if ($banner['image_mobile'])
            <source media="(max-width: {{ $layout['breakpoint'] - 1 }}px)" srcset="{{ $banner['image_mobile']['url'] }}">
          @endif
          <img src="{{ $banner['image']['url'] }}" alt="{{ $banner['image']['alt'] ?? '' }}"
               width="{{ $banner['image']['width'] }}" height="{{ $banner['image']['height'] }}"
               loading="{{ $loop->first ? 'eager' : 'lazy' }}">
        </picture>

        @if ($banner['video'])
          <video muted playsinline loop preload="none" poster="{{ $banner['image']['url'] }}"
                 data-src="{{ $banner['video']['url'] }}"></video>
        @endif

        @if ($banner['title'] !== '')
          <h2>{{ $banner['title'] }}</h2>
        @endif
        @if ($banner['text'] !== '')
          <p>{!! nl2br(e($banner['text'])) !!}</p>
        @endif

        @foreach ($banner['buttons'] as $button)
          <a class="button button--{{ $button['variant'] }}" href="{{ $button['url'] }}"
             @if ($button['new_tab']) target="_blank" @endif
             @if ($button['rel']) rel="{{ $button['rel'] }}" @endif>{{ $button['label'] }}</a>
        @endforeach
      </article>
    @endforeach
  </section>
@endif
```

- **single** — print the first banner (`banners('promo')->first()`).
- **random** — print them all, every one but the first `hidden`, and let a script open a random
  one. Without JavaScript the first is seen; the hidden pictures are `lazy` and are not loaded. The
  choice is made on every view, even when the site caches the page's HTML.
- **slider** — a strip with `scroll-snap`, arrows, dots and autoplay read from `data-banners`,
  paused on hover and focus, no autoplay under `prefers-reduced-motion`; one banner — no arrows
  and no dots.
- **video** — the script sets `src` from `data-src` on wide screens (or when `video_on_mobile`)
  and not under `prefers-reduced-motion`. Without it, on a phone and with reduced motion, the
  picture is what is seen.

## Button variants

```php
'variants' => [
    'primary' => 'Primary',
    'secondary' => 'Secondary',
    'link' => 'Link',
],
```

The key is what a template turns into a class; the name is the panel's, a plain string or a
translation key. The list is laid onto the editor's select at boot. A variant taken out of the
list does not lock a banner that has it: the saved button keeps it and saves as it is, it only
cannot be chosen for a new one, and the site prints it with the first variant.

## The panel's API

Under `webx-admin.api_path` (`/api/cms`), behind `cms.auth`:

```
GET    banners/places                    { data: [{ id|null, key, title, declared, layout, count, titles? }] }
POST   banners/places                    { key, title } → 201 { data: place }
PUT    banners/places/{key}              { title }        only somebody's own place
DELETE banners/places/{key}              only somebody's own and empty; 422 with `count`
GET    banners/places/{key}/banners      ?trashed=1 → { data: [{ id, title, thumb, video, enabled, position, updated_at, deleted_at }] }
POST   banners/places/{key}/banners      { values } → 201 { data: { banner, values } }
POST   banners/places/{key}/reorder      { ids } → 204
GET|PUT|DELETE banners/{id}              { values, place? } → { data: { banner, values } }
POST   banners/{id}/restore
```

A refusal is a 422 under the field: `image` (no picture, or a video without one),
`image_mobile`/`video` (the wrong kind of file), `buttons` (more than three),
`buttons.<n>.link` and `buttons.<n>.variant` (row `n` as the editor sees it), `place`.

## Screens

The editor is the described screen `banners.form`, so a project adds a field with a patch — it is
saved in `extra` and handed to the template under `fields`:

```php
Screens::extend('banners.form', [[
    'op' => 'add',
    'target' => 'project-fields',
    'node' => ['id' => 'badge', 'type' => 'wx-input', 'name' => 'badge', 'label' => 'Badge', 'localized' => true],
]]);
```

## For an agent: MCP

Nine tools through the same doors as the panel, behind `banners:read` and `banners:write`:
`banners_places`, `banners_place_create`, `banners_place_delete`, `banners_list`, `banners_get`,
`banners_create`, `banners_update`, `banners_delete`, `banners_reorder`. A place is its key, a
banner its id, a picture or a video a library key (an unknown one is refused). A button is
`{ label, link, variant }` — `link` an address or an entity as `menu_add_link` takes it, a look the
config does not have refused with the list of those it has. `banners://catalog` is what an agent
reads first: the looks, the layouts, every place with its banners in order, `written_in` and
`has_video` on each — and the reminder that a template, not a block, puts a place on the site.

## Demo content

`php artisan webx:demo` fills `hero` with four banners (three on, one of them without Russian
words, and one off) and `promo` with one, on the library's demo pictures. A button "to a page" points
at a page of the pages demo, else at the first page the site has. No page or template is touched.

## License

MIT
