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

## Answering a request

The registry hangs off `Route::fallback()`, so it is asked only when nothing else matched: a
project's own `/search` wins with no ordering to arrange and nothing of its own shadowed. A handler
gets the entity already loaded and whatever was left of the path:

```php
class ProductPage implements RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): Response
    {
        // $tail is '' for an exact hit, 'brands-bobcat/stock-in-stock' behind acceptsTail: true.
    }
}
```

What the resolver decides, in order: one spelling per address (a trailing slash, a capital letter
or a doubled slash is a 301 to the canonical one, query kept); exact match beats prefix match, so
`/about/mission` is its own page rather than a tail handed to `/about`; an alias answers 301 and
takes the tail with it, so a renamed category keeps its pages of filters.

What it does not decide is **publication**: the entity already carries that state, and a second
copy of it in `routes` would be a copy that drifts. A draft answers 404 from the handler, and a
preview answers 200 from the same place.

Whatever runs afterwards can read what was found without asking again:

```php
Resolution::of($request)?->entity;
```

`webx-routing.middleware` is what the fallback route runs through — `['web', 'webx.locale']`, since
a public page needs a session and a language. `webx-routing.fallback => false` switches the route
off for a site that would rather call `Resolver::resolve()` from a route of its own.

## Reserved addresses

An address the application answers itself is refused when the entity is saved, not when the request
arrives: losing silently to a live route leaves an editor with a page that exists everywhere except
on the site. The router is asked first, so a project that adds a screen keeps this true without
editing anything; `webx-routing.reserved` covers what no route describes, and the panel's prefix
comes from `module-admin` at runtime.

One consequence worth knowing: a fresh Laravel skeleton answers `/` with its welcome route, so no
page can take the site root until that route is gone.

## Commands

```bash
php artisan webx:routes:rebuild [--type=page] [--dry-run]
php artisan webx:routes:check
```

`rebuild` recomputes every address with the formatters as they are configured now and leaves an
alias behind for each one that moves — the supported way to change the address scheme of a type,
and the repair tool when rows were written around the observer.

`check` reports what no constraint can: rows whose entity is gone, entities with no address,
aliases that lead nowhere, and addresses the project has since claimed with a route of its own. It
exits 1 when it finds anything, so a deploy can run it and stop.

## Showing the aliases to somebody

```php
use WebxUi\Routing\Aliases\RouteAliases;

app(RouteAliases::class)->search(term: 'about', perPage: 25);
```

A page of flat values — the old address, where it leads now, the entity it belongs to — and that is
deliberately all a panel gets. `webx-ui/module-seo` puts them on an **Automatic** tab beside the
redirect rules an editor wrote by hand, read only: an alias is made, repointed and removed by the
entity that moved, and a screen that could edit one would be a screen that can make the registry
disagree with the site. To give an old address a different answer, write a rule — rules are tried
before routing.

## Bulk writes

```php
app(RouteSync::class)->bulk(Product::query()->lazy());
```

One upsert per chunk instead of one insert per row per language. The same formatter, the same
uniqueness rules.

## License

MIT
