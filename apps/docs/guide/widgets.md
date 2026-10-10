# Site widgets

`webx-ui/widgets` is the set of interactive pieces every public site repeats: the header and its
mobile menu, dialogs, tabs and accordions, the cookie banner, phones and opening hours, a language
switcher, a slider, a lightbox, a video and a map. The logic, the keyboard, ARIA, loading on demand
and consent are done in the package; the look is a skeleton on the site's tokens that a theme
repaints with the same doors it uses for the modules.

It is a Composer library — no panel section, no tables. `theme-default` depends on it, so a site
made by `webx:setup` with a theme has it already. Three rules hold for every widget:

- **A script only where a widget stands.** A page with a slider loads Swiper; the other hundred
  pages do not.
- **Nothing third-party before consent.** A YouTube player, a map's tiles, a counter: until the
  visitor agrees to that category there is no request to the third party at all, only a notice
  with a button.
- **It works without JavaScript.** A slider is a strip that scrolls, a lightbox a link to the
  picture, tabs are headings over their panels, a video a link to it, a map an address and a link.

The specification, with every decision and how each piece went into code, is
[`docs/architecture/WEBX_UI_WIDGETS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md).

## How a page gets the files

The package is the bottom layer of the theme chain, like a module: its views are overridden in
the theme, its CSS comes first in the cascade, its built files are published next to the theme's.

```bash
php artisan webx:theme:sync   # publishes dist/ of the theme chain, the widgets included
```

`@webxTheme` in the layout's `<head>` prints the tokens, the widgets' runtime and the theme's CSS.
It is expanded at the end of the response: by then every component on the page — the header and
the footer too — has said what it needs, and only that is printed — `<link>`s in the head, a
`<script type="module">` before `</body>`.

- **The runtime** (`runtime.js|css`, under 16 KB gzip) is on every page of a site with a theme: the
  behaviours by attribute, the header, the mobile menu, dropdowns and the form in a dialog.
- **A widget's own files** load only when it is claimed. A component claims itself; code of your
  own claims with the facade:

```php
use WebxUi\Widgets\Facades\Widgets;

Widgets::need('slider'); // dist/slider.js and slider.css on this page
```

- **The cookie banner** (`consent.js|css`) is on every page: the package prints it before `</body>`
  by itself — there is no component to place and none to forget.
- A lightbox link and a link to a form in a dialog are found in the finished page: a link written
  by hand in a block claims them without any code.

Every script starts through `webx.mount(root)`, so a widget comes alive in a block the panel's
preview swapped in and in HTML loaded later. Before removing such HTML, call `webx.unmount(root)`.

## Behaviours by attribute

No component needed — the attributes are enough, in a block template or in a theme's view:

| Attribute                                 | What it does                                                                                     |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `data-webx-disclosure="<id>"` on a button | shows and hides `#<id>`; Esc and a click outside close it                                        |
| `data-webx-dialog="<id>"`                 | opens `<dialog id>` as a modal with a focus trap; `[data-webx-dialog-close]` inside closes it    |
| `data-webx-tabs` on a container           | its `[data-webx-tab]` panels become tabs — arrows, Home, End; an anchor in the address opens one |
| `data-webx-accordion="single"`            | on `<details>`: one open at a time                                                               |

The components `<x-webx-dialog>` and `<x-webx-tabs>` print the same markup for you.

## Header and mobile menu

```blade
<x-webx-header sticky="hide-on-scroll" collapse="auto">
    <x-slot:brand><a href="/">Shop</a></x-slot:brand>
    <x-webx-header.nav :items="menu('header')" />
    <x-slot:actions><x-webx-phones /></x-slot:actions>
</x-webx-header>
```

`collapse="auto"` measures instead of guessing: the navigation folds into the menu button exactly
when its items no longer fit the bar. The mobile menu is built from the same parts — the brand on
top, the menu's tree in the body (`drill` or `accordion`), the actions at the bottom; it closes
on a link, on "Back" and on a swipe. `--webx-header-height` stays live, so an anchor under a
sticky header keeps its heading in view.

## Dropdown and form in a dialog

`<x-webx-dropdown>` is a `<details>` — a click opens it without JavaScript; with it the panel is
placed from its button and turned over at the window's edge, and one is open on the page.

Any link to `#webx-form-<slug>` — typed into a menu item or a block's button in the panel — opens
that `module-inbox` form in a dialog, printed once per page however many buttons lead to it.

## Cookie consent

The banner offers only the categories something on the site asks for: `necessary` (always),
`preferences`, `statistics`, `marketing`, `media`. "Reject all" is the same button as "Accept all";
"Customize" opens the categories; "Cookie settings" in the footer (`<x-webx-consent-link />`)
opens them again. The answer is the `webx_consent` cookie, which the server reads too — that is how
a video's notice is printed without a flash.

Third-party code of the site waits for its category in one of three ways:

```blade
{{-- Anything, as it is: printed as it stands once the visitor agrees --}}
<x-webx-consent category="statistics">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>
</x-webx-consent>

{{-- Or by hand: a script the browser does not run, an iframe without its src --}}
<script type="text/plain" data-webx-consent="marketing" src="https://example.com/pixel.js"></script>
<iframe data-webx-consent="media" data-src="https://www.youtube-nocookie.com/embed/…"></iframe>
```

From the site's own scripts:

```js
webx.consent.has('media') // true once the visitor agreed
webx.consent.open() // the dialog with the categories
document.addEventListener('webx:consent', (event) => console.log(event.detail.categories))
```

On the server, `Consent::has('media')`. Google Consent Mode v2 `default` is printed in the head,
before any Google tag. The banner's texts, the policy page, its version ("Ask everyone again") and
the categories are on the **Cookie** tab of the settings (`consent.*`, also over MCP); without
`module-settings`, in `config/webx-widgets.php`. Switching the banner off is for a site that needs
no consent — the [site audit](./audit) notices it when something third-party is on the site.

## Contacts

The phones, e-mails, addresses, hours and networks live once, on the **Contacts** tab of the
settings; the widgets, the map and the SEO markup read them from there, and print nothing while it
is empty.

| Component                 | What it shows                                                                               |
| ------------------------- | ------------------------------------------------------------------------------------------- |
| `<x-webx-phones>`         | the main number as a `tel:` link in E.164, the others in a dropdown, messengers beside them |
| `<x-webx-hours>`          | "Open until 7:00 PM" in the site's time zone, the week in a dropdown or a table             |
| `<x-webx-contact-button>` | a round button in a corner: chats, the number, the form — above the cookie banner           |
| `<x-webx-contact-bar>`    | "Call / Write / Request" along the bottom of a phone — one of the two, not both             |
| `<x-webx-socials>`        | the networks as icons                                                                       |

## Language switcher

`<x-webx-language-switcher />` names each language in itself and leads to the **same page** in it —
the addresses come from the routing registry, like `hreflang`. A page not translated yet leads to
that language's home page and is marked `is-fallback`. A one-language site prints nothing.

## Slider and lightbox

```blade
<x-webx-slider variant="cards" :per-view="['sm' => 1.2, 'md' => 2, 'lg' => 3]">
    @foreach ($services as $service)
        <x-webx-slide>…</x-webx-slide>
    @endforeach
</x-webx-slider>
```

Variants: `cards`, `hero` (fade and autoplay), `gallery` (thumbnails), `logos` (a running strip).
"Per view" counts by the slider's **own** width, so a slider in a narrow column behaves like one.
Whatever moves by itself has a pause button. Swiper is built in; nothing to install.

The lightbox (PhotoSwipe) opens any link with `data-webx-lightbox`; links with the same value page
together. `<x-webx-lightbox :image="$picture">` takes a picture of the media library and writes
its size; a link by hand needs `data-width` and `data-height` — without them the picture is
downloaded first to be measured, and the audit says so.

## Video and map

```blade
<x-webx-video src="https://www.youtube.com/watch?v=…" :poster="$image" title="The tour" />
<x-webx-video :file="$media" :poster="$image" />
<x-webx-map from="settings" />
<x-webx-map :lat="52.5163" :lng="13.3777" :zoom="16" marker="Pariser Platz" />
```

YouTube and Vimeo are a poster and a play button; the player (`youtube-nocookie.com`) comes only
on a click. Before consent to `media` the server prints a notice over the poster — "Load" (this one)
and "Always load videos" (consent). With no poster set, the video's own preview is fetched **once**
into the media library, so the visitor never asks YouTube even for the picture. A file of the site
is a `<video preload="none">` and needs no consent.

```blade
<x-webx-video variant="background" :file="$media" :poster="$image" ratio="21/9">
    <h1>Made to move</h1>
</x-webx-video>
```

The **background** of a first screen is a file of the site only — a provider's video behind a hero
would be asked on every showing — muted, looping, without controls, behind whatever the tag wraps;
the ratio is the least the frame takes. It plays only while on screen and with the tab in front,
and never by itself under reduced motion or on a connection saving data: then the poster stays, as
it does without JavaScript, and the file is not even asked for. A pause button is always in its
corner (WCAG 2.2.2); a pause is remembered for the next page. The video is decor, hidden from screen
readers: what it says, the page's markup over it says.

The map is Leaflet over OpenStreetMap tiles, with no key. Before consent it is the address, "Open
in maps" and the same two buttons; the wheel scrolls the page until the map is clicked. Another
provider of tiles is a config entry:

```php
// config/webx-widgets.php (php artisan vendor:publish --tag=webx-widgets-config)
'map' => [
    'provider' => 'maptiler',
    'providers' => [
        'maptiler' => [
            'label' => 'MapTiler',
            'tiles' => 'https://api.maptiler.com/maps/streets-v2/256/{z}/{x}/{y}.png?key={key}',
            'key' => env('WEBX_MAP_KEY'),
            'attribution' => '…',
            'max_zoom' => 20,
        ],
    ],
    // Where "Open in maps" leads when the address has no link of its own.
    'open' => 'https://www.openstreetmap.org/?mlat={lat}&mlon={lng}#map={zoom}/{lat}/{lng}',
],
```

`{key}` is replaced by the entry's `key`; a provider named but not described, or a `{key}` with no
key, is an error at once rather than a map that silently asks for tiles without one.

## Page tools

What a long page needs, each loading only where it stands:

```blade
<x-webx-share title="Our new workshop" />
<x-webx-back-to-top />

<div class="cards" data-webx-reveal="stagger" data-webx-reveal-effect="up">…</div>
<section data-webx-reveal="fade">…</section>
```

- **Tables of prose** — nothing to write. Every `<table>` without a class on a page — what a rich
  text field stores, whichever module prints it — is put by the server in a frame that scrolls
  sideways when the table is wider than the column. Its caption is taken out above the frame, so it
  stays in place while the table scrolls, and still names the table. A shadow shows the side there
  is more behind; a table that fits is no tab stop. A table a template wrote has a class and is
  left alone.
- **Reveal on scroll** — `data-webx-reveal="up"`, `fade` or `scale` on any element, or `stagger`
  on a container to bring its children in one after another. In the runtime, no component. Only
  the script hides anything, and only what is below the screen when the page opens: without
  JavaScript or with reduced motion everything is simply there.
- **Share** — each network's own address for sharing as a plain link (Facebook, X, LinkedIn,
  Telegram, WhatsApp, e-mail by default; also Viber, Reddit, Pinterest, Threads, Bluesky, VK) and
  Copy link. No network's script, so nothing to ask consent for. On a phone one Share button opens
  the system's share sheet instead. `url` is the page by default.
