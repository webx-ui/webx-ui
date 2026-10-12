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
> vocabulary, `@webxTheme`, `webx:theme:sync`, `webx:theme:make --local` and `webx:theme:check` (CSS knows
> only tokens). Block types, icons,
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

## Icons

Each layer's `icons/<name>.svg` — a lowercase, dashed name, a `viewBox`, `currentColor` for its
paint — becomes a `<symbol>` of one sprite, the higher layer winning by name. `webx:theme:sync`
publishes it to `public/themes/icons/<hash>/icons.svg`; a template points at it:

```blade
<x-theme::icon name="phone" />
<x-theme::icon name="arrow-right" class="b-hero__arrow" />
<x-theme::icon name="star" label="Favourite" />
```

```html
<svg
  class="theme-icon"
  width="1em"
  height="1em"
  fill="currentColor"
  aria-hidden="true"
  focusable="false"
>
  <use href="/themes/icons/1f2de5e15fe7/icons.svg#phone" />
</svg>
```

One em and the colour of the text; a stylesheet sizes it by class. With `label` it is
`role="img"` with that name instead of `aria-hidden`. An unknown name, or a sprite sync has not
published, is an empty `<svg>` — never an exception on a page — and `webx:theme:check` names it
in the layers' views and block templates, along with an inline `<svg>` and an icon file that
paints in a literal colour. A new icon in a local theme shows after `webx:theme:sync`.

```php
use WebxUi\Themes\ThemeIcons;

app(ThemeIcons::class)->names();      // every icon of the chain
app(ThemeIcons::class)->has('phone');
```

## Assets

A picture that belongs to a theme — a background, a decoration, a placeholder — lives in the
`assets/` folder of a layer, and a template asks for it by its path there:

```blade
<img src="{{ theme_asset('img/wave.svg') }}" alt="">
```

```html
<img src="/themes/webx-ui/theme-default/3b9c0e1f2a47/assets/img/wave.svg" alt="" />
```

The highest layer with the file answers, so a local theme replaces a picture of the theme below
by putting a file at the same path. `webx:theme:sync` publishes the files of every layer — a
package's under `public/themes/<vendor>/<name>/<hash>/`, a local theme's `assets/` under
`public/themes/local-<directory>/<hash>/` — and the hash is of the content, so the server may
cache `/themes/` forever. A path no layer has, or one a layer got after the last sync, is an empty
string — never an exception on a page — and in debug the log says which; `webx:theme:check` names
a `theme_asset('…')` with a literal path no layer answers, in the layers' views and block
templates.

### Words of the layout

"Skip to content", the label of a contact block, the 404 page — the words a theme's templates
print rather than take from a field — live in `lang/<locale>/theme.php` of a layer and are read as
`theme::theme.<key>`:

```blade
<a class="site-skip" href="#content">{{ __('theme::theme.shell.skip') }}</a>
```

A key is looked up through the chain: each layer's file bottom up, then the site's
`lang/vendor/theme/<locale>/theme.php`, so a local theme renames one word by having that key
alone. A layer lists the languages it covers in `locales` of its manifest, and
`webx:theme:check` holds it to them: every listed language has the keys of the layer's `en`, no
more and no fewer — a key added in English and forgotten in Polish would print
`theme.shell.skip` on a Polish page. A language the layer does not list is not compared: it only
overrides a word or two there.

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

Values merge bottom up: the lowest layer's defaults, each layer above, then the preset the
highest layer names in its manifest (`"preset": "night"`). The look is the theme's files, in git:
nothing in the panel or the environment changes it. `vocabulary` adds names of the theme's own (`color`, `length`, `font`, `shadow`,
`number`, `time`, `easing`); the engine's names cannot be redeclared. Every value is checked
against its type — a name the vocabulary does not know or a value of the wrong shape in a
tokens.json fails at boot, and so does a preset the chain does not declare.

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
theme_token('font-heading');            // 'Inter, system-ui, …' — a reference to another token resolved
```

A letter writes these values into `style="…"`: mail clients read no CSS variable and often no
`<style>`. `webx:theme:check` reads the views under `mail/` of every layer and
`resources/views/mail/` of every installed module, and names a `var(` or a literal colour there.

## PHP

```php
use WebxUi\Themes\ThemeChain;

$chain = app(ThemeChain::class);

$chain->layers;      // list<ThemeManifest>, top first
$chain->top();       // the theme the site configured, or null
$chain->viewPaths(); // every layer's views/ directory
$chain->requires();  // the modules the chain cannot render without
```

What the chain gives and from where — `php artisan webx:theme:show` prints it, `--json` for a
script — is `ThemeReport`:

```php
$report = app(\WebxUi\Themes\ThemeReport::class)->toArray();
$report['tokens']['color-accent']; // ['type' => 'color', 'value' => '#1f5fbf', 'resolved' => null, 'from' => 'webx-ui/theme-default']
$report['views'];                  // [['view' => 'components/header.blade.php', 'from' => 'theme', 'hides' => ['webx-ui/theme-default']], …]
$report['preset'];                 // the preset a manifest names, or null
```

The token rule for one stylesheet — what `webx:theme:check` runs on every file of the chain:

```php
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Vocabulary;

foreach (CssCheck::check($css, Vocabulary::base()) as $finding) {
    $finding->line;      // where
    $finding->problem;   // CssProblem: literal colour or font, unknown token, fallback, @media by width
    $finding->message(); // what and why, in a line
}
```

## Modules in the theme

A module with public pages carries its base look in `resources/theme/*.css` — site tokens of the
engine's vocabulary and its own classes, listed under «Classes» of its `AGENTS.md`. `webx:theme:sync`
writes every installed module's stylesheets into one file, `public/themes/modules/<hash>/modules.css`
(`ModuleStyles`), and `@webxTheme` links it under the theme layers, so a theme restyles a module by
writing the same class later in the cascade. `webx:theme:check` reads the same files.

A module's own test checks the contract with a trait:

```php
use WebxUi\Themes\Testing\ModuleThemeContract;

final class ThemeContractTest extends \PHPUnit\Framework\TestCase
{
    use ModuleThemeContract;

    protected function modulePath(): string { return dirname(__DIR__); }
}
```

A theme adds to a module's page without overriding it at the module's extension points:
`@webxPart('recipe-card', …)` prints `views/parts/recipe-card.blade.php` of the first layer that has
it, before the module's partial (a point with dots is directories: `parts/catalog/card/badges`).

A theme under development that Composer does not know about can be pointed at directly:

```php
app(\WebxUi\Themes\ThemeLocator::class)->register('acme/theme-north', base_path('../theme-north'));
```

## Read more

- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_THEMES.md

## License

MIT
