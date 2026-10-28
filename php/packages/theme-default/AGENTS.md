# webx-ui/theme-default

The theme every new site stands on: a value for every site token, the page layout with its header
and footer, and the stylesheet of the shell and of prose. A neutral starting point, written to be
overridden from the site's own `theme/` — never edited in `vendor/`. The mechanism (chain, token
vocabulary, `@webxTheme`, `webx:theme:sync`) is `webx-ui/themes`.

## What it owns

- **`tokens.json`** — `defaults` for all forty tokens of the vocabulary, each a `--site-<name>`:
  `color-bg`, `color-surface`, `color-surface-muted`, `color-text`, `color-text-muted`,
  `color-border`, `color-accent`, `color-accent-contrast`, `color-accent-2`, `color-highlight`,
  `color-danger`, `color-success`; `font-body`, `font-heading`, `font-size-base`,
  `line-height-base`, `font-size-1`…`font-size-6`; `space-1`…`space-8`; `radius-sm`, `radius-md`,
  `radius-lg`, `radius-full`; `shadow-sm`, `shadow-md`; `container-width`, `gutter`, `duration`,
  `easing`. The presets `warm` and `night`; a site draws in one by naming it in its manifest
  (`"preset": "night"` in `theme/theme.json`). Its test is `Testing\ThemeContract` first.
