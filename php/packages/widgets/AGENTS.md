# webx-ui/widgets

Interactive pieces for the public site: behaviour, keyboard, ARIA and the no-JavaScript fallback
are done here; the look is a skeleton on `--site-*` tokens that the theme repaints. It is the
bottom layer of the theme chain, like a module, and needs `webx-ui/themes`.

## What it owns

- **Runtime** — `dist/runtime.js` + `dist/runtime.css`, on every page of a site with a theme. Joins
  `window.webx`: `webx.mount(root)` starts what is new inside `root` (idempotent),
  `webx.unmount(root)` lets it go, `webx.widget(name, selector, setup)` registers a widget —
  `setup(el)` may return its cleanup.
- **Behaviours by attribute** (no component needed):
  - `data-webx-disclosure="<id>"` on a button — shows/hides `#<id>`, Esc and a click outside close.
  - `data-webx-dialog="<id>"` on a link or button — opens `<dialog id="<id>">` as a modal;
    `[data-webx-dialog-close]` inside closes it. A URL ending in `#<id>` opens it on load.
  - `data-webx-tabs` on a container of `[data-webx-tab]` panels — label from the attribute's
    value or the panel's first heading; `data-webx-tab-selected` picks the first one shown.
  - `data-webx-accordion="single"` on a container of `<details>` — one open at a time.
- **Components** — `<x-webx-dialog id title close>`, `<x-webx-tabs label>` with
  `<x-webx-tabs.panel title selected level>`. Views: `webx-widgets::components.<name>`.
- **Loading** — `Widgets::need('<name>')` (facade `WebxUi\Widgets\Facades\Widgets`) claims a
  widget's own `dist/<name>.js|css` for the page. `@webxTheme` prints a marker; the response gets
  the `<link>`s there and the `<script type="module">`s before `</body>`.
- **Classes** — public contract: `webx-<name>`, `webx-<name>__<element>`, states `is-*`
  (`webx-dialog__header|title|close|body`, `webx-tabs__list|tab|panel|title`, `is-enhanced`).
  `html.webx-js` once the runtime runs; `html.webx-scroll-locked` while a modal is open.
- **Local tokens** — `--webx-dialog-width`, `--webx-dialog-padding`, `--webx-dialog-backdrop`,
  `--webx-tabs-gap`, `--webx-tabs-indicator`; declared on `:root` from `--site-*`.
- **Words** — `webx-widgets::widgets.*` in en, ru, uk, de, pl, fr, es, it, pt, tr; every word
  is also a prop (`close="…"`).

## Change it without forking

| You want                               | Do this                                                                                                   |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Other colours, radius, spacing         | site tokens in `theme/tokens.json` — the widgets follow                                                   |
| A widget's own measure (dialog width…) | `--webx-<name>-*` on `:root` or on `.webx-<name>` in `theme/src/css`                                      |
| Another look of a widget               | CSS on `.webx-<name>*` classes in `theme/src/css` — no `!important` needed, every rule here is `:where()` |
| Other markup of a component            | `theme/views/vendor/webx-widgets/components/<name>.blade.php`                                             |
| A word                                 | the component's prop, or `lang/vendor/webx-widgets/<locale>/widgets.php`                                  |
| A behaviour of the site's own          | `webx.widget('name', '[data-…]', (el) => cleanup)` in `theme/src/js/theme.js`                             |
| Start widgets in HTML added later      | `webx.mount(container)`; before removing it, `webx.unmount(container)`                                    |

## Do not

- Do not edit `vendor/webx-ui/widgets` or `public/themes/webx-ui/widgets`: the next update or
  `webx:theme:sync` replaces them.
- Do not write a colour literally or with a fallback (`var(--site-x, #fff)`): presets stop
  repainting it.
- Do not add `hidden` to a disclosure's panel in markup: without JavaScript it would never show.
- Do not copy the runtime into the theme to change one behaviour: register your own widget.
- Do not call `Widgets::need()` for the behaviours above to get them — the runtime is already on
  every page; `need()` matters for widgets with files of their own.

## Check your work

- `php artisan webx:theme:sync` lists `webx-ui/widgets` as published.
- The page source has `/themes/webx-ui/widgets/<hash>/runtime.css` in `<head>` and
  `runtime.js` right before `</body>`; no `<!--webx-widgets-->` left.
- In the browser console: `typeof webx.widget === 'function'`; Tab through the widget with the
  keyboard; turn JavaScript off and the content is still there.

## Read more

- [README.md](README.md) in this directory.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_WIDGETS.md
