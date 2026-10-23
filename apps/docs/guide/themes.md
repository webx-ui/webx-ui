# Site themes

The public pages of a site — the frame of every page, the header and the footer, the typography,
the look of the blocks and of the modules' pages — are drawn by a **theme**. This page is about
the themes themselves: how a chain of them makes one look, the commands that show, check, make,
extract and eject a theme, and the test every theme runs. Which file of the site holds what is
in [Where the styles live](./styles.md).

## A chain of layers

A site stands on a chain, top first:

```
resources/views            the site's own overrides — above every theme, for the developer
theme/                     the site's local theme: only what makes this site different
acme/theme-clinic          a packaged theme several sites share (optional)
webx-ui/theme-default      the bottom: a value for every token, the shell, the six blocks
the modules                each module's own views and base look
```

A layer is a directory with the same parts, each optional — a part that is missing is inherited
from below:

| Part                         | What it is                                                                                    |
| ---------------------------- | --------------------------------------------------------------------------------------------- |
| `theme.json` / composer.json | the manifest: `title`, `uses`, `requires`, `locales`, `fonts`, `preset`, `settings`           |
| `tokens.json`                | `defaults`, `presets`, `schemes.dark` and the layer's own `vocabulary`                        |
| `src/css`, `src/js`          | the stylesheet (and, rarely, a script) on `var(--site-…)` only                                |
| `views/`                     | Blade views: the header, the footer, an override of a module's view                           |
| `compositions/`              | patches of the layout and the other compound views, by id ([Compositions](./compositions.md)) |
| `blocks/`                    | block types by slug, in the format `webx:blocks:export` writes                                |
| `icons/`, `assets/`          | the sprite's icons, pictures and fonts for `theme_asset()`                                    |
| `lang/<locale>/theme.php`    | the words of the layout                                                                       |

A **local** theme is `theme/` inside the site; its manifest is `theme/theme.json`, and the site's
Vite builds its `src/`. A **packaged** theme is a Composer package of `"type": "webx-theme"`; its
manifest is `extra.webx.theme` of its composer.json, and its `dist/` is built and committed, so a
site installs it without Node.

`WEBX_THEME` in `.env` names the top of the chain — `theme` for a local theme, a Composer name for
a packaged one; every layer below comes from the `uses` of the one above it.

### What the theme prints from the settings

A theme that prints the site's contacts says so under `settings` of its manifest, and the panel
offers the «Contacts» tab only then — with the sections the theme prints:

```json
"settings": { "contacts": ["phones", "emails", "addresses", "hours", "socials"] }
```

A tab is `true` (the whole tab) or the list of its sections — `phones`, `emails`, `addresses`,
`hours`, `messengers`, `socials`. Down the chain the layer nearest to the site that names a tab
decides it: a local theme over `theme-default` that lists its own sections replaces the default's
list — a studio with no office and no opening hours declares
`"settings": {"contacts": ["emails", "messengers", "socials"]}` and the editor is not asked about
addresses, hours, special dates or the time zone; a local theme that says nothing keeps what the
default prints. Packages and the project still add their sections on top. `"settings": {}` says
the theme prints none of them. A manifest with no `settings` key at all keeps the tab while the transition of
module-settings lasts (until the next minor) — declare it. `webx:theme:eject` carries the
package's declaration into `theme/theme.json`. More in [Settings](/guide/settings#tabs-only-where-something-uses-them).

## The dark scheme

A theme may give the same tokens a second set of values, which the visitor sees when their system
is dark or when the site set `data-theme="dark"` on `<html>`. It lives in `tokens.json`, beside
`defaults`:

```json
{
  "defaults": { "color-bg": "#f7f7f4", "color-text": "#121413", "color-accent": "#2e49f5" },
  "schemes": {
    "dark": { "color-bg": "#0d0f0e", "color-text": "#ecede8", "color-accent": "#8394ff" }
  }
}
```

`schemes` has only `dark` — the light scheme is `defaults`. The names are those of the
vocabulary, each value of its token's type. The set merges up the chain like `defaults`: a local
theme retunes one dark token of the theme below without repeating the rest, and a token with no
dark value keeps its light one in the dark. A preset recolours the light scheme only.

With a dark set, `@webxTheme` prints, after the light `:root`:

```css
:root {
  /* light values */
  color-scheme: light dark;
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme='light']) {
    /* dark values */
  }
}
:root[data-theme='dark'] {
  /* dark values */
  color-scheme: dark;
}
:root[data-theme='light'] {
  color-scheme: light;
}
```

Without one it prints exactly what it always did. That `@media` is the only one by scheme on the
page: the theme's CSS does not write `prefers-color-scheme` (`webx:theme:check` says so), and
`light-dark()` in a token is a workaround, not the way — its contrast is not measured, and the
engine sets neither `color-scheme` nor `data-theme` for it.

The contract is the attribute: `data-theme="dark"` or `"light"` on `<html>` overrides the system,
no attribute follows it. The switch is the site's to draw — there is no widget for it. A button in
the theme's header, and its script in `theme/src/js/theme.js`:

