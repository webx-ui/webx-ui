# webx-ui/themes

The theme engine: it resolves the site's theme chain — `resources/views`, the local `theme/`, the
packaged themes it `uses`, then the modules — into the order Blade looks views up in. It knows no
module names and draws nothing itself; the look comes from the themes (`webx-ui/theme-default`
and the site's `theme/`). **The rules every theme keeps, numbered as the check names them, are in
[RULES.md](RULES.md) — read them before you change the look.**

## What it owns

- **Config** `webx-themes.theme` (`WEBX_THEME`): the top of the chain — `theme` (a directory of
  the site with `theme.json`) or a package name. Empty means no theme.
- **`ThemeManifest`** — a theme's description: `extra.webx.theme` of a `"type": "webx-theme"`
  package, or `theme` in a local `theme.json`: `title`, `uses`, `requires`, `locales`, `fonts`,
  `preset`, `settings`. **`ThemeChain`** — the layers, top first; **`ThemeLocator`** — reference to manifest,
  `register($name, $path)` for a theme Composer does not know.
- **The preset** — `"preset": "night"` in the manifest of a layer (the highest that names one
  decides), a preset of the chain's tokens.json; an unknown name throws on the page and is a
  finding of the check. There is no setting for it: the look is in git, the panel does not edit it.
- **`settings`** — the tabs of the site's settings the theme prints, so the panel offers them:
  `"settings": {"contacts": ["phones", "emails", "hours"]}` (a tab is `true` or the list of its
  sections), summed down the chain by `ThemeChain::settings()`. `{}` says the theme prints none;
  no key at all keeps Contacts while module-settings' transition lasts. A theme that prints
  `contacts()` declares it — otherwise the tab is hidden and nobody can fill in what it prints.
- **`Vocabulary`** — the token names and their types; a theme adds its own under `vocabulary` in
  tokens.json. **`Tokens`** — the merged values: layers bottom up → the preset; `sources()` says
  which layer or preset set each.
- **`@webxTheme`** — `<style>:root { --site-… }</style>`, the fonts' `@font-face`, the widgets, the
  modules' base look, then every layer's stylesheet bottom up (a packaged layer's `dist/theme.css`
  and `dist/theme.js` if built); nothing without a theme. **`theme_token($name, $default)`** — a
  merged value for mail, with a reference to another token resolved.
- **`webx:theme:show [--json]`** — the layers, every token with its type, value and the layer or
  preset that set it, every view of the layers with what serves it and whom it hides, block types,
  icons, fonts, module stylesheets (`ThemeReport`). Run it before you change the look.
- **`webx:theme:check [path]`** — the rules a machine can hold (RULES.md): tokens.json values, CSS
  on tokens only (`Checks\CssCheck`) in the layers, widgets and modules, block types and their
  schemas, icons and `theme_asset()` paths, words in every declared locale, fonts, letters. A line
  per finding (file:line, what, why) and a non-zero exit.
- **`webx:theme:sync [--force] [--dry-run] [--adopt=<slug>]`** — the packaged layers' `dist/` and
  `assets/` to `public/themes/`, the modules' base look as one file, the icons as one sprite, then
  the steps tagged `webx-themes.sync` (`Contracts\SyncStep`): `module-blocks` puts the layers'
  block types on the site. A refused line exits non-zero. Without a theme it publishes only the
  packages below the themes (`BottomLayers`: the widgets' scripts, which `@webxWidgets` links).
- **`webx:theme:make <name> --local [--uses=…]`** — the local theme `webx:setup` gives every site:
  `theme.json`, empty `tokens.json`, `src/css/theme.css`, `src/js/theme.js`, a test.
  **`webx:theme:make <vendor/name> [--uses=…] [--path=…]`** — a packaged theme (over
  `webx-ui/theme-default` unless told otherwise, in `packages/<name>`): the manifest in
  composer.json, empty tokens.json and `src/css`, an already built empty `dist/`, the build
  (`npm run build`), a Testbench test, a CI. Both tests are green from the first run.
- **`webx:theme:extract <vendor/name> [--path=…]`** — the local theme becomes a package: `theme/`
  moves into it, the site requires it (path repository) and `WEBX_THEME` names it.
  **`webx:theme:eject [<vendor/name>] [--path=theme]`** — the packaged theme under the local one
  folds into it (a file, token or word the site already has wins), or a site on the package itself
  takes it whole; never `theme-default`. Both compare the look before and after (`ThemeLook`),
  name every difference, and print the Composer and npm commands instead of running them.
- **`Testing\ThemeContract`** — the test of a theme (`use ThemeContract;` + `themePath()`): the
  chain builds with it on top, every token has a value, the check finds nothing, text reads in
  every preset (WCAG AA), the layout keeps `@webxTheme`, `@webxSeo`, `@webxBlocks` and the
  `header`/`footer` regions, its views compile, a packaged `dist/` is built from `src/`
  (`ThemeSources`). **`Testing\ModuleThemeContract`** — a module's base look on tokens only, on
  the classes `### Classes` of its `AGENTS.md` lists.
- **View order**: plain views — `resources/views`, then each layer's `views/`; `<ns>::` views —
  `resources/views/vendor/<ns>`, then each layer's `views/vendor/<ns>`, then the module's own.
  Error pages: `errors.<status>` from the same order (`ErrorPages`).
- **Block types of a layer** — `blocks/<slug>.json` in the `webx:blocks:export` format; a higher
  layer replaces a type by slug whole and keeps every field below with its type.
- **Extension points**: `@webxPart('<point>', …)` in a module's view prints
  `parts/<point>.blade.php` from the chain of views, before the module's partial.
- **Words of the layout**: `__('theme::theme.<key>')` — each layer's `lang/<locale>/theme.php`
  bottom up, then the site's `lang/vendor/theme/<locale>/theme.php`.
- **Icons**: each layer's `icons/<name>.svg` is a `<symbol>` of one sprite, the higher layer
  winning by name; `<x-theme::icon name="phone" />` (`label="…"` makes it `role="img"`).
- **Assets**: `theme_asset('img/wave.svg')` — the URL of the highest layer's `assets/…` as sync
  published it; a path no layer has is `''`.
- **Fonts**: `fonts` in the manifest — `{ "family", "weight", "style", "files": { "<script>":
"fonts/….woff2" } }` under `assets/`; an `@font-face` per script with its `unicode-range`. The
  check and `webx:doctor` compare the scripts with the site's languages (`--locales=pl,ru`).

## Change it without forking

| You want                                            | Do this                                                                        |
| --------------------------------------------------- | ------------------------------------------------------------------------------ |
| Change the site's look                              | edit `theme/` — never a theme in `vendor/`                                     |
| Change a colour, a font, a radius                   | `theme/tokens.json` → `defaults`                                               |
| A token the vocabulary does not have                | `theme/tokens.json` → `vocabulary` (a role name), then `defaults`              |
| Draw the site in another palette                    | `theme/tokens.json` → `presets`; `"preset": "<name>"` in `theme/theme.json`    |
| Restyle a module's page                             | `theme/src/css` on its classes (its `AGENTS.md`, «Classes»)                    |
| Add to a module's page at a point                   | `theme/views/parts/<point>.blade.php`                                          |
| Override a module's page (the expensive door)       | `theme/views/vendor/<ns>/<view>.blade.php`, copied from the current version    |
| Override a view for this site only, above any theme | `resources/views/…` — it always wins                                           |
| Change or add a word of the layout                  | `theme/lang/<locale>/theme.php` — a new key in `en` and every language         |
| Another look of a block the theme brings            | `theme/blocks/<same slug>.json` — add fields, never drop one                   |
| Keep a block made in the panel in git               | `php artisan webx:blocks:export <slug> --path=theme/blocks`                    |
| An icon, a picture, a font of the site's own        | `theme/icons/`, `theme/assets/`, `theme/assets/fonts/` + `fonts`; then sync    |
| Stand the local theme on another theme              | `"uses": ["vendor/theme-name"]` in `theme/theme.json`                          |
| Print the site's contacts from the theme            | `"settings": {"contacts": [<sections printed>]}` in `theme/theme.json`         |
| A look several sites share                          | `php artisan webx:theme:make vendor/name`, or `webx:theme:extract vendor/name` |
| Stop following a packaged theme's updates           | `php artisan webx:theme:eject vendor/name`                                     |
| Publish the config                                  | `php artisan vendor:publish --tag=webx-themes-config`                          |

## Do not

- Do not edit anything in `vendor/webx-ui/themes` or `vendor/webx-ui/theme-default`: the next
  `composer update` erases it. The local theme is where a site's differences go.
- Do not copy a whole packaged theme into `theme/` by hand: copy only the files you change, or fixes
  to the rest never reach the site. When the site really leaves the theme, `webx:theme:eject`.
- Do not write a colour or a font literally in CSS, a fallback into `var(--site-x, …)` or `@media`
  by width: `var(--site-…)` and `@container` — `tokens.json` is the one place for a literal.
- Do not invent a prefix: `--site-*` a token, `--webx-*` a package's own variable, `--wx-*` the
  panel — never on the public site.
- Do not edit `public/themes/`: copies, replaced by the next sync. Do not commit a packaged theme's
  `src/` without its rebuilt `dist/`: sites install it without Node, and its test goes red.
- Do not paste an `<svg>` into a template nor write a colour into an icon: `icons/` with
  `currentColor` and `<x-theme::icon>`, or no layer above redraws it and no preset repaints it.
- Do not write a word a visitor reads into a template — text, `aria-label`, `alt`, `title`:
  `{{ __('theme::theme.<key>') }}`, or it stays English in every other language.
- Do not load a font from a CDN nor preload one: files in `assets/fonts/` (a CDN hands every
  visitor's address to a third party; a preload downloads a script whatever the language).
- Do not write `var(` or a literal colour into a letter: mail reads no CSS variable —
  `style="color:{{ theme_token('color-text') }}"`.
- Do not make two themes `use` each other (the site refuses to boot), and do not call
  `View::prependNamespace()` for a theme yourself: it puts the theme above the site's overrides.
- Do not build a way to change the look from the panel — a setting, a field, a stored preset: the
  look is the theme's files in git, and a value in the database would silently beat tokens.json.

## Check your work

- `php artisan webx:theme:show` — before you start: what the chain gives, and which layer owns the
  value you are about to change.
- `php artisan webx:theme:check` — no findings; the theme's test (`ThemeContract`) — green.
- `php artisan webx:theme:sync --dry-run` — what would be published, and what would happen to each
  block type of the layers.
- After `extract` or `eject`: the command's last line says «The same look» — otherwise it lists
  every token, view, block, icon, asset, font or word that changed.
- Open a page in a browser, on a desktop and at 375px, in every preset: jsdom does not check layout.

## Read more

- [RULES.md](RULES.md) — the 35 rules of a theme; [README.md](README.md) — the PHP API.
- Guide: https://webx-ui.github.io/webx-ui/guide/themes
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md