- **Back to top** — a round button in a corner that comes after two screens (`:after`), goes up
  smoothly unless the visitor asked for reduced motion and leaves the focus at the top. It stands
  above the cookie banner, the contact bar and the quick contact of its corner. Without JavaScript
  it is a link to `#top` where the template put it.

## Counters and countdown

```blade
<x-webx-counter :value="3000" suffix="+" />
<x-webx-counter value="4.9" prefix="★ " :duration="1500" />
<x-webx-countdown to="2026-12-31 18:00" ended="The sale is over" />
```

- **Counter** — the server prints the final number the way the page's language writes it (3,000;
  3.000 in German): that is what search engines index, a screen reader says and a page without
  JavaScript shows. The script counts up to it when the counter comes into view — only a counter
  below the screen when the page opens; one already in view keeps its number rather than flash and
  drop to zero. Decimals are the ones the value was written with. Reduced motion: final at once.
- **Countdown** — to a wall time of the **site's** time zone (the one of the opening hours on the
  Contacts tab, else `app.timezone`): the sale ends at six where the shop is, wherever it is read.
  A moment with its offset, or a `DateTimeInterface`, is that moment. The server prints the time
  left, the script counts down every second from the clock — a page out of a cache is right. At the
  end the `ended` text takes its place; without one the timer goes, and so does the nearest
  `[data-webx-countdown-scope]` around it (the Countdown block is one). Without JavaScript it is the
  line "Ends on …" rather than digits standing still; with it, that line is what a screen reader hears.

