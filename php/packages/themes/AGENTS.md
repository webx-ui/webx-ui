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
  `editable`, `fonts`).
- **`ThemeChain`** — the layers, top first (`app(ThemeChain::class)`).
- **`ThemeLocator`** — reference to manifest; `register($name, $path)` for a theme Composer does
  not know.
- **Config** `webx-themes.preset` (`WEBX_THEME_PRESET`): a preset from the chain's tokens.json;
  an unknown name is ignored.
- **`Vocabulary`** — the token names and their types (`Vocabulary::base()`); a theme adds its own
  under `vocabulary` in tokens.json. **`Tokens`** — the merged values (`app(Tokens::class)`):
  layers bottom up → preset → the owner's edits of `editable` tokens (`Contracts\Appearance`).
- **`@webxTheme`** — `<style>:root { --site-… }</style>`, the widgets, the modules' base look, then
  every layer's stylesheet bottom up; nothing without a theme. **`theme_token($name, $default)`** — a merged value, for mail.
- **`webx:theme:check [path]`** — CSS knows only tokens (spec §8): the tokens.json of every
  layer, `src/css` of the layers, `resources/css` and `resources/theme` of the widgets and modules.
  A line per finding (file:line, what, why) and a non-zero exit; `Checks\CssCheck::check($css,
$vocabulary)` is the same rule for one stylesheet, and the block editor warns with it.
- **`webx:theme:sync [--force] [--dry-run]`** — copies the packaged layers' `dist/` and `assets/`
  to `public/themes/`, the installed modules' `resources/theme/*.css` as one
  `public/themes/modules/<hash>/modules.css` (`ModuleStyles`), then runs the steps packages tag `webx-themes.sync` (`Contracts\SyncStep`):
  `module-blocks` puts the layers' block types on the site. A refused line exits non-zero.
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
  bottom up, then the site's `lang/vendor/theme/<locale>/theme.php` (`ThemeLines`).

## Change it without forking

| You want                                            | Do this                                                      |
| --------------------------------------------------- | ------------------------------------------------------------ |
| Change the site's look                              | edit `theme/` — never the packaged theme in `vendor/`        |
| Restyle a module's page                             | `theme/src/css` on its classes (its `AGENTS.md`, «Classes»)  |
| Add to a module's page at a point                   | `theme/views/parts/<point>.blade.php`                        |
| Override a module's page (the expensive door)       | `theme/views/vendor/<ns>/<view>.blade.php`                   |
| Override a view for this site only, above any theme | `resources/views/…` — it always wins                         |
| Change a word of the layout                         | `theme/lang/<locale>/theme.php`, the same key                |
| Stand the local theme on another theme              | `"uses": ["vendor/theme-name"]` in `theme/theme.json`        |
| Change a colour, a font, a radius                   | `theme/tokens.json` → `defaults`                             |
| A token the vocabulary does not have                | `theme/tokens.json` → `vocabulary`, then `defaults`          |
| Another palette the site can switch to              | `theme/tokens.json` → `presets`; `WEBX_THEME_PRESET`         |
| Another look of a block the theme brings            | `theme/blocks/<same slug>.json` — add fields, never drop one |
| Keep a block made in the panel in git               | `php artisan webx:blocks:export <slug> --path=theme/blocks`  |
| Publish the config                                  | `php artisan vendor:publish --tag=webx-themes-config`        |

## Do not

- Do not edit anything in `vendor/webx-ui/themes` or `vendor/webx-ui/theme-default`: the next
  `composer update` erases it. The local theme is where a site's differences go.
- Do not copy a whole packaged theme into `theme/`: copy only the files you change, or fixes to
  the rest never reach the site.
- Do not make two themes `use` each other: the site refuses to boot with the loop in the message.
- Do not write a colour or a font literally in CSS: use `var(--site-…)`, or the value stops
  following presets and the panel. No fallback in `var(--site-x, …)` and no `@media` by width
  (`@container`) either; `tokens.json` is the one place a literal value goes.
- Do not invent a prefix or mix the three: `--site-*` is a token of the vocabulary, `--webx-*` a
  package's own variable (`--webx-slider-gap`), `--wx-*` the panel — never on the public site.
- Do not edit files under `public/themes/`: they are copies, and the next sync replaces them.
- Do not call `View::prependNamespace()` for a theme yourself: it puts the theme above the site's
  `resources/views/vendor/<ns>`.

## Check your work

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
