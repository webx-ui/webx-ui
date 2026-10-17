# Performance

A WebX site runs on any hosting: the database on the next server, the cache and the sessions in
that same database (`CACHE_STORE=database` in the skeleton). Every query costs a network round
trip there, so the measure of a page is **how many queries it makes and whether that number grows
with the rows** — not how fast it feels on a developer's machine, where an in-memory sqlite hides
both the network and a missing index.

This page is the rules a module keeps so that it stays fast. The cache itself is
[Caching](./caching.md); what the browser downloads after the HTML — pictures, fonts, scripts — is
the theme's ([Speed](./styles.md#speed)). The design and the numbers behind it are in
`docs/architecture/WEBX_UI_PERFORMANCE.md` and `WEBX_UI_PERFORMANCE_AUDIT.md`.

## N+1: load a list's relations with the list

Whatever the template of a list touches on a row is loaded by one query for the list, where the
list is built:

```php
$articles = Article::query()
    ->published()
    ->with(['routes', 'cover', 'rubrics.routes'])
    ->paginate(12);
```

- **Addresses are a relation.** `$record->url()` answers from a loaded `routes` relation, and
  asks the registry once per call without it. Cards, breadcrumbs, related records and the
  `ItemList` markup are lists too.
- **The ancestors of a tree in one query** — the nested set's `ancestors()` with its `routes` —
  never by walking `parent` one level at a time.
- **When you need ids, take ids.** `shownRelatedIds()`, `categoryKeys()`, `Relations::loadIds()`
  and `RelationTarget::names()` answer without loading the target models; building a picker of a
  thousand services to label a filter is the mistake they replace.
- **Heavy columns only where they are read.** A list of versions takes
  `EntityVersion::scopeWithoutPayload()`; a panel list needs no JSON body.
- **Values of blocks by type, not by value.** A field type that goes to the database for each
  value — a picture, a file, a link to a record — implements `PreloadsValues`: the renderer walks
  the whole tree of the page first (nested blocks, repeater items) and hands the type every value
  at once.
- **A sitemap through the model.** `scopeForSitemap()` loads the addresses, the cover and the
  `lastmod` of a chunk; the sitemap reads the table with `chunkById()`, never `get()`.
- **Once per request, not once forever.** A lookup a page asks for many times is held by an object
  tied to the request and forgotten on `RequestHandled` and `JobProcessing`. A static property
  with no reset serves the previous request's data under Octane and in a queue worker.

Lazy loading is not forbidden globally — `Model::preventLazyLoading()` would break sites that
build on the modules. The budgets below hold the line instead.

## Cache: versioned groups

Data every page reads goes through a group, so that a save in the panel resets it and nothing
else has to ([Caching](./caching.md)):

```php
$groups->remember('faq.sections', ['faq'], fn () => Section::query()->with('items')->get()->toArray());
```

- **A group belongs to the module whose data it is.** One or two per module, declared from
  `boot()` with the models whose saves move it: `bumpOn('faq', Section::class, Item::class)`.
- **A write without an Eloquent event says so.** A mass `update()`, an `upsert`, raw SQL, an
  import, a reorder in one statement, a command — `$groups->bump('faq')` in the same commit.
  Inside a transaction the reset waits for the commit by itself.
- **An entry every page needs is declared** with `prefetch()`, so the first read of a request
  brings it with the versions.
- **Nothing about the user in a shared entry** but the language, which is in the key already.
  Permissions, badges, "mine" are computed per request.
- **The key holds whatever else the answer depends on** — the host of the request when the value
  has an absolute address in it, a config value that changes the output.
- **Do not cache what is updated behind the group's back** — counts from a search index that a
  queue brings up to date after the reset would be stored stale under the new version.

**If somebody had to press «Reset the site cache», it is a bug.** The button, `webx:cache:reset`
and the agent's `cache_reset` are the emergency exit. The journal row says when and which group;
find the write that went past the group, give it a `bump()` and a freshness test.

## Indexes and queries