## Before and after, table of contents

```blade
<x-webx-compare :before="$old" :after="$new" :start="30">Two weeks apart</x-webx-compare>

<x-webx-toc>…a long text with h2 and h3…</x-webx-toc>
<x-webx-toc for="terms" :depth="2" title="Contents" />
```

- **Before and after** — `before` and `after` are what `<x-webx-lightbox :image>` takes: a media
  field's value, an object with `url()`, an address. The frame has the shape of the first picture
  with sizes (or `ratio`), so nothing below it moves while they load; the after picture lies over
  the before one, cut at the divider. The divider is an `<input type="range">`: the arrows move it
  (5%, 1% with Shift, Home and End), a screen reader hears a slider; a mouse drags it, a finger too
  once it moves sideways — up and down still scrolls the page. Labels are `before-label` and
  `after-label`, "Before" and "After" in the page's language by default. Without JavaScript the
  two pictures stand side by side, or one under the other in a narrow column.
- **Table of contents** — the list is made by the server from the finished page: of the tag's own
  slot, of the element `for` names (an id, without `#`) or of `<main>`. Headings without an id get
  one from their words, unique on the page, so every link works without JavaScript and can be
  shared; an id you gave is kept, a heading with `data-webx-toc-skip` is left out. `depth` 2 lists
  the `h2`, 3 (the default) the `h3` under them. With a slot, a wide container puts the list beside
  the text (`side="end"` or `start`), sticky under the header; a narrow one — a phone, a column
  20rem wide — puts it above, folded into a bar that names the section being read and opens the
  list. `fold="never"` keeps it open. The section being read is marked `is-current` as the page
  scrolls.

