# webx-ui/cache

Cache on versioned keys: a registry of named groups of data (`menu`, `settings`, `routes`, …),
one version a group in the cache, and `remember()` on keys that carry those versions — so a
save moves a version and every entry built on the old one is a miss, on `file` and `database`
stores as well as Redis. A library with no screen: the panel's «System → Cache», the command
`webx:cache:reset` and the MCP tool `cache_reset` are `webx-ui/module-admin`'s and reset
through this registry (the screen's `cache()` reaches `admin.ts` through `webx:panel --sync`). The groups themselves are declared by the packages that own the data.

## What it owns

- **`CacheGroups`** (singleton): `declare(group, title?)`, `bumpOn(group, ...models)`,
  `bump(...groups)`, `bumpAll()`, `onReset(group, fn)`, `remember(name, depends, fn, ttl?,
localized?)`, `get(…)` / `put(…)` (its two halves), `prefetch(name, depends, localized?)`,
  `key(name, depends, localized?)`, `version(group)`, `versions(...)`, `all()`, `ids()`,
  `has(group)`, `forgetMemo(group?)`, `enabled()`, `ttl()`.
- **`CacheGroup`**: one group — `id`, `title()`, `models()`, `hooks()`.
- **Keys** `webx:ver:<group>` for the versions, `webx:<name>:<locale|->:<group>.<v>…` for the
  entries; a tail over 180 characters is hashed (the `database` store's key column is 255).
- **Config** `webx-cache` (`config/webx-cache.php`, tag `webx-cache-config`): `enabled`
  (`WEBX_CACHE_ENABLED`), `ttl` (`WEBX_CACHE_TTL`, a day).
- **Rules the registry keeps**: a version starts at a random number, never 1; inside a
  transaction a move waits for the commit, once a group, and `remember()` stores nothing; the
  versions are read once a request in one `many()`, the values `remember()` read with them, and
  the entries declared with `prefetch()` in one more; an undeclared group throws.
- **Event** `Events\GroupRead` (name, depends, source `memo`/`store`/`miss`) from `remember()` and
  `get()` — made only while somebody listens (the debug bar, `webx-ui/module-debug`).
- Groups every WebX site has, declared by their owners: `locales` (`webx-ui/localization`),
  `routes` (`webx-ui/routing`), `theme` (`webx-ui/themes`), `screens` (`webx-ui/module-admin`).

## Change it without forking

| You want                                     | Do this                                                                                                                                                                  |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Cache something the site reads a lot         | `app(CacheGroups::class)->remember('name', ['menu', 'settings'], fn () => …)` — the groups it is built from                                                              |
| The same data in every language              | `remember(…, localized: false)`                                                                                                                                          |
| An entry every page reads                    | `prefetch('name', ['x'], localized: false)` from the provider — read with the versions, one `many()` for all of them, not a query each                                   |
| A group for a module's data                  | in the provider's `register()`: `$this->app->register(CacheServiceProvider::class)`; in `boot()`: `declare('x', 'webx-x::module.title')`, `bumpOn('x', Model::class, …)` |
| A write without an Eloquent event            | `bump('x')` right after the mass `update()`, `upsert`, raw query or import                                                                                               |
| A cache that still lives on a key of its own | `onReset('x', fn () => $this->forget())` — the panel's button reaches it; drop it once on `remember()`                                                                   |
| A longer or shorter life for one entry       | `remember(…, ttl: 3600)`; the default for all — `WEBX_CACHE_TTL`                                                                                                         |
| Everything computed on every request         | `WEBX_CACHE_ENABLED=false` — for a test, never for a site                                                                                                                |
| A worker that serves many requests           | nothing under Octane (`RequestReceived` forgets the memo); elsewhere `forgetMemo()` between requests                                                                     |

## Do not

- Do not call `cache:clear` to refresh the site: it takes sessions, locks and rate limits with
  it. `bump()` the group, or «System → Cache» in the panel, moves the versions and nothing else.
- Do not use `Cache::tags()` for site data: tags work on Redis and Memcached only, and a site on
  `file` or `database` would keep yesterday's menu. The groups are the tags that work everywhere.
- Do not `Cache::remember()` site data on a key of your own: nothing resets it when the data
  changes. Go through `remember()` with the groups it depends on.
- Do not leave a mass write without its `bump()`: a reset somebody had to press is a `bump`
  somebody forgot. Find the path and give it one, with a test.
- Do not write to the cache by hand inside a transaction: the rows may still roll back and the
  entry would outlive them. `remember()` skips the store while one is open.
- Do not name or entitle a group you do not own: `declare('menu')` to depend on it is fine, the
  title and the models are the owner's. One or two groups a module; an expensive build (a
  sitemap) is a group of its own, so that it moves less often.
- Do not seed or read `webx:ver:*` directly: the registry seeds at random and reads them once a
  request; a hand-written 1 meets the keys of a version lost before it.

## Check your work

- In a test: `version('x')` before and after the write differ, or a `remember()` on the group
  computes again. A path that writes without a model — assert the same after it.
- `php artisan webx:cache:reset --group=x` (with `webx-ui/module-admin`) prints `old → new`.
- `php artisan webx:doctor` reports the store: `array` keeps nothing between requests and is a
  warning in production; `database` is swept hourly by `webx:cache:prune`.
- On the `database` store, one `SELECT … FROM cache WHERE key IN (…)` a request for the
  versions, however many groups a page depends on, and one more for everything `prefetch()`ed.
- In a test, `forgetMemo()` between «requests»: what one read is kept for it, and a test that
  travels in time or flushes the store without it reads the memo, not the store.

## Read more

- [README.md](README.md) in this directory — the whole API with examples.
- The guide: https://webx-ui.github.io/webx-ui/guide/caching
- The design and its reasons: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CACHE.md