- **An index for a real query**, one `EXPLAIN` on MariaDB showed: the equality columns first, then
  the range or the order — `(deleted_at, pinned, published_at)` for the blog's feed. Not "just in
  case": every index costs every write.
- **A new index is a new migration** that says in a comment which query it serves, with its
  `down()`. A long string column takes a prefix on MySQL and MariaDB (`path(255)`); sqlite has no
  prefixes, so the migration branches on the driver. Run it on MariaDB: migrate, roll back,
  migrate again.
- **Say what an index cannot fix.** `LIKE '%x%'` over translated JSON and an order by
  `coalesce(…)` read the whole table. Fine up to about 5,000 rows; beyond that, a search engine
  (`module-catalog-manticore`) or a generated column with an index.
- **A list that grows is paginated** — public feeds and panel lists alike; the table takes
  Laravel's `->paginate()` as it is. A list with a hand-made order (services, FAQ) cannot be: a
  reorder writes the whole visible order. Such a list must be flat in cost instead — its budget
  does not grow with the rows.
- `count()` and `exists()`, not `get()->count()`; `value()` and `pluck()` for one column;
  `whereIn` once, not `find()` in a loop.

## Budget tests

`WebxUi\Admin\Testing\CountsQueries` runs the work once and fails when it asked the database more
than the budget allows — listing every statement and calling out the repeated ones, which is where
the N+1 is:

```php
use WebxUi\Admin\Testing\CountsQueries;

final class PerformanceTest extends TestCase
{
    use CountsQueries;

    #[Test]
    public function the_index_fits_its_budget_cold_and_warm(): void
    {
        $this->seedServices();           // more rows than the page shows

        $this->cold();                   // this test's helper: an empty store and memo
        $this->assertQueriesWithin(16, fn () => $this->get('/services')->assertOk());

        $this->assertQueriesWithin(10, fn () => $this->get('/services')->assertOk());
        $this->assertNoRepeatedQueries(fn () => $this->get('/services')->assertOk());
    }
}
```

- **Every kind of public page** — the list, a category, a record, the sitemap, the feed — cold and
  warm; **every panel list, form and MCP list** warm.
- **More rows than a page shows**, with categories, covers and relations — an N+1 shows only as
  growth.
- **`assertNoRepeatedQueries` on every warm list**: it catches an N+1 whatever the count.
- **A budget is the ceiling the page fits under today, not a target.** Raising it needs a reason
  written in the test.
- The caches on, as on a site (`cache.default=array`, the packages' caches enabled), and
  `CacheGroups::forgetMemo()` between "requests" — otherwise the test reads the memo of the
  previous one.

`php artisan webx:make-module <Name>` writes `tests/Feature/Cms/<Name>ModulePerformanceTest.php`
next to the module class: a budget test and a freshness test, both incomplete until filled in, so
PHPUnit says so on every run.

## Freshness tests

For every group of the module: save through the panel's API (or the agent's tool), then open the
page — the change is there. Or compare `version('<group>')` before and after. A path that writes
without a model — a mass update, an import, a reorder — gets the same test after it.

```php
$this->get('/services')->assertOk();     // warm: the old title is in the cache now

$this->actingAs($admin, 'cms')->putJson("/api/cms/services/{$id}", [...$payload, 'title' => 'Renamed'])->assertOk();
$groups->forgetMemo();                    // the next request reads the store, not this one's memo

$this->get('/services')->assertSee('Renamed');
```

## Check your work

Before a module or a change to one is done:

- [ ] lists and cards load their relations with the list; `assertNoRepeatedQueries` is green;
- [ ] data every page reads is in `remember()` with its groups; the module's group is declared
      with its models; every write without an event calls `bump()`;
- [ ] the indexes the new queries need are there, checked with `EXPLAIN` on MariaDB;
- [ ] a list that grows is paginated, or flat in cost when its order is hand-made;
- [ ] `PerformanceTest` has a budget for every page and panel list and a freshness test for every
      group;
- [ ] the package's `AGENTS.md` names both under "Check your work".