- **`views/components/layout`** — the document every module page is printed in (`<x-layout>`), the
  dispatcher of the composition `layout` (`compositions/layout/default.json`; also `layout.minimal`,
  `layout.blank`, and `layout.account` — the grid of a visitor's account, `account.menu` beside `account.body`, for `webx-ui/module-users`): `@webxTheme`, `@webxThemeColor`, the page's `head` slot (or `@webxSeo` + `@webxBlocks`), `@stack('head')`,
  `<x-webx-notice-bar>` above the header, the `header`/`footer` regions, `<main id="content" class="site-main">`, `@stack('scripts')`; the modules add their pieces.
- **`views/components/header`**, **`views/components/footer`** — the regions' fallbacks and the
  dispatchers of the compositions `header`/`footer` (`compositions/header|footer/default.json`, leaves
  in `views/components/header|footer/`): they work out the `vars` (`menu('header')` split into `nav`
  and `calls` — the pages under home until it has entries —, `contacts()`, the languages;
  `menu('footer')`). Ids: `header.topbar` → `header.contacts`, `header.brand` → `header.logo`,
  `header.nav`, `header.actions` → `header.phones|cta|languages|cart`; `footer.inner` → `footer.contacts`,
  `footer.nav` → `footer.menu|consent`, `footer.copy`.
- **`views/errors/404`** — a missing address in the site's frame: `<x-layout>` with its own head
  (`<title>`, `noindex`, `@webxBlocks`, no `@webxSeo`), the code, a line and a link to the
  language's home page. **`lang/<10 languages>/theme.php`** — every word of the shell and the
  blocks, `locales` in the manifest: `shell.skip` ("Skip to content"), `shell.footer` (the
  footer navigation's `aria-label`), `not_found.*`, `blocks.contact.*`, `mail.sent`. No template
  writes a word.
- **`views/mail/layout`** — the frame of every letter of the site: the site name, the sheet with
  the letter (`$slot`), a "Sent from" line. A module's letter asks for it with
  `@component('mail.layout', ['title' => …])` (module-inbox does). Tables and `style="…"` with
  values from `theme_token()`, sizes in px: mail reads no CSS variable and no class.
- **Classes** (the contract a local theme restyles): `site` on `<body>`, `site-container`,
  `site-skip`, `site-header`, `site-header__inner|title|nav|link`, `site-main`, `site-bleed` (a
  direct child of `<main>` that spans the page), `site-footer`,
  `site-footer__inner|nav|link|copy`, `site-error`, `site-error__code|home`, `site-prose` (prose typography outside `<main>`, and
  the muted colours of quotes, captions and `small` inside a block, which otherwise keep the
  block's own colour).
- **`blocks/`** — the six block types `webx:blocks:import --theme` puts on a site (`webx:setup` only prints it)
  (THEMES §9.1), as `webx:blocks:export` writes them; CSS on the tokens and `@container` only:
  - `hero` — `title`, `subtitle`, two buttons (`button_*`, `second_*`), `tone`
    `plain|surface|accent|image` (+ `image`), `align` `start|center`, `width` `column|bleed`;
  - `text` — `heading`, `body` (rich text, under `site-prose`; a body of plain lines prints as
    paragraphs), `width` `narrow|wide`;
  - `media-text` — `image`, `heading`, `body`, one button, `side` `start|end`, `tone` `plain|surface`;
  - `cards` — `heading`, `intro`, `columns` `2|3|4` (at most; the container decides), `pictures`
    `photo|icon`, repeater `cards` (`image`, `title`, `text`, `link_label`, `link_url`: the
    whole card is the link; `link_label` ends in the `arrow-right` icon, `b-cards__arrow`);
  - `cta` — `heading`, `text`, two buttons, `tone` `accent|surface|plain`;
  - `contact` — `heading`, `text`, `show_contacts`/`show_hours` (Settings › Contacts through
    `contacts()` and the phones, hours and socials widgets), `form` (an Inbox form's slug),
    `form_heading` and `show_form_description` (off: the headings say what the form is for); labels `theme::theme.blocks.contact.*`, each after its icon (`b-contact__icon`).
    Classes are BEM under `b-<slug>` (`b-hero__title`, `b-cards__card`, `b-cta__actions` …).
- **`demo/pages/`** — the showcase ("Kitchen sink" and the pages under it), seeded by
  `webx:demo` beside the pages demo and removed with it. Its blocks use the six above, the
  `columns` of the module-blocks demo, the showcase's own in `demo/blocks/` (what no real block
  shows — `showcase-column` is a 20rem column to put a block in) and
  the `gallery`, `logos`, `video`, `map`, `counters`, `countdown`, `compare` and `toc` blocks of `webx-ui/widgets`; their pictures are `demo/media/`, put in
  the library by the media demo and named in a page as `"path": "demo:<file name>"`. Nothing
  under `demo/` is published on a site. Kitchen sink › Blocks: a page per block above — every
  variant, then the block in a narrow column. Kitchen sink › Icons: every icon of the chain
  (`showcase-icons`), then icons in a line, at four sizes, in buttons, on the accent. Kitchen
  sink › Assets: `assets/img/wave.svg` through `theme_asset()` as a picture and as a mask over the
  accent (`showcase-assets`). Kitchen sink › Fonts: a line in every script Inter covers and one
  in Greek, which it does not (the fallback, on purpose), the weights and the italic
  (`showcase-fonts`). A page per widget under "Kitchen sink" — every variant, a narrow column,
  what to try; the place to look before changing one.
  Written in English, Russian and Polish: a page's `title`, `slug` and SEO card, and every
  field a visitor reads (an `alt` too), are `{ "en": …, "ru": …, "pl": … }`; the demo keeps the
  languages the site has and gives its default one the English where it is missing. A plain
  string still works — the site's default language. Language switcher › English only stays
  untranslated on purpose: the switcher's answer to a page with no translation. `ThemeDefaultTest`
  fails on a visitor's word left as one string on a translated page. The showcase's own types in
  `demo/blocks/` print no word of their own either — a button, a caption, a card's title are
  `localized` fields (`:n`, `:count`, `:word` where a number goes), and the test reads their
  templates as it reads the shell's. Pictures in `demo/media/` carry no text (rule 28): a word in a
  picture cannot be translated; a logo is a name and stays.
- **`src/css/`** — `base.css`, `shell.css`, `prose.css`, joined by `theme.css`; every selector
  inside `:where()` (no specificity). **`dist/theme.css`** — the committed build that
  `webx:theme:sync` publishes.
- **`icons/`** — 27 icons for `<x-theme::icon name="…">`: `arrow-left|right|up-right`,
  `chevron-up|down|left|right`, `check`, `close`, `plus`, `minus`, `menu`, `search`, `phone`,
  `mail`, `map-pin`, `clock`, `calendar`, `user`, `share`, `link`, `download`, `globe`, `info`,
  `alert`, `star`, `play` — 24×24, stroke 2, round ends, `currentColor`. `theme-icon` is one em,
  on the text's line (`base.css`).
- **`assets/img/`** — pictures for `theme_asset('img/…')`: `wave.svg` (two grey waves, 1200×120,
  stretched; as a mask it takes any colour).
- **`assets/fonts/`** — Inter, the theme's own files (no CDN): a variable file per script —
  `latin`, `latin-ext`, `cyrillic` — upright and italic, weights 100–900, declared under `fonts`
  in `composer.json`; `OFL.txt` beside them is its licence and travels with it. `font-body` is
  `Inter, system-ui, …`, so a script without a file falls to the system font. An English page
  downloads only `inter-latin.woff2`. Other scripts (Greek, Vietnamese) — a local theme declares
  `Inter` again with them, or its own family.

## Change it without forking

| You want                                 | Do this                                                                                                                                                                                     |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Another accent, font, radius, spacing    | `theme/tokens.json` → `defaults`                                                                                                                                                            |
| Draw the site in another palette         | `"preset": "night"` in `theme/theme.json`; a palette of your own in `theme/tokens.json` → `presets`                                                                                         |
| Restyle the header, footer or prose      | `theme/src/css/theme.css`, on the `site-*` classes above                                                                                                                                    |
| A piece of the header or footer          | `theme/compositions/header/default.json` (or `footer`) by id; the notice bar inside: `remove` it in `layout`, `add` to `header.topbar`                                                      |
| An attribute, a piece moved, gone, added | `theme/compositions/layout/default.json` (or `<handle>.json`) by id: `html`, `head`, `body`, `skip-link`, `notice-bar`, `header`, `main`, `content`, `footer`, `meta.theme-color` — no copy |
| A different document (the last door)     | `theme/views/components/layout.blade.php` — the composition is not printed then; `place` brings the modules' pieces                                                                         |
| Another look of one of the six blocks    | `theme/blocks/<slug>.json`, every field kept; `webx:blocks:import --theme --replace=<slug>`                                                                                                 |
| A section of a page full width           | class `site-bleed` on the direct child of `<main>`                                                                                                                                          |
| Another drawing of an icon, or a new one | `theme/icons/<name>.svg` in `currentColor`, then `webx:theme:sync`                                                                                                                          |
| Another picture of the theme             | `theme/assets/<same path>`, then `webx:theme:sync`                                                                                                                                          |
| Another font                             | files in `theme/assets/fonts/`, `fonts` in `theme/theme.json`, the family in `font-body`/`-heading`                                                                                         |
| Inter with another script (Greek)        | `fonts` in `theme/theme.json` with family `Inter` and every script — it replaces this one whole                                                                                             |
| Prose typography outside `<main>`        | class `site-prose` on the wrapper                                                                                                                                                           |
| The header and footer as blocks          | arrange the `header`/`footer` regions in the panel; no code                                                                                                                                 |
| Another frame for every letter           | `theme/views/mail/layout.blade.php`, copied from here; colours from `theme_token()` only                                                                                                    |
| Another inbox letter                     | `theme/views/vendor/webx-inbox/mail/submission.blade.php`, copied from module-inbox                                                                                                         |

## Do not

- Do not edit `vendor/webx-ui/theme-default`: `composer update` replaces it.
- Do not override the layout to change a colour or a margin: a token or a CSS rule is enough.
- Do not drop `@webxTheme`, the `head` slot, `@stack('head')` or the two regions from an
  overridden layout: pages lose their tokens, metatags or header without an error.
- Do not print `@webxSeo` or `@webxBlocks` unconditionally in an overridden layout: module views
  already send both in the `head` slot, and twice means two `<title>` tags.
- Do not write a literal colour, a font name or `var(--site-x, fallback)` in CSS; do not use
  `@media` for width — `@container`.
- Do not write `var(`, a class or a literal colour into a letter: the mail client paints nothing
  there, or the letter stops repainting with the site; `webx:theme:check` names both (rule 25).
- Do not raise specificity or add `!important` to beat this theme: its rules have none.
- Do not make a new block with a similar name for another look of one of the six (`hero-2`):
  override the slug, so the pages already on it change with it.

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