## Pages of a list, "Show more", the notice bar

```blade
<x-webx-pagination :paginator="$articles" />

<x-webx-load-more :paginator="$articles" pages="shown">
    <ul class="cards" data-webx-load-more-list>
        @foreach ($articles as $article) <li>…</li> @endforeach
    </ul>
</x-webx-load-more>

<x-webx-notice-bar />
```

- **Pagination** — what `->paginate()` answered, as it is: previous, the numbers around this page
  (`around`, 2 by default) with the first and the last always there, next. `->simplePaginate()`
  gets previous and next. The first page links to the list's own address, not `?page=1` — one
  address per page. A stylesheet, no script.
- **Show more** — the list and its pagination, which is what a page without JavaScript and a search
  engine get. The script shows a button: it fetches the next page as any visitor gets it, takes the
  items of the list with the same page parameter out of its HTML and adds them, focuses the first
  new item, says "Page 2 of 4 loaded." to a screen reader and replaces the address with
  `?page=2`. Back leaves the list; a reload lands on the page last loaded, whose links lead back. No
  endpoint and no JSON: an item is what the module prints, a theme's override included. The list
  is the element marked `data-webx-load-more-list` (its children are the items); without one the
  slot is the items. `pages="covered"` (the default) — the button stands in for the links;
  `pages="shown"` — both, the pages already on the screen marked. A module's own links go in the
  `links` slot. A failure says so and uncovers the links.
