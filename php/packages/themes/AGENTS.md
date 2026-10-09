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
- **View order**: plain views — `resources/views`, then each layer's `views/`; `<ns>::` views —
  `resources/views/vendor/<ns>`, then each layer's `views/vendor/<ns>`, then the module's own.

## Change it without forking

| You want                                            | Do this                                               |
| --------------------------------------------------- | ----------------------------------------------------- |
| Change the site's look                              | edit `theme/` — never the packaged theme in `vendor/` |
| Override a module's page                            | `theme/views/vendor/<ns>/<view>.blade.php`            |
| Override a view for this site only, above any theme | `resources/views/…` — it always wins                  |
| Stand the local theme on another theme              | `"uses": ["vendor/theme-name"]` in `theme/theme.json` |
| Publish the config                                  | `php artisan vendor:publish --tag=webx-themes-config` |

## Do not

- Do not edit anything in `vendor/webx-ui/themes` or `vendor/webx-ui/theme-default`: the next
  `composer update` erases it. The local theme is where a site's differences go.
- Do not copy a whole packaged theme into `theme/`: copy only the files you change, or fixes to
  the rest never reach the site.
- Do not make two themes `use` each other: the site refuses to boot with the loop in the message.
- Do not call `View::prependNamespace()` for a theme yourself: it puts the theme above the site's
  `resources/views/vendor/<ns>`.

## Check your work

- `php artisan tinker` → `app(\WebxUi\Themes\ThemeChain::class)->layers` — the chain as resolved.
- Open a page: the layout and the module views come from the layer you expect.

## Read more

- [README.md](README.md) in this directory — the PHP API.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md
