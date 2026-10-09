# webx-ui/themes

Site themes as layers.

A site does not copy a theme, it stands on one: its own `theme/` holds only what makes it
different, the packaged theme below it holds everything else, and the modules below both hold the
look of their own pages. An update of the packaged theme or a module reaches the site with
`composer update`, because nothing was copied.

```
resources/views          ← the site itself, always first
theme/                   ← the site's local theme: its differences
  → webx-ui/theme-default ← a packaged theme
    → modules            ← their own views
```

Part of [WebX UI](https://github.com/webx-ui/webx-ui). No tables, no JavaScript.

> Early days: this release resolves the chain and the order Blade looks views up in. Tokens,
> `@webxTheme`, assets and the `webx:theme:*` commands arrive in the next releases — see the
> specification.

## Requirements

- PHP 8.4+
- Laravel 13

## Install

```bash
composer require webx-ui/themes
```

Then name the top of the chain in `.env` or `config/webx-themes.php`:

```dotenv
WEBX_THEME=theme
```

`theme` is a directory of the site with a `theme.json`; a Composer package name
(`webx-ui/theme-default`) works too. Empty — the default — means no theme, and the package does
nothing at all.

## A theme

A packaged theme is a Composer package of `"type": "webx-theme"` that describes itself under
`extra.webx.theme`:

```json
{
  "name": "webx-ui/theme-default",
  "type": "webx-theme",
  "extra": {
    "webx": {
      "theme": {
        "title": "Default",
        "uses": [],
        "requires": ["module-pages", "module-blocks"]
      }
    }
  }
}
```

A local theme has no composer.json; it keeps the same keys under `theme` in `theme/theme.json`:

```json
{
  "theme": {
    "title": "My site",
    "uses": ["webx-ui/theme-default"]
  }
}
```

`uses` is what the theme stands on, first entry highest. A chain may be any length; a loop is an
error at boot, and so is a theme that cannot be found — a site that silently lost a layer would
render half-styled.

Views live in the theme's `views/`: `views/components/layout.blade.php` is what `<x-layout>`
renders, `views/vendor/webx-pages/show.blade.php` overrides the pages module's `show` view.

## How views are looked up

| Kind               | Order                                                                                      |
| ------------------ | ------------------------------------------------------------------------------------------ |
| `view('home')`     | `resources/views` → each layer's `views/`, top first                                       |
| `view('ns::show')` | `resources/views/vendor/ns` → each layer's `views/vendor/ns`, top first → the module's own |

The first file found wins. The chain is resolved once, after every package has booted, and never
changes in the life of the process.

## PHP

```php
use WebxUi\Themes\ThemeChain;

$chain = app(ThemeChain::class);

$chain->layers;      // list<ThemeManifest>, top first
$chain->top();       // the theme the site configured, or null
$chain->viewPaths(); // every layer's views/ directory
$chain->requires();  // the modules the chain cannot render without
```

A theme under development that Composer does not know about can be pointed at directly:

```php
app(\WebxUi\Themes\ThemeLocator::class)->register('acme/theme-north', base_path('../theme-north'));
```

## Read more

- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md

## License

MIT
