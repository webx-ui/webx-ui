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

> Early days: this release resolves the chain, the order Blade looks views up in, the token
> vocabulary, `@webxTheme`, `webx:theme:sync` and `webx:theme:make --local`. Block types, icons,
> the panel tab and the rest of the `webx:theme:*` commands arrive in the next releases — see the
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

## Tokens

A site's style is written in one vocabulary of tokens, the same for every theme, module and
block: `color-bg`, `color-accent`, `color-accent-2`, `font-heading`, `space-1`…`space-8`,
`radius-md`, `container-width`, `duration` and the rest — on the page `--site-<name>`. Each layer
gives values in its `tokens.json`:

```json
{
  "defaults": { "color-accent": "#0e6b5c", "font-heading": "\"Inter\", sans-serif" },
  "presets": {
    "night": { "title": "Night", "tokens": { "color-bg": "#111418", "color-text": "#e8eaed" } }
  },
  "vocabulary": { "color-olive": "color" }
}
```

Values merge bottom up: the lowest layer's defaults, each layer above, then the preset
(`webx-themes.preset`, `WEBX_THEME_PRESET`), then the owner's edits of the tokens the theme lists
as `editable`. `vocabulary` adds names of the theme's own (`color`, `length`, `font`, `shadow`,
`number`, `time`, `easing`); the engine's names cannot be redeclared. Every value is checked
against its type — a name the vocabulary does not know or a value of the wrong shape in a
tokens.json fails at boot, an owner's value that does not fit is dropped.

In the layout's `<head>`:

```blade
@webxTheme
```

prints one inline `<style>` with `:root { --site-… }`, then each layer's stylesheet bottom up: a
packaged layer's `dist/theme.css` from `public/themes/` (copied there by
`php artisan webx:theme:sync` — run it after `composer update`), a local layer's
`src/css/theme.css` and `src/js/theme.js` through the site's Vite. Without a theme it prints
nothing.

Where a CSS variable does not reach — mail, an inline SVG:

```php
theme_token('color-accent');            // '#0e6b5c'
theme_token('color-danger', '#e11d48'); // the default when the chain gives no value
```

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
