# Addresses

`webx-ui/routing` is the registry of a site's public addresses: one table in which every
addressable thing holds its path, and one resolver that hands a request to whoever owns the path it
matched. A composer package with no npm half — nothing about it is a screen — and a library rather
than a section of the panel, because the public side of a site uses it with no panel in sight.

It exists because the address space is **flat**. `/about`, `/blog`, `/parts` and
`/alternator-belt-7100104` share one namespace, and uniqueness across all of it is the one thing no
single module can arrange: a module cannot see another module's addresses, and those are exactly
the ones it collides with.

```bash
composer require webx-ui/routing
php artisan migrate
```

## What an address is here

One row of `routes`:

| Column                     | What it holds                                                    |
| -------------------------- | ---------------------------------------------------------------- |
| `locale`                   | the language the address is published in                         |
| `path`                     | `about/mission` — no leading slash, lower case, `''` is the home |
| `kind`                     | `canonical` (the address it has) or `alias` (one it used to)     |
| `entity_type`, `entity_id` | what lives there                                                 |

The language is a column and the path never carries the language prefix: a site that puts one in
the address declares its routes inside `{locale?}`, exactly as `webx-ui/localization` already does,
so the registry never sees `/uk/` at either end.

There is no publication state in the registry, and that is deliberate. A draft has a row like
everything else; whether it may be shown is the entity's own business, answered by the handler. Two
copies of that state would be two copies that drift.

## Giving a model addresses

Two steps: the trait on the model, the type in a provider.

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

```php
use WebxUi\Routing\{OnConflict, RouteType, RouteTypes};
use WebxUi\Routing\Formatters\TreePath;

$this->app->make(RouteTypes::class)->register(new RouteType(
    type:       'page',            // the morph alias stored in routes.entity_type
    model:      Page::class,
    formatter:  TreePath::class,   // about/mission
    handler:    PageController::class,
    onConflict: OnConflict::Fail,  // a page's address is chosen deliberately
));
```

That is the whole integration. Saving, renaming, moving in the tree, deleting and restoring keep
the registry in step from there on, and the model answers three new questions:

```php
$page->routePath();      // 'about/mission' — what the formatter says right now
$page->routeCanonical(); // the row the registry actually holds
$page->url();            // 'https://example.test/uk/about/mission'
```

`type` is a name, not a class: it goes into Eloquent's morph map on registration, so moving `Page`
between namespaces stays a refactor instead of a migration.

## Formatters

The address is built by the type's formatter, and a formatter is a **pure function of the entity**.
Not a style rule — three things depend on it: the observer and `webx:routes:rebuild` have to
compute the same address or a rebuild silently moves half the site; a form has to be able to show
the address before the entity is saved; and changing the scheme of a type has to be one command.

| Formatter                          | Result                          | For                                      |
| ---------------------------------- | ------------------------------- | ---------------------------------------- |
| `Slug`                             | `about`                         | rubrics, categories                      |
| `TreePath`                         | `about/mission`                 | pages — the ancestors come from the tree |
| `SlugId`                           | `kak-vybrat-remen-7100104`      | articles, products                       |
| `SlugSku`                          | `hydraulic-oil-filter-46969598` | products with an article number          |
| `Prefixed('article', Slug::class)` | `article/kak-vybrat-remen`      | anything that wants a segment in front   |

Your own is one method:

```php
use WebxUi\Routing\Formatters\PathFormatter;

final class YearAndSlug implements PathFormatter
{
    public function format(Model $entity, string $locale): string
    {
        return $entity->published_at->year.'/'.$entity->getTranslation('slug', $locale);
    }
}
```

Put it on somebody else's type without touching that module — in `config/webx-routing.php`:

```php
'types' => [
    'article' => ['formatter' => App\Routing\YearAndSlug::class],
],
```

and then move the addresses that already exist:

```bash
php artisan webx:routes:rebuild --type=article
```

Nothing dies in the move: every address that changes leaves an alias behind. `--dry-run` prints
the same list and writes nothing.

## Collisions

A flat namespace means two things named the same want the same address, and each type says what to
do about it:

- `OnConflict::Fail` — the save fails validation on the slug field. Pages, rubrics, categories:
  addresses a person chose.
