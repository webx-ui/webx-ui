# webx-ui/themes

The theme engine: it resolves the site's theme chain — `resources/views`, the local `theme/`, the
packaged themes it `uses`, then the modules — into the order Blade looks views up in. It knows no
module names and draws nothing itself; the look comes from the themes (`webx-ui/theme-default`
and the site's `theme/`).

## What it owns

- **Config** `webx-themes.theme` (`WEBX_THEME`): the top of the chain — `theme` (a directory of
  the site with `theme.json`) or a package name. Empty means no theme.
- **`ThemeManifest`** — a theme's description: `extra.webx.theme` of a `"type": "webx-theme"`
  package, or `theme` in a local `theme.json` (`title`, `uses`, `requires`, `locales`,
  `fonts`, `preset`).
- **`ThemeChain`** — the layers, top first (`app(ThemeChain::class)`).
- **`ThemeLocator`** — reference to manifest; `register($name, $path)` for a theme Composer does
  not know.
- **The preset** — `"preset": "night"` in the manifest of a layer (the highest that names one
  decides): a preset from the chain's tokens.json. A name the chain does not declare throws on the
  page and is a finding of `webx:theme:check`. There is no setting for it: the look is in git, and
  the panel does not edit it.
- **`Vocabulary`** — the token names and their types (`Vocabulary::base()`); a theme adds its own
  under `vocabulary` in tokens.json. **`Tokens`** — the merged values (`app(Tokens::class)`):
  layers bottom up → the preset; `sources()` says which layer or preset set each value.
- **`@webxTheme`** — `<style>:root { --site-… }</style>`, the fonts' `@font-face`, the widgets, the modules' base look, then
  every layer's stylesheet bottom up; nothing without a theme. **`theme_token($name, $default)`** — a merged value, for mail;
  a value that refers to another token (`var(--site-font-body)`) comes with that token's value.
- **`webx:theme:show [--json]`** — what the chain gives and where each piece comes from: the
  layers, every token with its type, value and the layer or preset that set it, every view of the
  layers with what serves it (the site, a layer, the module) and whom it hides, block types,
  icons, fonts, module stylesheets (`ThemeReport`). Run it before you change the look.
- **`webx:theme:check [path]`** — CSS knows only tokens (spec §8): the tokens.json of every
  layer, `src/css` of the layers, `resources/css` and `resources/theme` of the widgets and modules.
  A line per finding (file:line, what, why) and a non-zero exit; `Checks\CssCheck::check($css,
$vocabulary)` is the same rule for one stylesheet, and the block editor warns with it. Letters
  too (`Checks\MailCheck`): views under `mail/` of the layers and `resources/views/mail/` of the
  modules take colours from `theme_token()` in `style="…"` — `var(` or a literal is a finding.
- **`webx:theme:sync [--force] [--dry-run]`** — copies the packaged layers' `dist/` and `assets/`
  to `public/themes/` (a local theme's `assets/` to `public/themes/local-<directory>/`), the installed modules' `resources/theme/*.css` as one
  `public/themes/modules/<hash>/modules.css` (`ModuleStyles`), the layers' icons as one sprite
  (`ThemeIcons`), then runs the steps packages tag `webx-themes.sync` (`Contracts\SyncStep`):
  `module-blocks` puts the layers' block types on the site. A refused line exits non-zero.
  Without a theme it publishes only the packages below the themes (`BottomLayers`: the widgets'
  scripts, which `@webxWidgets` links on such a site).
- **Block types of a layer** — `blocks/<slug>.json` in the `webx:blocks:export` format
  (`Blocks\LayerBlocks::of($chain)`): an upper layer replaces a lower one's type with the same
  slug whole; its schema keeps every field below with the same type (`webx:theme:check` says so).
- **`webx:theme:make theme --local --uses=<vendor/name>`** — a local theme: `theme.json`, an empty
  `tokens.json`, `src/css/theme.css`, `src/js/theme.js` and a test. `webx:setup` runs it on a new site.
- **View order**: plain views — `resources/views`, then each layer's `views/`; `<ns>::` views —
  `resources/views/vendor/<ns>`, then each layer's `views/vendor/<ns>`, then the module's own.
- **Error pages**: an HTML request that ends in an HTTP error gets `errors.<status>` from that same
  order (`ErrorPages`); no such view, JSON, or a view that fails to render — Laravel's page.
- **Extension points**: `@webxPart('<point>', …)` in a module's view prints `parts/<point>.blade.php`
  from the chain of views when one has it (dots are directories), before the module's partial.
- **`Testing\ModuleThemeContract`** — a module's test: its base look knows only tokens and styles
  only the classes `### Classes` of its `AGENTS.md` lists, which its views print.
- **Words of the layout**: `__('theme::theme.<key>')` — each layer's `lang/<locale>/theme.php`
  bottom up, then the site's `lang/vendor/theme/<locale>/theme.php` (`ThemeLines`). Every
  language a layer lists in `locales` has the keys of its `en`, no more and no fewer; the check
  says which are missing or extra (`Checks\LineCheck`). A language not listed is not compared.
- **Icons**: each layer's `icons/<name>.svg` (lowercase, dashed) is a `<symbol>` of one sprite,
  the higher layer winning by name (`ThemeIcons`); sync publishes it to
  `public/themes/icons/<hash>/icons.svg`. `<x-theme::icon name="phone" />` prints
  `<svg class="theme-icon" width="1em" height="1em" fill="currentColor" aria-hidden="true">`
  with a `<use>`; `label="…"` makes it `role="img"` with that name. An unknown name or an
  unpublished sprite is an empty `<svg>` (a comment says why in debug). The check reads every
  icon file and the `<x-theme::icon>` and inline `<svg>` in the layers' views and block templates.
- **Assets**: `theme_asset('img/wave.svg')` — the URL of `assets/img/wave.svg` of the highest
  layer that has it, as sync published it (`public/themes/<name>/<hash>/assets/…`, cached
  forever). A path no layer has, or a layer not synced since it got the file, is `''` (the log
  says which in debug); the check names a literal `theme_asset('…')` no layer answers.
- **Fonts**: `fonts` in the manifest — `{ "family": "Inter", "weight": "100 900", "style":
"normal", "files": { "latin": "fonts/inter-latin.woff2", "cyrillic": "…" } }`, a file per script
  under `assets/` (`latin`, `latin-ext`, `cyrillic`, `cyrillic-ext`, `greek`, `greek-ext`,
  `vietnamese`). `@webxTheme` prints an `@font-face` per file with its script's `unicode-range`
  (`ThemeFonts`), so a page downloads only the scripts its letters need. A family of a higher
  layer replaces the same family below. The check and `webx:doctor` compare the scripts with the
  site's languages (`ru`/`uk` need `cyrillic`, `pl`/`tr` `latin-ext`; `ThemeFonts::LANGUAGES`)
  and with the layer's own `locales`; `webx:theme:check --locales=pl,ru` for other ones.

## Change it without forking

| You want                                            | Do this                                                                           |
| --------------------------------------------------- | --------------------------------------------------------------------------------- |
| Change the site's look                              | edit `theme/` — never the packaged theme in `vendor/`                             |
| Restyle a module's page                             | `theme/src/css` on its classes (its `AGENTS.md`, «Classes»)                       |
| Add to a module's page at a point                   | `theme/views/parts/<point>.blade.php`                                             |
| Override a module's page (the expensive door)       | `theme/views/vendor/<ns>/<view>.blade.php`                                        |
| Override a view for this site only, above any theme | `resources/views/…` — it always wins                                              |
| Change a word of the layout                         | `theme/lang/<locale>/theme.php`, the same key                                     |
| Add a word of the layout                            | the key in `theme/lang/en` and every language of `locales`                        |
| Stand the local theme on another theme              | `"uses": ["vendor/theme-name"]` in `theme/theme.json`                             |
| Change a colour, a font, a radius                   | `theme/tokens.json` → `defaults`                                                  |
| A token the vocabulary does not have                | `theme/tokens.json` → `vocabulary`, then `defaults`                               |
| Draw the site in another palette                    | `theme/tokens.json` → `presets`; `"preset": "<name>"` in `theme/theme.json`       |
| Another look of a block the theme brings            | `theme/blocks/<same slug>.json` — add fields, never drop one                      |
| Keep a block made in the panel in git               | `php artisan webx:blocks:export <slug> --path=theme/blocks`                       |
| Add an icon, or redraw one of the theme's           | `theme/icons/<name>.svg`, then `php artisan webx:theme:sync`                      |
| Add a picture, or replace one of the theme's        | `theme/assets/<path>`, then `php artisan webx:theme:sync`                         |
| A font of the site's own                            | `theme/assets/fonts/`, `fonts` in `theme.json`, its family in `tokens.json`, sync |
| Publish the config                                  | `php artisan vendor:publish --tag=webx-themes-config`                             |

## Do not

- Do not edit anything in `vendor/webx-ui/themes` or `vendor/webx-ui/theme-default`: the next
  `composer update` erases it. The local theme is where a site's differences go.
- Do not copy a whole packaged theme into `theme/`: copy only the files you change, or fixes to
  the rest never reach the site.
- Do not make two themes `use` each other: the site refuses to boot with the loop in the message.
- Do not write a colour or a font literally in CSS: use `var(--site-…)`, or the value stops
  following the presets and tokens.json. No fallback in `var(--site-x, …)` and no `@media` by width
  (`@container`) either; `tokens.json` is the one place a literal value goes.
- Do not invent a prefix or mix the three: `--site-*` is a token of the vocabulary, `--webx-*` a
  package's own variable (`--webx-slider-gap`), `--wx-*` the panel — never on the public site.
- Do not edit files under `public/themes/`: they are copies, and the next sync replaces them.
- Do not paste an `<svg>` into a template, nor write a colour into an icon file: put the file in
  `icons/` with `currentColor` and write `<x-theme::icon>`, or no layer above can redraw it and
  no preset repaints it. Size an icon in CSS (`width`/`height` on its class), not by attributes.
- Do not write a word a visitor reads into a template — text, `aria-label`, `alt`, `title`:
  `{{ __('theme::theme.<key>') }}`, or it stays English on every other language of the site.
- Do not load a font from Google Fonts or any other CDN, nor with `<link rel="preload">`: the
  files go in `assets/fonts/` (a CDN hands every visitor's address to a third party), and a
  preload downloads a script whatever the page's language.
- Do not write `var(` or a literal colour into a letter: mail reads no CSS variable, and a literal
  does not repaint with the site — `style="color:{{ theme_token('color-text') }}"` (rule 25).
- Do not call `View::prependNamespace()` for a theme yourself: it puts the theme above the site's
  `resources/views/vendor/<ns>`.
- Do not build a way to change the look from the panel — a setting, a field, a stored preset: the
  look is the theme's files in git, and a value in the database would silently beat what
  `tokens.json` says.

## Check your work

- `php artisan webx:theme:show` — before you start: what the chain already gives, and which
  layer owns the value you are about to change.
- `php artisan webx:theme:check` — no findings before you say the theme is done.
- `php artisan tinker` → `app(\WebxUi\Themes\ThemeChain::class)->layers` — the chain as resolved.
- Open a page: the layout and the module views come from the layer you expect.
- `php artisan tinker` → `app(\WebxUi\Themes\Tokens::class)->css()` — the tokens as the page
  gets them; a bad tokens.json throws here with the file and the token.
- `php artisan webx:theme:sync --dry-run` — what would be published, and what would happen to
  each block type of the layers.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md
