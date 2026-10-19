# webx-ui/cache

Cache on versioned keys for a Laravel site.

Every group of data — the menu, the settings, the addresses — has a version: a counter in the
cache under `webx:ver:<group>`. An entry built from a group carries that version in its key, so
moving the version is forgetting every entry built on the old one at once. It works on every
cache store, `file` and `database` included, where Laravel's tags do not.

Part of [WebX UI](https://github.com/webx-ui/webx-ui). The admin panel's «System → Cache»
screen, the `webx:cache:reset` command and the MCP tool `cache_reset` live in
`webx-ui/module-admin` and reset through this registry; the registry itself needs no panel. The
screen's client half, `cache()` from `@webx-ui/module-admin`, is put into the site's
`resources/js/admin.ts` by `php artisan webx:panel --sync`.

## Requirements

- PHP 8.4+
- Laravel 13 (`illuminate/cache`, `illuminate/database`, `illuminate/support`)

## Install

```bash
composer require webx-ui/cache
```

The service provider is discovered. A package that declares a group registers it itself, so the
registry is one singleton before any provider boots:

```php
public function register(): void
{
    $this->app->register(CacheServiceProvider::class);
}
```

## Declaring a group

From a service provider's `boot()`: the group, its title for the panel (a translation key or the
words themselves), and the models whose every save moves it.

```php
use WebxUi\Cache\CacheGroups;

$groups = $this->app->make(CacheGroups::class);

$groups->declare('menu', 'webx-menu::module.title');
$groups->bumpOn('menu', Menu::class, MenuItem::class);
```

`bumpOn()` listens to `saved`, `deleted`, `restored`, `forceDeleted` and the nested set's `moved`.
Inside a database transaction the move waits for the commit and happens once however many models
were saved; a rollback moves nothing. `declare()` is idempotent: a package that only reads a group
declares it too, and the first title given stays.

A write with no Eloquent event behind it — a mass `update()`, an `upsert`, a raw query, an
import — says so itself:

```php
Product::query()->where('brand_id', $old)->update(['brand_id' => $new]);
$groups->bump('catalog');
```

## Reading through a group

```php
$html = $groups->remember(
    'theme.header',                  // the entry's name
    ['menu', 'settings', 'theme'],   // the groups it is built from
    fn () => view('theme::header')->render(),
);
```

The key is `webx:theme.header:<locale>:menu.<v>.settings.<v>.theme.<v>`: the language of the
request is part of it, so every language has an entry of its own. Pass `localized: false` for
data that is the same in every language, and `ttl:` for a life other than the default
(`webx-cache.ttl`, a day).

Nothing is stored while a transaction is open: the rows read inside it may still roll back. The
value is computed and returned, not kept. With `webx-cache.enabled` off, everything is computed
on every call — for a test, not for a site.

## Resetting by code

```php
$groups->bump('menu', 'seo');   // the groups named
$groups->bumpAll();             // every declared group
```

A cache that still lives on a key of its own joins a reset with a hook on its group:

```php
$groups->onReset('settings', fn () => $this->app->make(Settings::class)->forget());
```

The hook runs on a reset made by code — `bump()`, the panel's button, the command, the agent's
tool — and not when a model of the group moves it: such a cache forgets itself on its models'
events already.

## How the versions behave

- Read once a request for every declared group in one `Cache::many()`, then kept in memory;
  `forgetMemo()` drops them (a worker serving the next request, a test). What `remember()`
  read or computed is kept with them: the settings asked for ten times while a page renders are
  one read of the store.
- An entry every page reads is declared — `prefetch('settings', ['settings'], localized: false)`
  — and comes with the versions, in one more `many()` on the first read of a request: on the
  `database` store a page otherwise paid a query for each of them.
- A version starts at a random number, never at 1: a version evicted from the cache and seeded
  again at 1 would meet the entries of the old 1.
- A version lives 30 days untouched; one that expired is seeded afresh — a miss, not a stale hit.
- `bump()` is `Cache::increment()`: atomic on every store with more than one reader.
- A key longer than 180 characters hashes its tail: the `database` store's key column is 255.

## API

| Method                                                                        | What it does                                                                    |
| ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------- |
| `declare(group, title?)`                                                      | declare a group; idempotent, the first title stays                              |
| `bumpOn(group, ...models)`                                                    | declare and move on the models' events                                          |
| `onReset(group, fn)`                                                          | a hook for a reset made by code                                                 |
| `bump(...groups)` / `bumpAll()`                                               | move the versions; after the commit inside a transaction                        |
| `version(group)` / `versions(...groups)`                                      | the current versions, read once a request                                       |
| `key(name, depends, localized = true)`                                        | the key an entry built from these groups is stored under                        |
| `remember(name, depends, fn, ttl = null, localized)`                          | `Cache::remember()` on that key; nothing stored in a transaction                |
| `get(name, depends, localized)` / `put(name, depends, value, ttl, localized)` | the two halves of `remember()`, for a reader that decides what is worth keeping |
| `prefetch(name, depends, localized)`                                          | read this entry with the versions, in one call, on the first read of a request  |
| `all()` / `ids()` / `has(group)`                                              | the registry, for a screen, a command, a tool                                   |
| `forgetMemo(group?)`                                                          | forget the versions and the values read this request                            |
| `enabled()` / `ttl()`                                                         | the `webx-cache` config                                                         |

An undeclared group in `bump()`, `key()` or `remember()` throws `InvalidArgumentException`: a
typo in a group name must not pass as a group nobody resets.

## License

MIT.
