# webx-ui/routing

The registry of a site's public addresses.

One flat namespace — `/about`, `/blog`, `/alternator-belt-7100104` — one row per address, and one
resolver that hands a request to whoever owns the path it matched. Uniqueness holds across every
kind of content at once, which is the part no single module can do on its own: a module cannot see
another module's addresses, and those are exactly the ones that collide.

Part of [WebX UI](https://github.com/webx-ui/webx-ui). It knows no module names: pages, articles,
categories and products arrive as registrations.

## Requirements

- PHP 8.3+
- Laravel 13
- [`webx-ui/localization`](https://github.com/webx-ui/localization) — the languages an address is
  published in
- [`webx-ui/nested-set`](https://github.com/webx-ui/nested-set) — for tree-shaped addresses

## Install

```bash
composer require webx-ui/routing
php artisan migrate
```

## Register a type

```php
use WebxUi\Routing\{OnConflict, RouteType, RouteTypes};
use WebxUi\Routing\Formatters\{SlugSku, TreePath};

$types = $this->app->make(RouteTypes::class);

$types->register(new RouteType(
    type:       'page',            // the morph alias stored in routes.entity_type
    model:      Page::class,
    formatter:  TreePath::class,   // about/mission
    handler:    PageController::class,
    onConflict: OnConflict::Fail,  // a page address is chosen deliberately
));

$types->register(new RouteType(
    type:        'product',
    model:       Product::class,
    formatter:   SlugSku::class,   // hydraulic-oil-filter-46969598
    handler:     ProductPage::class,
    acceptsTail: true,             // /category-remeni/brands-bobcat reaches the handler as a tail
    onConflict:  OnConflict::Suffix,
));
```

## Give the model addresses

```php
use WebxUi\Localization\HasTranslations;
use WebxUi\NestedSet\HasNestedSet;
use WebxUi\Routing\HasUrl;

class Page extends Model
{
    use HasNestedSet, HasTranslations, HasUrl;

    public function translatable(): array
    {
        return ['title', 'slug'];
    }
}
```

That is the whole integration. From then on:

```php
$page->routePath();          // 'about/mission' — what the formatter says right now
$page->routeCanonical();     // the row the registry holds
$page->routes;               // every row for this entity, aliases included
$page->url();                // 'https://example.test/uk/about/mission'
```

Saving, moving in the tree, renaming, deleting and restoring keep the registry in step. A rename
leaves the old address behind as an alias that answers 301; moving a branch moves every descendant
and leaves each of them an alias too.

## Formatters

A formatter is a pure function of the entity, so a save, a preview in the form and
`webx:routes:rebuild` all produce the same address.

| Formatter                          | Result                          |
| ---------------------------------- | ------------------------------- |
| `Slug`                             | `about`                         |
| `TreePath`                         | `about/mission`                 |
| `SlugId`                           | `kak-vybrat-remen-7100104`      |
| `SlugSku`                          | `hydraulic-oil-filter-46969598` |
| `Prefixed('article', Slug::class)` | `article/kak-vybrat-remen`      |

Write your own by implementing `PathFormatter`; put it on somebody else's type with
`webx-routing.types.<type>.formatter` and run `webx:routes:rebuild --type=<type>` to move the
addresses that already exist.

## Collisions

A flat namespace means `Str::slug()` of a name is not unique, so each type says what to do:

- `OnConflict::Fail` — the save fails validation on the slug field. Pages, rubrics, categories.
- `OnConflict::Suffix` — `base-2`, `base-3`, and the suffix goes back into the entity's slug so the
  form shows the address the site will serve. Catalogue imports.

`PathRejected` is a `ValidationException`, so a form request can let it through untouched. Its
message is English, like every default a library ships: whoever opens the form translates it.

## Bulk writes

```php
app(RouteSync::class)->bulk(Product::query()->lazy());
```

One upsert per chunk instead of one insert per row per language. The same formatter, the same
uniqueness rules.

## License

MIT
