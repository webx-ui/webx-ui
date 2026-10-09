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
- **`@webxTheme`** — `<style>:root { --site-… }</style>` and every layer's stylesheet; nothing
  without a theme. **`theme_token($name, $default)`** — a merged value, for mail.
- **`webx:theme:sync`** — copies the packaged layers' `dist/` and `assets/` to `public/themes/`.
- **`webx:theme:make theme --local --uses=<vendor/name>`** — a local theme: `theme.json`, an empty
  `tokens.json`, `src/css/theme.css`, `src/js/theme.js` and a test. `webx:setup` runs it on a new site.
- **View order**: plain views — `resources/views`, then each layer's `views/`; `<ns>::` views —
  `resources/views/vendor/<ns>`, then each layer's `views/vendor/<ns>`, then the module's own.

## Change it without forking

| You want                                            | Do this                                               |
| --------------------------------------------------- | ----------------------------------------------------- |
| Change the site's look                              | edit `theme/` — never the packaged theme in `vendor/` |
| Override a module's page                            | `theme/views/vendor/<ns>/<view>.blade.php`            |
| Override a view for this site only, above any theme | `resources/views/…` — it always wins                  |
| Stand the local theme on another theme              | `"uses": ["vendor/theme-name"]` in `theme/theme.json` |
| Change a colour, a font, a radius                   | `theme/tokens.json` → `defaults`                      |
| A token the vocabulary does not have                | `theme/tokens.json` → `vocabulary`, then `defaults`   |
| Another palette the site can switch to              | `theme/tokens.json` → `presets`; `WEBX_THEME_PRESET`  |
| Publish the config                                  | `php artisan vendor:publish --tag=webx-themes-config` |

## Do not

- Do not edit anything in `vendor/webx-ui/themes` or `vendor/webx-ui/theme-default`: the next
  `composer update` erases it. The local theme is where a site's differences go.
- Do not copy a whole packaged theme into `theme/`: copy only the files you change, or fixes to
  the rest never reach the site.
- Do not make two themes `use` each other: the site refuses to boot with the loop in the message.
- Do not write a colour or a font literally in CSS: use `var(--site-…)`, or the value stops
  following presets and the panel.
- Do not edit files under `public/themes/`: they are copies, and the next sync replaces them.
- Do not call `View::prependNamespace()` for a theme yourself: it puts the theme above the site's
  `resources/views/vendor/<ns>`.

## Check your work

- `php artisan tinker` → `app(\WebxUi\Themes\ThemeChain::class)->layers` — the chain as resolved.
- Open a page: the layout and the module views come from the layer you expect.
- `php artisan tinker` → `app(\WebxUi\Themes\Tokens::class)->css()` — the tokens as the page
  gets them; a bad tokens.json throws here with the file and the token.
- `php artisan webx:theme:sync --dry-run` — what would be published.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md
