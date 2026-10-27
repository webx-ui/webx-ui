# Caching

A WebX site caches on versioned keys. Every group of data — the menu, the settings, the addresses
— has a version, a counter in the cache; an entry built from a group carries that version in its
key, so moving the version is forgetting every entry built on the old one at once. It works on
every store, `file` and `database` included, where Laravel's tags do not. The design and its
reasons are in `docs/architecture/WEBX_UI_CACHE.md`; the rest of what keeps a module fast — N+1,
indexes, budget tests — is [Performance](./performance.md). The registry is the library `webx-ui/cache`;
the screen, the command and the agent's tool that reset through it are `webx-ui/module-admin`'s.

**The rule:** a save in the panel resets its own group, and nobody ever needs the button. If
somebody had to press «Reset the site cache», a write went past the groups — a mass `update()`, a
raw query, an import — and that is a bug: find the path and give it a `bump()`, with a test.

## Reading through a group

```php
use WebxUi\Cache\CacheGroups;

$html = app(CacheGroups::class)->remember(
    'theme.header',                       // the entry's name
    ['menu', 'settings', 'theme'],        // the groups it is built from
    fn () => view('theme::header')->render(),
);
```

The key is `webx:theme.header:<locale>:menu.<v>.settings.<v>.theme.<v>`: the language of the
request is part of it, so every language has an entry of its own. Pass `localized: false` for data
that is the same in every language, and `ttl:` for a life other than the default day
(`webx-cache.ttl`).

Nothing is stored while a database transaction is open: the rows read inside it may still roll
back, and an agent's `dry_run` is exactly such a transaction. The value is computed and returned,
not kept.

## What every page reads

An entry every page asks for — the settings, the languages, the block types — is declared by its
reader, and the first read of a request brings all of them with the versions, in one more
`many()`:

```php
$groups->prefetch('settings', ['settings'], localized: false);
```

On the `database` store this is the difference between one query for the cache and one for each
entry. A name with a part only the request knows — a path, a hash — cannot be declared and is
read on its own. What `remember()` read or computed is kept for the request too: the settings
asked for ten times while a page renders are one read of the store.

## Declaring a group

A module requires `webx-ui/cache`, registers its provider from its own `register()` — so the
registry is one singleton before any `boot()` declares into it, whether the site found the package
by discovery or a test listed the providers by hand — and declares the group its data lives in
from `boot()`, with the models whose every save moves it:

```php
public function register(): void
{
    $this->app->register(CacheServiceProvider::class);
}

// in boot():
$groups = $this->app->make(CacheGroups::class);

$groups->declare('menu', 'webx-menu::module.title');
$groups->bumpOn('menu', Menu::class, MenuItem::class);
```

`bumpOn` listens to `saved`, `deleted`, `restored`, `forceDeleted` and the nested set's `moved`.
Inside a transaction the move waits for the commit and happens once however many models were
saved; a rollback moves nothing.

A write with no Eloquent event behind it says so itself:

```php
Item::query()->where('section_id', $old)->update(['section_id' => $new]);
$groups->bump('faq');
```

The catalogue has one word for both its search index and its storefront's caches:
`Catalog::touch($ids)` / `touchQuery($products)` ([The storefront's caches](./catalog#the-storefront-s-caches)).

The groups every site has are declared by their owners: `locales` by `webx-ui/localization`,
`routes` by `webx-ui/routing`, `theme` by `webx-ui/themes`, `screens` by the frame, which also
gives the four their words on the screen; the modules declare `settings`, `menu`, `blocks`,
`regions`, `seo`, `sitemap`, `snippets`, `banners` and `catalog`. A group may be
read by anybody — a theme's header depends on `menu` — but is named and given its models by its
owner only. A group with an expensive build, the sitemap, is a group of its own, so that it moves
less often.

## A cache that is not on versioned keys yet

A cache that still lives on a key of its own and forgets itself on its models' events joins the
button with a hook on its group:

```php
$groups->onReset('settings', fn () => $this->app->make(Settings::class)->forget());
```

The hook runs on a reset made by code — the button, the command, the agent's tool, an explicit
`bump()` — and not when a model of the group moves it: the cache already forgets itself then,
often more precisely than the whole group. Move the cache onto `remember()` and the hook goes.

## Resetting

Three doors, one act: the versions of the groups named move, nothing else. Sessions, locks and
rate limits stay. Every reset is a row in the journal, with who and which groups.

- **System → Cache** in the panel: the store, every group with its version and what resets it,
  «Reset the site cache» and a «Reset» per group. Behind `cache.view` and `cache.reset`; no role
  has them when the frame is installed, so only a super administrator sees the section until one
  is granted.
- `php artisan webx:cache:reset` — every group, or `--group=menu --group=seo`.
- `cache_reset` over MCP, with `dry_run` to see what would move.

## Stores and the doctor

`php artisan webx:doctor` says what the store means: `array` keeps nothing between requests and is
a warning in production; `database` works everywhere but keeps what nobody reads any more, so the
frame sweeps its expired rows hourly (`webx:cache:prune`, on the schedule — the system cron behind
`schedule:run` is needed). `WEBX_CACHE_ENABLED=false` computes everything on every request, for a
test and not for a site.

## Check your work

- Save a record in the panel, open the page that shows it: the change is there without a reset.
- In a test: `version('menu')` before and after the write differ, or a `remember()` on the group
  computes again. A path that writes without a model — assert the same after it.
- `php artisan webx:cache:reset --group=<yours>` prints `old → new` for the group.