```html
<button type="button" class="site-scheme" data-scheme-toggle aria-pressed="false">Dark mode</button>
```

```js
const root = document.documentElement
const isDark = () =>
  root.dataset.theme
    ? root.dataset.theme === 'dark'
    : matchMedia('(prefers-color-scheme: dark)').matches

for (const button of document.querySelectorAll('[data-scheme-toggle]')) {
  button.setAttribute('aria-pressed', String(isDark()))
  button.addEventListener('click', () => {
    const scheme = isDark() ? 'light' : 'dark'
    root.dataset.theme = scheme
    button.setAttribute('aria-pressed', String(scheme === 'dark'))
    try {
      localStorage.setItem('site-scheme', scheme)
    } catch {
      // Private mode or blocked storage: the choice lasts until the page is left.
    }
  })
}
```

The remembered choice has to be on `<html>` before the first paint, or a dark visitor sees the
light page flash first: a module script runs too late. So a few lines go inline at the top of
`<head>` of the layout, above `@webxTheme` — a patch of the [composition](./compositions.md)
`layout`, not a copy of the layout:

```json
{
  "add": [
    {
      "id": "scheme.early",
      "to": "head",
      "first": true,
      "html": "<script>try{const s=localStorage.getItem('site-scheme');if(s==='dark'||s==='light')document.documentElement.dataset.theme=s}catch{}</script>"
    }
  ]
}
```

in `theme/compositions/layout/default.json`. The layout keeps `@stack('head')` as well, but it
stands after the stylesheets; for this script the top of `<head>` is the place.

The theme's test measures the contrast of rule 29 in the dark scheme too (`dark scheme`,
`preset warm, dark scheme`), a dark token left unset counting with its light value;
`webx:theme:check` says the same as notes, and notes a layer that recoloured a token in its
`defaults` while the dark value of it still comes from a layer below. `webx:theme:show` gives each
token its dark value and the layer it comes from, or «as light». Letters stay light:
`theme_token()` returns the light value.

## Four doors, cheapest first

A change goes through the cheapest door that solves it:

1. **A token** — a value in `theme/tokens.json`. Every page, block and module page follows, in
   every preset.