- **Notice bar** — above the header (the default theme's layout prints it after "Skip to
  content"), a region named "Announcement" with a close button. It says the first banner of the
  place `notice` of `webx-ui/module-banners` — a place of words, where a banner needs no
  picture — else its slot, else nothing. Closing it remembers the version of its words in
  `localStorage` (`version` names one of your own); new words come back for everybody. A closed bar
  is hidden by a line in the head before the page is painted, so it never flashes. It is in the
  flow and does not stick: the sticky header and `--webx-header-height` are what they were.

## Blocks

On a site with `module-blocks` and `module-media` the package offers eight block types — `webx:setup`
installs them, or `php artisan webx:blocks:offered --install --module=widgets`. Installed, they
belong to the site: an update never overwrites them.

| Block       | Fields                                                                         | Inside                     |
| ----------- | ------------------------------------------------------------------------------ | -------------------------- |
| `gallery`   | heading, pictures of the library, grid or slider, columns, zoom                | slider `gallery`, lightbox |
| `logos`     | heading, a name, a logo and a link each                                        | slider `logos`             |
| `video`     | heading, a link or a file (a switch), poster, caption, shape 16:9 4:3 1:1 9:16 | `<x-webx-video>`           |
| `map`       | heading, the contacts or coordinates with an address, zoom, height             | `<x-webx-map>`             |
| `counters`  | heading, a number, what stands before and after it, a label each               | `<x-webx-counter>`         |
| `countdown` | heading, text, a date and a time of the site's zone, at the end a text or hide | `<x-webx-countdown>`       |
| `compare`   | heading, before, after, their labels, where the divider starts, caption        | `<x-webx-compare>`         |
| `toc`       | title, the whole page or a text of its own (a switch), side, depth             | `<x-webx-toc>`             |

A block with nothing to show — no pictures, an unknown address, a deleted file — prints nothing.

## Changing the look without forking

| You want                       | Do this                                                                                                         |
| ------------------------------ | --------------------------------------------------------------------------------------------------------------- |
| Other colours, radius, spacing | the site tokens in `theme/tokens.json` — every widget follows                                                   |
| A widget's own measure         | its `--webx-<name>-*` token (`--webx-slider-gap`, `--webx-video-radius`…) on `:root` or on its class            |
| Another look of a part         | the same class in `theme/src/css` — `.webx-slider__pause { … }`: it comes later in the cascade, no `!important` |
| Other markup                   | `theme/views/vendor/webx-widgets/components/<name>.blade.php`                                                   |
| An icon                        | `theme/views/vendor/webx-widgets/icons/<name>.blade.php`                                                        |
| A word                         | the component's prop, or `lang/vendor/webx-widgets/<locale>/widgets.php`                                        |

The classes — `webx-<name>`, `webx-<name>__<part>`, states `is-*` — are the public contract.
Style a part by its one class, not through a chain: one class beats the theme's prose and is
beaten by the theme's own rule, which comes later. An overridden view keeps what the package's
view promised — a slider that moves needs its pause button, and the audit checks it.

## In the site audit

With `webx-ui/module-audit` installed, every crawled page is also read for what the widgets
promise:

| Check                    | Severity | Finds                                                                                              | Fix by a button                                    |
| ------------------------ | -------- | -------------------------------------------------------------------------------------------------- | -------------------------------------------------- |
| `widgets.before_consent` | error    | a YouTube, Vimeo, Google Maps or OpenStreetMap iframe, a known counter or pixel asked at once      | pasted into content: rewritten to wait for consent |
| `widgets.banner_off`     | warning  | the banner off while something third-party is on the site                                          | turns the banner on                                |
| `widgets.lightbox_size`  | warning  | a lightbox link without `data-width` / `data-height`                                               | —                                                  |
| `widgets.slider_pause`   | warning  | a slider that moves with no pause button — a theme's override that lost it                         | —                                                  |
| `widgets.video_pause`    | warning  | a background video with no pause button — an override that lost it                                 | —                                                  |
| `widgets.counter_number` | notice   | a counter whose markup does not hold its number — an override that left it to the script           | —                                                  |
| `widgets.compare_range`  | warning  | before and after without its range input — an override that lost it: no keyboard, no screen reader | —                                                  |
| `widgets.toc_target`     | warning  | a link of a table of contents to an id the page does not have                                      | —                                                  |
| `widgets.load_more_link` | warning  | "Show more" with a next page and no link to it — only a button, which no search engine presses     | —                                                  |
| `widgets.contact_both`   | notice   | the quick-contact button and the bottom bar on one page                                            | —                                                  |

"Pasted into content" means a text block, a page's body or any field a module hands to the audit:
the fix finds the code there and rewrites it — an iframe's `src` becomes `data-src`, a script
becomes `type="text/plain"`, both get `data-webx-consent` of their category. Code that a template
prints is not in any field; wrap it in `<x-webx-consent>` or use the video and map blocks. A
third party the audit does not know is added in `webx-widgets.audit.third-party`
(`'widget.example.com' => 'marketing'`).

## The Kitchen sink

`theme-default` brings a showcase, seeded by `php artisan webx:demo` and removed with it: under
**Kitchen sink** a page per widget — Header and menu, Cookie consent, Dropdown and form in a
dialog, Contacts, Language switcher, Slider, Lightbox, Video, Map, Page tools, Background video,
Counters and countdown, Before and after, Table of contents, Show more, Notice bar — with every variant, a narrow
column, what to try and the markup to copy. It is the place to look at a change in the theme
before a real page does, and the pages the starter site's browser tests measure.
