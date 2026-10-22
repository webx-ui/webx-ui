# Where the styles live

What a site looks like is spread over three places, and only two of them are the site's to edit.
This page says which file holds what, what is safe to change and what is not, and how to see a
change — for a person changing the design with an agent, and for the agent itself.

## Three looks, three places

| Look                                    | Where it lives                                                              | Whose                                     |
| --------------------------------------- | --------------------------------------------------------------------------- | ----------------------------------------- |
| The public site — pages, header, footer | the site's theme: `theme/`, over the packaged theme it stands on; `public/` | **the site's**, edit `theme/` freely      |
| Blocks on the pages                     | block types, made in the panel («Blocks») and stored in the database        | **the site's**, in the panel              |
| The admin panel                         | the packages `@webx-ui/*` in `node_modules/` and `webx-ui/*` in `vendor/`   | the library's — rebrand it, never edit it |

## The public site

A new site stands on a theme: `webx:setup` creates `theme/` over `webx-ui/theme-default`, which
brings the layout, the header, the footer, the typography and a value for every site token — so
the site opens finished. `theme/` holds only what makes this site different, and the modules'
pages (a page, an article, a product) stand inside the theme's layout.

| You want to change                    | Edit                                                                                                 |
| ------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Colours, fonts, radii, spacing        | `theme/tokens.json` — `"defaults": { "color-accent": "#…" }`; every page, block and module follows   |
| Anything else about the look          | `theme/src/css/theme.css`, on the classes the shell and the modules print, with `var(--site-…)` only |
| The frame of every page, the `<head>` | copy `components/layout.blade.php` from `vendor/webx-ui/theme-default/views/` into `theme/views/`    |
| The header and the footer             | the same, `components/header.blade.php` and `footer.blade.php` — shown while the region is empty     |
| The menu items                        | the panel («Menus»), not the view — the header prints `menu('header')`                               |
| A picture of the theme (a background) | `theme/assets/img/` and `theme_asset('img/…')`; see [Assets](#assets)                                |
| A font of the site's own              | `theme/assets/fonts/` and `fonts` in `theme/theme.json`; see [Fonts](#fonts)                         |
| The favicon, files of the site's own  | `public/` — `public/favicon.ico`, your own folders such as `public/documents/`                       |
| A module's page (article, page…)      | its classes in `theme/src/css` first; see [below](#a-module-s-page)                                  |
| The start page before real content    | `resources/views/demo.blade.php` — delete it with its route when the site has a home page            |

Never edit the packaged theme in `vendor/`: `composer update` brings its fixes, and
`php artisan webx:theme:sync` publishes its built files into `public/themes/`. Before you change
anything, `php artisan webx:theme:show` lists what the chain already gives — every token with the
layer that set it, every view with what it overrides, the block types, icons and fonts. How the
chain works, the test every theme runs, and how a site's look becomes a theme of its own
(`webx:theme:make`, `extract`, `eject`) — [Site themes](./themes.md).

The look is the theme's files, in git, and nothing else: the panel has no colour picker and no
preset switch. The site draws in a preset of the chain when its manifest names one —
`"preset": "night"` in `theme/theme.json` — and a palette of its own is a preset or the
`defaults` in `theme/tokens.json`. A view in
`resources/views/components/` sits above every theme layer and wins over it — that is why the
skeleton has no `layout`, `header` or `footer` there.

### CSS knows only tokens

A colour or a font written in a stylesheet is out of reach of the presets and of `tokens.json`:
the page stops repainting wherever it stands. So `theme/src/css` — like the
CSS of the modules and the widgets under it — uses `var(--site-…)` and nothing else:

| Not this                                       | But this                                                                    |
| ---------------------------------------------- | --------------------------------------------------------------------------- |
| `color: #0e6b5c`, `rgb()`, `oklch()`, `white`  | `var(--site-color-accent)` — a role; `transparent`, `currentColor` are fine |
| `font-family: Georgia, serif`                  | `var(--site-font-heading)`; a generic family (`serif`, `monospace`) is fine |
| `var(--site-color-olive)` that the theme lacks | a name of the vocabulary, or add it under `vocabulary` in `tokens.json`     |
| `var(--site-color-border, #ccc)`               | `var(--site-color-border)` — every token has a value                        |
| `@media (min-width: 40rem)`                    | `@container (min-width: 40rem)`                                             |

Three prefixes, each for its own thing — a fourth one is a mistake, and so is mixing them up,
because the check tells them apart by the prefix:

| Prefix     | What it is                                                               | Rule                                                                                            |
| ---------- | ------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------- |
| `--site-*` | a token of the site's vocabulary: its value comes from the theme         | only names the chain knows, never a fallback; a new name goes under `vocabulary` in tokens.json |
| `--webx-*` | a package's own variable, a widget's or a module's (`--webx-slider-gap`) | declared by the package on `--site-*`; a theme overrides it to tune that one widget             |
| `--wx-*`   | the panel and the core components                                        | never on the public site: it is not loaded there, and `var(--wx-…)` is silently empty           |

A block's own variable (`--team-columns`, set inline from a field) is none of these: it lives under
`.b-<slug>` and may have a default.

`tokens.json` is the one place a literal value belongs. `php artisan webx:theme:check` reads the
whole chain — every layer's `tokens.json` and `src/css`, the CSS of the modules and the widgets —
and prints a line per finding (file, line, what, why), exiting non-zero; run it before saying the
theme is done.

### A site without a theme

`php artisan webx:setup --no-theme` writes the layout, the header and the footer into
`resources/views/components/`, styled by about eighty lines of `<style>` in the layout so that the
first pages are readable without a build. The day the real design starts, delete that block,
uncomment the `@vite([...])` line under it and write the design in `resources/css/app.css`. Its
variables are your own; the `--site-*` tokens and the `--wx-*` variables of the panel are not
loaded on such a site.

### A module's page

A module prints its public pages with views of its own, with semantic markup and classes that are
its public contract — listed under «Classes» in the module's `AGENTS.md`. On a site with a theme
the module also brings its **base look**: `resources/theme/*.css` on the site tokens, so a form of
the Inbox or the trail of SEO looks decent under any theme from the first page.
`php artisan webx:theme:sync` writes the base look of every installed module into one file,
`public/themes/modules/<hash>/modules.css`, and `@webxTheme` links it **under** the theme: the
theme restyles a module by writing the same class, later in the cascade.

Four doors, the cheapest first:

1. **Tokens** — a module's base look follows `theme/tokens.json` like everything else.
2. **Style it from `theme/src/css`** by its classes — nothing to publish, nothing to keep up to
   date. Not by tags and not by nesting: the markup inside may change, the classes will not.
3. **Add a piece at an extension point** — where a module's view calls `@webxPart('recipe-card', …)`,
   the theme puts `theme/views/parts/recipe-card.blade.php` and it is printed instead of the
   module's partial (a card customised in the panel still wins).
4. **Change its markup**: copy the views into the site and edit the copy.

```bash
php artisan vendor:publish --tag=webx-blog-views
```

The copies land in `resources/views/vendor/` and win over the package's. **Keep only the file
you changed and delete the rest**: the ones you did not touch then keep arriving fresh with each
release, and the copies you kept get no fixes. The tag is `webx-<module>-views` —
`webx-pages-views`, `webx-blog-views`, `webx-catalog-views` and so on; a module without public
pages has none.

Which modules have their base look today:

| Module             | Its pages and parts                            | Classes                                              | Extension points |
| ------------------ | ---------------------------------------------- | ---------------------------------------------------- | ---------------- |
| `module-inbox`     | the form — fields and messages are the widgets | `webx-inbox-form*` (+ `webx-field-*`, `webx-form-*`) | —                |
| `module-seo`       | the trail, the page FAQ, interlinking          | `webx-breadcrumbs*`, `webx-faq*`, `webx-links*`      | —                |
| `module-blog`      | the feed, a rubric, a tag, an article          | `webx-blog-*`                                        | `blog.card`      |
| `module-services`  | the index, a category, a service               | `webx-services-*`                                    | `services.card`  |
| `module-menu`      | `<x-webx-menu::menu>`                          | `wx-menu*`                                           | —                |
| `module-events`    | the index, a category, an event                | `wx-events*`, `wx-event*`                            | `events.card`    |
| `module-vacancies` | the index, a vacancy                           | `wx-vacancies*`, `wx-vacancy*`                       | `vacancies.card` |
| `module-press`     | an outlet's page                               | `wx-press-outlet*`, `wx-press-article*`              | —                |
| `module-recipes`   | the index, a category, the catalogue, a recipe | `wx-recipes*`, `wx-recipe*`                          | `recipe-card`    |

A module whose classes shipped as `wx-*` keeps them: classes are a contract, and renaming them
would break the themes already written. A module that prints a style of its own inline — the
lists of events and vacancies and the catalogue of recipes do, for a site without a theme — prints
none under a theme, so the theme's stylesheet has the last word. The catalogue of a shop comes with
the themes of shops (the themes spec, §19).

`theme-default` prints the page FAQ and the interlinking block of `module-seo` at the end of every
page's main column: the page's own content first, the questions next, the links last. Both are off
until a project turns them on (`WEBX_SEO_FAQ`, `WEBX_SEO_LINKS`) and print nothing on a page that
has none; a theme that wants them elsewhere overrides `components/layout`.

### Icons

An icon on the public site comes from one sprite — the `icons/` folders of every theme layer put
together by `php artisan webx:theme:sync`, the higher layer winning by file name:

```blade
<x-theme::icon name="phone" />
<x-theme::icon name="arrow-right" class="b-hero__arrow" />
<x-theme::icon name="star" label="Favourite" />
```

An icon is one letter wide and the colour of the text around it, so a preset or the accent
repaints it with the words; give its class a `width` and `height` for another size. It is hidden
from a screen reader unless it has a `label`. `webx-ui/theme-default` brings 27 — arrows and
chevrons, check, close, plus and minus, menu, search, phone, mail, map pin, clock, calendar, user,
share, link, download, globe, info, alert, star, play — and shows them on the starter site under
Kitchen sink › Icons.

- **Another icon, or another drawing of one of the theme's:** `theme/icons/<name>.svg` — lowercase
  and dashes, a `viewBox`, `currentColor` wherever the file paints — then `webx:theme:sync`.
- **Not:** an `<svg>` pasted into a template, nor a colour written into the file. No layer above
  can redraw the first, no preset repaints the second; `webx:theme:check` lists both, and an icon a
  template names that no layer has (the page shows an empty space in its place).

### Assets

A picture that belongs to the theme rather than to the content — a background, a decoration, a
placeholder — lives in the `assets/` folder of a theme layer, and a template asks for it by its
path there:

```blade
<img src="{{ theme_asset('img/wave.svg') }}" alt="">
```

The highest layer that has the file answers, so the site's own theme replaces a picture of
`webx-ui/theme-default` by putting a file at the same path in `theme/assets/`. `php artisan
webx:theme:sync` publishes the files to `public/themes/`, in a folder named by their content: the
address changes when the file does, and the server caches `/themes/` forever. The starter site
shows one picture twice — as it is and as a mask over the accent — under Kitchen sink › Assets.

- **A new picture:** `theme/assets/img/<file>`, then `webx:theme:sync` — until then the page
  prints an empty address.
- **Not:** a file under `public/` for what is part of the look. A layer above cannot replace it,
  and the browser keeps the old one after you change it. A photo the owner changes goes in the
  media library, not in the theme. `webx:theme:check` names a `theme_asset('…')` no layer has.

### Fonts

The default theme draws its text in Inter, and the font's files are the theme's own, under
`assets/fonts/`. No page asks a font service for anything, so no visitor's address goes to a third
party — for a site in the EU that would be a question of consent. The font is cut by script, and
the theme's manifest says which file covers which:

```json
"fonts": [
  {
    "family": "Inter",
    "weight": "100 900",
    "style": "normal",
    "files": {
      "latin": "fonts/inter-latin.woff2",
      "latin-ext": "fonts/inter-latin-ext.woff2",
      "cyrillic": "fonts/inter-cyrillic.woff2"
    }
  }
]
```

`@webxTheme` prints an `@font-face` for each file with the range of letters of its script, and a
browser downloads a file only when the page has a letter in that range: an English page takes the
Latin file alone, a Polish one adds Latin Extended for `ą` and `ł`, a Russian one adds Cyrillic.
Kitchen sink › Fonts on the starter site draws a line in each script, and one in Greek, which
Inter has no file for here — that is what the fallback looks like.

- **A font of your own:** its `.woff2` files in `theme/assets/fonts/`, one per script
  (`latin`, `latin-ext`, `cyrillic`, `cyrillic-ext`, `greek`, `greek-ext`, `vietnamese` — the
  way Google Fonts and Fontsource cut them, so a download from either fits), `fonts` in
  `theme/theme.json` as above, the family at the head of `font-body` or `font-heading` in
  `theme/tokens.json`, and `php artisan webx:theme:sync`. A family the site declares replaces the
  same family of the theme below, so Inter with Greek added is `"family": "Inter"` with all four
  files. Keep the font's licence beside the files.
- **Which scripts:** each language of the site needs `latin`, and some one more — `ru` and `uk`
  `cyrillic`, `pl`, `tr`, `cs` `latin-ext` ([Languages](./languages.md#scripts-and-fonts)).
  `webx:theme:check` compares the files with the site's languages and names a script that has no
  file; `webx:doctor` warns the same on a deploy after a language was added in the panel.
- **Not:** a `<link>` to Google Fonts or another CDN, and no `<link rel="preload">` for a font —
  a preload downloads the file on every page, whatever its language.

### Words of the layout

<!-- v-pre: the mustaches below are Blade, and Vue would try to read them. -->

::: v-pre

The few words a theme prints itself — "Skip to content", the labels of the contact block, the page
for a missing address — come from `lang/<locale>/theme.php` of a theme layer, never from the
template: `{{ __('theme::theme.shell.skip') }}`. `webx-ui/theme-default` has every one of them in
the ten languages of the panel (en, ru, uk, de, fr, es, it, pl, pt, tr) and lists them in `locales`
of its manifest; everything else a visitor reads comes from a field, the settings or a module.

:::

- **Another word for one of the theme's:** the same key in `theme/lang/<locale>/theme.php` — only
  that key; the rest still comes from below.
- **A new word of your own:** the key in `theme/lang/en/theme.php` and in every language of
  `locales` in `theme/theme.json`; `webx:theme:check` names a key a listed language lacks, or has
  and `en` does not.
- **Not:** a word written into a template, an `aria-label` or an `alt`. It stays English on every
  other language of the site.

### Letters

A mail client is not a browser: it reads no CSS variable, often no `<style>`, and no file of the
theme's font. So a letter takes the values of the tokens on the server — `theme_token('color-accent')`
is the value after the layers and the preset — and writes them into `style="…"` of each element.
A preset or an accent changed in `tokens.json` repaints the letters with the pages.

Every letter of the site stands in one frame, `views/mail/layout.blade.php` of the theme: the
site's name above, the letter on a sheet, a "Sent from" line below. `webx-ui/theme-default` has
it; a module's letter asks for it by name (`@component('mail.layout', ['title' => …])`), and the
letter about a new form submission of `module-inbox` does. "Kitchen sink › Mail" on the starter
site shows that letter in the theme's frame and colours.

- **Another frame for every letter:** `theme/views/mail/layout.blade.php`, copied from the theme;
  tables, inline styles, sizes in px.
- **Another letter of a module:** `theme/views/vendor/webx-inbox/mail/submission.blade.php`,
  copied from the module.
- **Not:** `var(--site-…)`, a class or a colour written by hand in a letter — the client paints
  nothing there, or the letter stops repainting with the site. `webx:theme:check` reads the
  letters of the layers and of the modules and names both.

### Forms

Every form of the site — a request, a sign-in, a checkout — is drawn from the fields of
`webx-ui/widgets` (`<x-webx-field>`), so one set of classes paints them all: `webx-field`,
`__label`, `__control`, `__hint`, `__error`, `is-invalid`. Repaint them like any widget — tokens
first, then `--webx-field-*` on `:root`, then the same classes in `theme/src/css`; another control
of one type is `theme/views/vendor/webx-widgets/components/field/<type>.blade.php`. The classes and
the variables are listed in [Forms](./forms#the-look); "Kitchen sink › Forms › Fields" shows each
type in each state. The form around them (`<x-webx-form>`) adds `webx-form`, `__submit`, the
answer `__answer` and the refusal `__refusals`, `is-sending` and `--webx-form-*`
([The look of the form](./forms#the-look-of-the-form)); "Kitchen sink › Forms › Sending" sends one.

### Speed

The server half of a page is the packages' business ([Performance](./performance.md)); what the
browser downloads after the HTML — pictures, fonts, scripts — is the theme's. Rules 36–41 of
`RULES.md` in short:

- **Pictures.** A photo is WebP or AVIF at the width the layout draws it: a picture of the media
  library gives its preview and its file to `srcset`, with `sizes` saying how wide the cell is —
  the gallery of `webx-ui/widgets` does exactly that. A library of old JPEGs and PNGs is converted
  once with `php artisan webx:media:webp`, which rewrites every reference to the files.
- **Their size.** Every `<img>` carries `width` and `height` — of the file, or the size the layout
  draws it at — so the page keeps its place before the file arrives and the text below does not
  jump. A picture of the library knows its size; one whose size is unknown may say it under a
  condition (`@if ($picture['width']) width="…" height="…" @endif`). `webx:theme:check` names an
  `<img>` of a theme's views and block templates that has neither.
- **What loads first.** `loading="lazy"` on every picture below the first screen;
  `fetchpriority="high"` on the one picture that is the largest thing of the first screen, the
  hero. A lazy first screen gets its picture last.
- **Fonts.** `.woff2`, one file per script ([Fonts](#fonts)), as few faces as the design needs —
  a variable font is one file for all its weights. `@webxTheme` prints `font-display: swap`, so
  the text shows in the fallback until the file is there. No preload: it downloads the file on
  every page, whatever its language. The check notes a `.woff` file (a third larger) without
  failing.
- **Scripts and styles.** A widget's script and style come only with its component
  (`<x-webx-slider>`, `<x-webx-map>`): a page without a slider downloads no slider. The map and the
  video start when they come near the screen. A library of your own for one page goes into that
  page's block or widget, not into `theme/src`.
- **Measure.** The home page and the heaviest page, in the browser's performance panel or
  Lighthouse at a phone's width: the largest picture (LCP), the jumps (CLS), the bytes. A fast
  machine on the office network shows none of them.

## Blocks

A block type — its fields, its template, its CSS and its script — is made in the panel under
«Blocks» (or by an agent over MCP), and stored in the database, not in the site's files. Its
styles travel with it and reach only the pages that show it.

- Every selector starts with `.b-<slug>` (the type's slug), and sizes respond to `@container`
  rather than `@media`: a bare `h2 {}` or a media query in a block reaches the whole site.
- On a site with a theme, a block's CSS knows only tokens too ([above](#css-knows-only-tokens)):
  saving warns of a literal colour or font, a `--site-*` the theme lacks and a fallback inside one.
- Keep the site's types in git: `php artisan webx:blocks:export` writes `resources/blocks/<slug>.json`,
  `php artisan webx:blocks:import --publish` reads them back on another machine.

### Blocks of the theme

A theme carries block types too, as `blocks/<slug>.json` — the files `webx:blocks:export` writes. A
type of an upper layer replaces the one of a lower layer with the same slug, whole; its schema may
add fields but keeps every field below with the same type, or the content filled in for the lower
type is lost. The set moves only when a person says so — `webx:setup` prints the import for a dev
copy and does not run it, and a deploy, `webx:theme:sync` and `composer update` never write a block
type, so what a client changed in the panel stays:

```bash
php artisan webx:blocks:import --theme --dry-run      # what the layers would change
php artisan webx:blocks:import --theme --publish      # create the types the site lacks
php artisan webx:blocks:import --theme --replace=hero # take the layer's hero over the site's
```

| What the site has                | What import does                                                            |
| -------------------------------- | --------------------------------------------------------------------------- |
| no type by that slug             | creates it from the layer (`--publish` publishes it)                        |
| the same type                    | nothing — `unchanged`                                                       |
| a type by that slug that differs | keeps it and prints the difference; `--replace=<slug>` writes a new version |
| a type a layer stopped carrying  | keeps it — pages may stand on it                                            |

Whoever changed the site's type — the client, a module, an older release of the theme — import
does not take it over unasked: replacing is by name, one type at a time, and the version before
stays in the history. A type deleted in the panel is not brought back by anything but an import.

### The six blocks of the default theme

`webx-ui/theme-default` brings the primitives a company page is built of — the same on every new
site, restyled by the tokens, overridden by slug:

| Block        | What                                                                           | Variants                                                                   |
| ------------ | ------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| `hero`       | a headline, a line under it, one or two buttons                                | page, tint, accent, over a picture; left or centre; column or edge to edge |
| `text`       | prose in the theme's typography — headings, lists, quotes, tables              | a reading column or the whole column                                       |
| `media-text` | a picture beside a heading, a text and a button                                | picture left or right; page or tint                                        |
| `cards`      | a grid of cards — a photo or an icon, a title, a text, the card as a link      | two, three or four across at most                                          |
| `cta`        | a band asking for one thing, with one or two buttons                           | accent, tint, page with a border                                           |
| `contact`    | the contacts of Settings › Contacts and a form of the Inbox, named by its slug | contacts, a form, or both                                                  |

Every one lays itself out by its container: in a narrow column the cards go one a row and the
picture of `media-text` goes above its words, whatever the window. Their pages, every variant and
a narrow column each, are on the starter site under Kitchen sink › Blocks. Another look of one of
them is the same slug in `theme/blocks/` with every field kept — not a `hero-2`.

A type whose CSS breaks [the tokens rule](#css-knows-only-tokens) is refused by the import, not
just warned of — `webx:theme:check` lists the same findings first. The usual path of a site's own block:
make it in the panel, then `php artisan webx:blocks:export hero --path=theme/blocks` and commit —
the site's look is then all in git, not only in its database, and another copy of the site takes
it with `php artisan webx:blocks:import --theme`, once.

The rules a block type has to follow are in [Blocks](./blocks.md).

## The admin panel

The panel is the library's own look, the same on every site. A site rebrands it — colours,
density — by overriding the panel's `--wx-*` variables in a stylesheet of its own, and never by
editing the packages.

1. Create `resources/css/admin.css`:

   ```css
   :root {
     --wx-color-primary: #7c3aed;
     --wx-color-primary-hover: #6d28d9;
     --wx-color-primary-active: #5b21b6;
   }
   ```

2. Import it in `resources/js/admin.ts`, **below** the `// /webx:styles` line and outside every
   `// webx:` marker pair: `import '../css/admin.css'`.
3. Build the front end (below).

Only variables that exist do anything: a `var(--wx-…)` with a name that is not in
`node_modules/@webx-ui/tokens/dist/tokens.css` is silently empty. The list, and what each one
means, is in [Theming](./theming.md). Check the result in the dark theme too.

## Safe and not safe

| Safe to edit                                                                        | Never edit                                                                                                         |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| `theme/` — tokens, CSS, copies of views; `resources/css/` on a site without a theme | `vendor/` — `composer update` overwrites it; extend by configuration and published views instead                   |
| `resources/views/` — your own views, published module copies in `vendor/` under it  | `node_modules/` — `npm install` overwrites it; rebrand the panel through `--wx-*` instead                          |
| `public/` — images, icons, fonts                                                    | `public/build/` and `public/hot` — written by the build                                                            |
| `resources/js/admin.ts` **outside** the `// webx:` markers                          | the `// webx:imports`, `// webx:styles`, `// webx:modules` regions — `php artisan webx:panel --sync` rewrites them |
| block types, in the panel or over MCP                                               | a copy of a module in the site (a fork) — it stops getting fixes; patch and configure instead                      |
| `AGENTS.md` under `## This project`                                                 | `AGENTS.md` between `<!-- webx:agents -->` markers — rewritten on every `webx:panel --sync`                        |

## Seeing the change

| What changed                                | How to see it                                                                                                                                              |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A view (`.blade.php`), the inline `<style>` | reload the page                                                                                                                                            |
| `theme/src`, `admin.css`, `admin.ts`        | the development server rebuilds as you save: `npm run dev` on a local site, the `vite` container under [Docker](./docker.md). Without it — `npm run build` |
| A block type                                | its preview in the panel, then publish it                                                                                                                  |
| An icon in `theme/icons/`                   | `php artisan webx:theme:sync`, then reload                                                                                                                 |
| A picture in `theme/assets/`                | `php artisan webx:theme:sync`, then reload                                                                                                                 |
| A font in `theme/assets/fonts/`             | `php artisan webx:theme:sync`, then reload                                                                                                                 |
| Anything, on the server                     | `npm run build` (in Docker: `docker compose up -d --build`)                                                                                                |

Not changing? `php artisan view:clear` clears cached views, and `php artisan webx:doctor` says
when the built front end is older than its sources ("has changed since the bundle was built").

## Checklist for an agent

1. Decide which look the request is about: the public site, a block, or the panel.
2. Edit only what the "safe" column lists. Never `vendor/`, `node_modules/` or a `// webx:`
   region; never copy a module into the site.
3. To change a module's page, try `theme/src/css` first; publish its views only when the markup must
   change, and delete every published file you did not edit.
4. Use only variables that exist: `--site-*` on the public site, `--wx-*` from `tokens.css` in the
   panel. In a block, prefix every selector with `.b-<slug>` and use `@container`.
5. Show the change: reload, or `npm run build`; run `php artisan webx:doctor` and, on a site with
   a theme, `php artisan webx:theme:check` and the theme's test (`php artisan test`, the suite
   `Theme`). Read `php artisan webx:theme:show` before step 2 on a site with a theme: it names the
   layer that owns each token and view. The 41 rules a theme keeps are in
   `vendor/webx-ui/themes/RULES.md`.
6. Look at a phone width as well as a wide one; for the panel, the dark theme too.
7. Commit what the site owns: `theme/`, `resources/`, `public/` (not `public/build`, `public/themes`), `config/`, and the
   block types after `php artisan webx:blocks:export` (on a site with a theme,
   `--path=theme/blocks`).