2. **CSS on the classes** — `theme/src/css/theme.css`, on the classes the shell (`site-*`) and the
   modules (`webx-<module>-*`, listed in each module's `AGENTS.md`) print.
3. **An extension point** — `theme/views/parts/<point>.blade.php` for a point a module's view
   offers with `@webxPart` (`parts/blog/card.blade.php`) — or **a patch of a composition**,
   `theme/compositions/layout/default.json`, for the layout: an attribute, a piece gone, moved or
   added, by id ([Compositions](./compositions.md)).
4. **An override of a view** — a copy of the current version in `theme/views/…`, changed the
   least. The expensive door: the copy stops getting the module's fixes.

The 35 rules of a theme — where to work, tokens, where each style lives, blocks, views, languages
and fonts, accessibility, what to check before saying it is done — are in the engine's
[`RULES.md`](https://github.com/webx-ui/webx-ui/blob/main/php/packages/themes/RULES.md). The check
and the test name a rule by its number.

## The commands

| Command                                  | What it does                                                                                                                      |
| ---------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `webx:theme:show [--json]`               | the layers, every token with the layer or preset that set it, every view with what it hides, blocks, icons, fonts                 |
| `webx:theme:check [path]`                | the rules a machine can hold; a line per finding (file:line, what, why), non-zero exit                                            |
| `webx:composition:show [name]`           | the compositions, or the final tree of one with who added, moved, replaced or removed each node (`--origin`, `--json`, `--blade`) |
| `webx:theme:sync [--dry-run]`            | publishes `dist/`, `assets/`, the sprite and the modules' base look; never writes a block type                                    |
| `webx:theme:make theme --local --uses=…` | the local theme `webx:setup` gives every new site                                                                                 |
| `webx:theme:make vendor/name [--uses=…]` | a packaged theme in `packages/<name>`, over `webx-ui/theme-default` unless told otherwise                                         |
| `webx:theme:extract vendor/name`         | the site's local theme becomes a package the site then stands on                                                                  |
| `webx:theme:eject [vendor/name]`         | a packaged theme becomes part of the site's local theme; its updates stop                                                         |

`show` is what to read before a change: it says which layer owns the value you are about to
change. `check` is what to run before saying the change is done.

## The test of a theme

Every theme has a PHPUnit test that runs the engine's contract:

```php
use WebxUi\Themes\Testing\ThemeContract;

final class ThemeTest extends TestCase
{
    use ThemeContract;

    protected function themePath(): string
    {
        return dirname(__DIR__);
    }
}
```

`make` writes it for both kinds — the local theme's test runs with the site's (`php artisan
test`), the package's on Testbench (`vendor/bin/phpunit`) — and it is green on the theme `make`
just wrote. It holds the theme to:

- the manifest reads and the chain builds with this theme on top;
- every token of the vocabulary has a value somewhere in the chain;
- `webx:theme:check` finds nothing: tokens, the preset, stylesheets, block types and their
  schemas, icons, assets, words in every declared language, fonts, letters;
- text reads in every preset — WCAG AA, 4.5:1 for `text` on `bg` and `surface`, `text-muted` on
  both, `accent-contrast` on `accent`, `accent` on `bg` (hex colours; another notation is left to
  the eye);
- the layout of the chain keeps `@webxTheme`, `@webxSeo`, `@webxBlocks` and the `header` and
  `footer` regions — in the final tree of the composition `layout`, whose patches of the theme
  apply with no finding;
- the theme's views compile (a component of a module not installed where the test runs is not
  the theme's fault and is skipped);
- a packaged theme's committed `dist/` is built from its current `src/`.

The first red line is the first rule a change broke.

## A packaged theme

`php artisan webx:theme:make acme/theme-clinic` writes `packages/theme-clinic/`:

```
composer.json        "type": "webx-theme", the manifest under extra.webx.theme
tokens.json          {"defaults": {}} — every value comes from below until you set one
src/css/theme.css    empty, with the doors in a comment
dist/                theme.css and sources.json, already built — the test is green at once
package.json         npm run build — Vite builds src/ into dist/ and writes sources.json
vite.config.js
tests/ThemeTest.php  the contract, on Testbench
phpunit.xml.dist
.github/workflows/theme.yml   dist/ is rebuilt and unchanged; the contract is green
```

After every change to `src/`: `npm install && npm run build` in the package, and commit `dist/` —
the test fails while `src/` and `dist/` disagree. A site stands on the theme with
`"uses": ["acme/theme-clinic"]` in its `theme/theme.json` and the package required from Composer
(a path repository while it lives beside the site, a VCS or Packagist one when it moves out).

## Extract: a site's look becomes a theme

A design worked out on one site, and a second site wants the same. Copying `theme/` would mean
fixing every bug twice; instead the local theme becomes a package both sites stand on:

```bash
php artisan webx:theme:extract acme/theme-clinic
```

- `theme/` moves into `packages/theme-clinic/` (`--path` for another place); `theme.json` becomes
  the composer.json's `extra.webx.theme`, `uses` and the rest kept.
- The package gets what a packaged theme has: the build of `dist/`, the test on the contract, the
  CI. The local theme's own test (a test of the site) is dropped, and the stub `src/js/theme.js`
  that says nothing too.
- The site stands on the package: a path repository and `"acme/theme-clinic": "@dev"` in its
  composer.json, `WEBX_THEME=acme/theme-clinic` in `.env`, the theme's suite gone from
  `phpunit.xml`; the entries of `theme/` in `vite.config.js` are named for you to remove.
- The look of the chain is compared before and after — tokens, the winning file of every view,
  picture and icon, block types, font faces, words; any difference is listed.

Composer and npm are not run — the command ends with the steps:

```bash
composer update acme/theme-clinic
(cd packages/theme-clinic && npm install && npm run build)
php artisan webx:theme:sync
```

Until they run, the site has no theme stylesheet. The block types the theme brings stay on the
site as they are: they are the site's now, and no sync touches them. A new
`theme/` for this site's own differences is `php artisan webx:theme:make theme --local
--uses=acme/theme-clinic`.

## Eject: a site leaves a theme

A site has come to differ from its packaged theme more than it shares with it, and every update
of the theme breaks something the site overrode. It takes the theme in and stops following it:

```bash
php artisan webx:theme:eject acme/theme-clinic
```

- On a site with a local theme over the package, the package **folds into `theme/`**: a file the
  site already has wins, as it won in the chain; `tokens.json` merges by token (presets by name,
  then by token), the words of the layout by key, the manifest key by key — `uses` becomes what
  the package stood on. The package's `src/` goes under `theme/src/css/theme-clinic/` (and
  `src/js/…`), imported first in `theme.css`, where its stylesheet used to come.
- On a site that stood on the package itself, the package **becomes `theme/` whole**
  (`--path` for another directory) and `WEBX_THEME` names it.
- The look is compared before and after, as for extract.
- `webx-ui/theme-default` is never ejected: it is the value of every token and the shell every
  site stands on — override what you need of it in `theme/`, file by file.

Then `composer remove acme/theme-clinic`, `npm run build`, `php artisan webx:theme:sync` and
`php artisan webx:theme:check`. After a fold the check may ask for words: a language the site
declares in `locales` now needs every key of `en`, the package's included — before, they fell
back to English as well.

## For an agent

1. `php artisan webx:theme:show` — what the chain gives and which layer owns it.
2. The change, in `theme/`, through the cheapest door; the rules in `RULES.md`.
3. `php artisan webx:theme:check` — no findings; the theme's test — green.
   The pictures, fonts and scripts of the theme keep rules 36–41 ([Speed](./styles.md#speed)).
4. Screenshots in a browser: the home page, an inner page, each module's page — on a desktop and
   at 375px, in every preset. jsdom does not check layout.
5. The home page and the heaviest page measured at a phone's width — LCP, CLS, bytes.
6. A report to the person: what changed, through which door, which views are overridden.
