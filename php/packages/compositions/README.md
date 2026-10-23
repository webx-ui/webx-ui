# webx-ui/compositions

Compound Blade views as trees of nodes with ids, patched by themes without copies.

A composition — the document every page is printed in, a page of a module — is a tree in JSON.
The package that introduces it gives the base tree, modules add their pieces, a theme and the
site patch the tree by id: set an attribute, add, remove, move or replace a node, for every page
or for one page type. The result is compiled into ordinary Blade views, one for each composition
and handle, with static component tags; a request merges nothing.

Part of [WebX UI](https://github.com/webx-ui/webx-ui). `webx-ui/theme-default` gives the
composition `layout` (and `layout.minimal`, `layout.blank`); `webx-ui/themes` hands over the
theme chain, so each theme's `compositions/` is a layer.

## Requirements

- PHP 8.4+
- Laravel 13 (`illuminate/console`, `illuminate/filesystem`, `illuminate/support`, `illuminate/view`)

## Install

```bash
composer require webx-ui/compositions
```

The service provider is discovered. A package that loads compositions registers it itself, so the
registry is one singleton before any provider boots:

```php
public function register(): void
{
    $this->app->register(CompositionsServiceProvider::class);
}

public function boot(): void
{
    Compositions::load(__DIR__.'/../resources/compositions');
}
```

## A base and a patch

`<package>/compositions/layout/default.json` — the base, in the bottom layer:

```json
{
  "root": {
    "id": "html",
    "tag": "html",
    "protected": true,
    "children": [
      { "id": "head", "tag": "head", "children": [{ "id": "head.stack", "stack": "head" }] },
      {
        "id": "body",
        "tag": "body",
        "attrs": { "class": "site" },
        "children": [{ "id": "content", "slot": "default" }]
      }
    ]
  }
}
```

`theme/compositions/layout/default.json` — a patch of the site's theme:

```json
{
  "attrs": { "html": { "data-webx-smooth-scroll": true } },
  "add": [{ "id": "promo", "to": "body", "first": true, "view": "components.promo" }],
  "remove": ["widgets.contact-button"]
}
```

`theme/compositions/layout/blog.article.json` — the same for one page type, over `default`.

A base may also say `vars` — the variables its leaves get, `["article", "related"]` — and
`dispatcher` — the view that includes it, `"webx-blog::article"`. They change nothing compiled:
`webx:composition:show` prints them, and `webx:theme:check` looks for a copy of the dispatcher
and notes a theme's view that reads a variable the leaves do not get.

Operations apply in a fixed order within a file — `add`, `move`, `replace`, `attrs`, `props`,
`remove` — and layer by layer: the base, the modules by id, the themes bottom up, the site's
config. A patch that names a node that is not there is skipped and logged as a finding; the page
renders.

## Rendering

A dispatcher view includes the compiled variant of its handle:

```blade
@include(composition_view('layout', $attributes->get('handle')))
```

and a module's page names its handle: `<x-layout handle="blog.article">`.

```bash
php artisan webx:composition:compile   # every composition × handle, with the findings
php artisan webx:composition:clear     # remove the compiled set
php artisan webx:composition:show layout --handle=blog.article --origin   # the final tree and who changed what
```

The set is compiled again on a request whose sources (the JSON files, their directories, the
config, `vendor/composer/installed.php`) are newer than it, while Blade checks its own views
(`view.check_cache_timestamps`).

## Config

`php artisan vendor:publish --tag=webx-compositions-config`:

- `patches` — patches above the local theme, by composition and handle;
- `layouts` — a page type to another composition: `['catalog.checkout' => 'layout.minimal']`;
- `compiled` — where the set is written (`storage/framework/compositions`);
- `check` — compare the sources on a request; `null` follows Blade.

## Testing

`WebxUi\Compositions\Testing\IsolatedCompositions` gives a test a compiled set of its own, so a
test that loads other modules does not read the set of the one before it.

`WebxUi\Compositions\Testing\ComparesHtml` — `assertSameHtml($before, $after)`: the same HTML up
to the whitespace between tags (the indent between a tag and its text counts as one space), the
measure a view turned into a composition is held to.

## License

MIT