- `OnConflict::Suffix` — `base-2`, `base-3`, and **the suffix goes back into the entity's slug**,
  so the form shows the address the site will actually serve. Catalogue imports.

`PathRejected` is a `ValidationException`, so a form request lets it through untouched and the
message lands under the field.

## Answering a request

The registry hangs off `Route::fallback()`. A fallback is by definition tried only when nothing else
matched, which is the whole trick: a project's own `/search` wins with no registration order to
arrange, and nothing a project wrote is shadowed by anything the registry holds.

```php
use WebxUi\Routing\RouteHandler;

class PageController implements RouteHandler
{
    public function handle(Request $request, object $entity, string $tail): Response
    {
        // $tail is '' for an exact hit, and 'brands-bobcat' behind acceptsTail: true.
    }
}
```

What the resolver decides, in this order:

1. **One spelling.** A trailing slash, a capital letter or a doubled slash is a 301 to the one
   spelling, query string kept. One address, one entry in the analytics, one row in the index.
2. **Exact beats prefix.** `/about/mission` is its own page, never a tail handed to `/about`. A
   shorter row only wins when its type sets `acceptsTail: true` — which is how a catalogue category
   swallows `/parts/brands-bobcat/stock-in-stock` and how the home page, an address of `''`, does
   not swallow the site.
3. **An alias answers 301, and takes the tail with it.** A renamed category keeps its pages of
   filters.
4. **The handler decides publication.** The entity already carries that state; a draft is a 404 from
   the handler and a preview is a 200 from the same place.

Whatever runs after the handler can read what was found without asking again:

```php
WebxUi\Routing\Resolution::of($request)?->entity;
```

`module-seo` is the first taker: with the registry installed, `@webxSeo` in a template knows what
entity the page is about without being told.

## Addresses the registry must not hand out

Checked when an entity is **saved**, never when a request is resolved. By the time a request
arrives it is too late to be useful — the project's route has already won, and the editor is left
with a page that exists everywhere except on the site.

The router is asked first, so a project that adds a screen keeps the list true without editing
anything; `webx-routing.reserved` covers what no route describes (`storage`, `build`, directories
the web server serves off disk); the panel's own prefix is added at runtime from `module-admin`.

One consequence, before it surprises somebody: a fresh Laravel skeleton answers `/` with its
welcome route, so no page can take the site root until that route is gone. That is the rule
working.

## An alias is not a redirect rule

Both send a browser somewhere else, and an editor sees them side by side on the SEO section's
**Automatic** tab — read only — next to the rules they wrote by hand. They are not the same thing:

|            | Alias                           | Rule in `module-seo`                          |
| ---------- | ------------------------------- | --------------------------------------------- |
| Written by | the site, when something moved  | an editor                                     |
| Points at  | the row, so renames never chain | text, so a later rename can leave it stale    |
| Lives      | as long as the entity           | until somebody deletes it                     |
| Answers    | always 301, always exact        | 301 or 302; exact, mask or regular expression |
| Tried      | during routing                  | before routing — a rule wins                  |

A rule beating a live page is deliberate: an address that should now go elsewhere has to be
expressible. The panel warns when a rule is about to shadow a page and saves it anyway.

## Commands

```bash
php artisan webx:routes:rebuild [--type=page] [--dry-run]
php artisan webx:routes:check
```

`check` reports what no database constraint can: rows whose entity is gone, entities with no
address, aliases that lead nowhere, and addresses a project has since claimed with a route of its
own. It exits 1 when it finds anything, so a deploy can run it and stop. From an agent, the same
answers are `resolve_url`, `where_is` and `find_conflicts`.

## Importing a catalogue

```php
app(WebxUi\Routing\RouteSync::class)->bulk(Product::query()->lazy());
```

One upsert per chunk instead of one insert per row per language, with the same formatters and the
same uniqueness rules as a save through the form.

## What it does not own

Page content, publication and drafts (the entity), meta markup and hand-written redirects
([`module-seo`](./seo)), the language of a request (`webx-ui/localization`), the tree
(`webx-ui/nested-set`). And it knows no module names at all: pages, articles, categories and
products reach it as registrations, so `/article/` is a segment the news module asked for rather
than an idea this package has.
