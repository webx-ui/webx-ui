# webx-ui/theme-default

The theme every new site stands on: a value for every site token, the page layout with its header
and footer, and the stylesheet of the shell and of prose. A neutral starting point, written to be
overridden from the site's own `theme/` — never edited in `vendor/`. The mechanism (chain, token
vocabulary, `@webxTheme`, `webx:theme:sync`) is `webx-ui/themes`.

## What it owns

- **`tokens.json`** — `defaults` for all forty tokens of the vocabulary (`--site-color-*`,
  `--site-font-*`, `--site-space-1…8`, `--site-radius-*`, `--site-shadow-*`,
  `--site-container-width`, `--site-gutter`, `--site-duration`, `--site-easing`) and the presets
  `warm` and `night`. `editable` in the manifest: `color-accent`, `color-accent-contrast`,
  `font-heading`.
- **`views/components/layout`** — the document every module page is printed in (`<x-layout>`):
  `@webxTheme`, the page's `head` slot (or `@webxSeo` + `@webxBlocks` when there is none),
  `@stack('head')`, the `header` and `footer` regions, `<main id="content" class="site-main">`,
  `@stack('scripts')`.
- **`views/components/header`**, **`views/components/footer`** — the regions' fallbacks:
  `config('app.name')` and `menu('header')` (the pages under the home page until the menu has
  entries); `menu('footer')` and the copyright line.
- **Classes** (the contract a local theme restyles): `site` on `<body>`, `site-container`,
  `site-skip`, `site-header`, `site-header__inner|title|nav|link`, `site-main`, `site-bleed` (a
  direct child of `<main>` that spans the page), `site-footer`,
  `site-footer__inner|nav|link|copy`, `site-prose` (prose typography outside `<main>`, and
  the muted colours of quotes, captions and `small` inside a block, which otherwise keep the
  block's own colour).
- **`demo/pages/`** — the showcase ("Kitchen sink" and the pages under it), seeded by
  `webx:demo` beside the pages demo and removed with it. Its blocks use only the demo block types
  (`hero`, `text`, `columns`).
- **`src/css/`** — `base.css`, `shell.css`, `prose.css`, joined by `theme.css`; every selector
  inside `:where()` (no specificity). **`dist/theme.css`** — the committed build that
  `webx:theme:sync` publishes.

## Change it without forking

| You want                              | Do this                                                     |
| ------------------------------------- | ----------------------------------------------------------- |
| Another accent, font, radius, spacing | `theme/tokens.json` → `defaults`                            |
| Another palette to switch to          | `theme/tokens.json` → `presets`; `WEBX_THEME_PRESET`        |
| Restyle the header, footer or prose   | `theme/src/css/theme.css`, on the `site-*` classes above    |
| Other markup in the header or footer  | `theme/views/components/header.blade.php` (or `footer`)     |
| A different document                  | `theme/views/components/layout.blade.php`, copied from here |
| A section of a page full width        | class `site-bleed` on the direct child of `<main>`          |
| Prose typography outside `<main>`     | class `site-prose` on the wrapper                           |
| The header and footer as blocks       | arrange the `header`/`footer` regions in the panel; no code |

## Do not

- Do not edit `vendor/webx-ui/theme-default`: `composer update` replaces it.
- Do not override the layout to change a colour or a margin: a token or a CSS rule is enough.
- Do not drop `@webxTheme`, the `head` slot, `@stack('head')` or the two regions from an
  overridden layout: pages lose their tokens, metatags or header without an error.
- Do not print `@webxSeo` or `@webxBlocks` unconditionally in an overridden layout: module views
  already send both in the `head` slot, and twice means two `<title>` tags.
- Do not write a literal colour, a font name or `var(--site-x, fallback)` in CSS; do not use
  `@media` for width — `@container`.
- Do not raise specificity or add `!important` to beat this theme: its rules have none.

## Check your work

- `php artisan webx:theme:sync`, then open a page: `<style data-webx-theme>` and a `<link>` to
  `/themes/webx-ui/theme-default/<hash>/theme.css` are in the `<head>`.
- In the browser: `getComputedStyle(document.body).backgroundColor` follows `color-bg` and the
  chosen preset; `document.documentElement.scrollWidth === clientWidth` at 360px.
- Working on this package: `npx vite build` in its directory and commit `dist/`; its test fails
  when `src/` and `dist/` disagree.

## Read more

- [README.md](README.md) in this directory.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md
