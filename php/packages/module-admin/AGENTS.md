# webx-ui/module-admin

The panel itself: the shell page at `config('webx-admin.path')`, the module registry and the
manifest the front end reads, screens and their patches, and the machinery other modules borrow —
drafts, versions, the change journal, notes, relations, categories, chunked uploads, the nightly
backup — plus the commands that install a site and say whether it stands up. Signing in is not
here: that is `webx-ui/module-auth`; languages are `webx-ui/localization`; the MCP server is
`webx-ui/mcp` — read their guides for those.

## What it owns

- **Shell** — every address under `webx-admin.path` (default `cms`) serves one page that mounts `#webx-app`; routing
  inside belongs to the front end. Its JSON lives under `webx-admin.api_path` (default `api/cms`): `manifest`,
  `screens/{name}`, `links/*`, `collections`, `relations/{target}`, `entities/{type}/{id}/notes`, `history/{type}/{id}`,
  `history/runs/{id}`, `uploads`, `locales`, `translations/{locale}`, `cache`. The panel's icons are served under its prefix too.
- **Auth** — none of its own (see Do not). Modules put their API behind the middleware group `webx.panel` (`web`, `webx.panel-locale`, `cms.auth`, `webx.history`).
- **Modules** — `ModuleRegistry`: a module (`AbstractModule`, `id()` required) is registered from a
  provider and lands in the manifest with its title, icon, order, group and permissions. Two modules with one id are refused.
- **Screens** — `ScreenRegistry` behind the `Screens` facade; field types in `FieldTypes`
  (`wx-input`, `wx-link`, `wx-repeater`, `wx-relations`, …), which check and cast what a screen saves.
- **Drafts and versions** — traits `HasDraft` (the `$table->draft()` macro: `draft`, `published_at`) and `HasVersions` (table `cms_versions`; `publish()` writes a version,
  `restoreVersion()` puts one back into the draft). `HasExtra` stores fields a patch adds in `extra`.
- **History** — table `cms_history`, trait `RecordsHistory`, facade `History`, types in `HistoryTypes`. With `webx-ui/mcp`: MCP tools `history_get`, `history_runs`, resource
  `history://types`, scope `history:read` (the frame registers a type of its own, so they are always there).
- **Cache** — «System → Cache» (client half `cache()` from `@webx-ui/module-admin`, wired by `webx:panel --sync`), `webx:cache:reset`, MCP `cache_reset`
  (`dry_run`), every reset journalled; the group `screens` and the words of `locales`, `routes`, `theme`.
  The registry itself (`WebxUi\Cache\CacheGroups`) is `webx-ui/cache`'s — read its AGENTS.md to cache or reset.
- **Also** — tables `cms_notes` (`HasNotes`), `cms_relations` (`HasRelations`), `cms_uploads` (chunked uploads,
  `UploadPurposes`); macros `category()`, `categoryLinks()`; `@webxPart` / `@webxPartAssets`; the site password (`CloseSite`, `Openings`).
- **Fetching an address** — `Remote\RemoteFetcher`: `get()`, `download()`, `head()`, `check()` (a pin for a client of your own); public internet only, checked after DNS and on every redirect, `FetchRefused` with a `reason`; hosts of the site's network — `WEBX_REMOTE_TRUSTED_HOSTS`, the only hosts — `WEBX_REMOTE_ONLY_HOSTS`.
- **Shortcodes** — the registry `WebxUi\Admin\Shortcodes\Shortcodes` (facade `Shortcodes`): `register()`, `source()`,
  `html()`, `htmlIn()`, `plain()`, `text()`; directives `@shortcodes`, `@shortcodesIn`, `@shortcodesPlain`; `GET /api/cms/shortcodes`
  for the panel's text fields. `wx_text($value)` makes a string or a `ShortcodeText` a `ShortcodeText`, whose `trim*`, `strip*`
  and `map()` change the typed text and stay HTML.
- **Testing** — `Testing\CountsQueries` in a Testbench case: `assertQueriesWithin(N, fn () => $this->get(…))`
  fails over the budget listing every statement, repeated ones (an N+1) called out; `assertNoRepeatedQueries()`, `recordQueries()`; `Testing\FakeHostResolver` — DNS for `RemoteFetcher` without a network.
- **Front end entry** — `resources/js/admin.ts` in the site, written from `stubs/panel.stub` (`panel-auth.stub` with
  module-auth). Three regions are the installer's: `// webx:imports`, `// webx:styles`, `// webx:modules`; the rest is the site's.

### Commands

| Command                                                                                                 | What it does                                                                                                                                                                                                                                                                                                                                                                                                                |
| ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `webx:setup`                                                                                            | database, `composer require` of the ticked modules, `webx:panel --sync`, npm build, migrate, doctor. Run it again to add a module. **Writes no block types, seeds no demo** (staging and production are made with it): prints the commands for a dev copy — `webx:blocks:import --theme --publish`, `webx:blocks:offered --install`, `webx:demo` — and the demo's missing languages (`Catalogue::demoGaps`)                 |
| `webx:panel`                                                                                            | writes the entry file; refuses if it exists unless `--sync` or `--force`; `--entry=` moves it                                                                                                                                                                                                                                                                                                                               |
| `webx:panel --sync`                                                                                     | tops up the three regions, `package.json`, the Vite input, `webx-admin.vite`, modules' `layout`, `AGENTS.md`                                                                                                                                                                                                                                                                                                                |
| `webx:doctor`                                                                                           | checks both halves, bundle, npm ranges, migrations, storage, layout, regions, languages, caches, Passport keys, site gate (a panel session passes it), mail (`Mail\MailRoutes`: a trap — `log`, a sandbox; `.env` newer than the cached config), trusted proxies (`*` warns); repairs nothing. `--strict` fails on `warn`/`fail` only, never `(note)` or `(content)` — levels in `Diagnosis`; `--json` has `ok` and `level` |
| `webx:modules`, `webx:module:add`                                                                       | list the modules a site can have; install one without the questions (`--no-build`); installs no block types — prints `webx:blocks:offered --install --module=<id>` when the module offers some                                                                                                                                                                                                                              |
| `webx:demo`                                                                                             | seed demo content; `--remove`, `--module=`, `--force`                                                                                                                                                                                                                                                                                                                                                                       |
| `webx:make-module`                                                                                      | a module class and `tests/Feature/Cms/<Name>ModulePerformanceTest.php`, its query-budget and freshness tests to fill in                                                                                                                                                                                                                                                                                                     |
| `webx:boot`                                                                                             | container start: wait for the database, migrate, cache; never writes block types (`webx:blocks:import` is run by a person)                                                                                                                                                                                                                                                                                                  |
| `webx:db:backup`, `webx:history:prune`, `webx:versions:prune`, `webx:prune-uploads`, `webx:cache:prune` | scheduled by the package itself; need the system cron behind `schedule:run`                                                                                                                                                                                                                                                                                                                                                 |
| `webx:cache:reset [--group=]`                                                                           | moves the versions of the cache groups (all, or the ones named) — never `cache:clear`; writes a journal row                                                                                                                                                                                                                                                                                                                 |
| `webx:snapshot`                                                                                         | packs content tables + the public disk into `storage/app/snapshots/<site>-<env>-<date>.tar.gz`; `--with-admins`, `--all`, `--no-media`, `--output=`                                                                                                                                                                                                                                                                         |
| `webx:snapshot:restore <archive>`                                                                       | **replaces this stand's content** with the archive's: backup first, stand-own tables untouched, addresses rewritten, files mirrored (`--keep-extra` to only add)                                                                                                                                                                                                                                                            |

### Moving content between stands

`webx:snapshot` on one stand, `scp` the archive, `webx:snapshot:restore` on the other. Every package declares its tables in `SnapshotTables` as `content` (travels), `admins`
(`--with-admins`), `stand` (enquiries, journals, tokens, audit; only `--all`), `derived` (emptied
on restore) or `transient` (sessions, cache, queues, `migrations`; never). A restore refuses an
archive with migrations this code lacks, refuses production without `--force`, and asks unless
`--no-interaction --force`. Its first line of work is a `webx:db:backup` dump — the rollback.

### The site's root `AGENTS.md`

`webx:panel` (and so `--sync`) writes a block between `<!-- webx:agents -->` and
`<!-- /webx:agents -->`: what the site is, the first rule (forms included), where its look lives
(`RootFile::STYLES_GUIDE`), a link to `vendor/webx-ui/<pkg>/AGENTS.md` for every installed `webx-ui/*` package that has one, what to run after updating. The block is replaced whole on each
run; everything outside it — the `## This project` section — is never touched. A hand-written file without markers keeps every word, with the block put on top.
`CLAUDE.md` is written once as `@AGENTS.md`; an existing one without that line only gets a warning.
Where `webx-ui/mcp` serves its HTTP endpoint (route `webx.mcp`), `.mcp.json` gets one server named after the site (`Site::name()`), at `app.url` + the route; other servers, and a key renamed over the
same address, are kept; a file that is not JSON is left alone with a warning (`Agents\McpConfig`).

### Permissions

| Permission    | Opens                                                                |
| ------------- | -------------------------------------------------------------------- |
| `cache.view`  | «System → Cache», read-only: `GET cache`, the journal rows of resets |
| `cache.reset` | the same, plus `POST cache/reset` and MCP `cache_reset`              |

Everything else here is open to every signed-in administrator and asks inside: the manifest (a
section shows to whoever holds one of its `permissions()`, or of `visibleWith()` with
`HasVisibility`; with neither — to everybody), screens (nodes behind a permission are cut out),
links, collections, relations, notes, history (`history_get`, `history_runs`), editing, uploads —
each by the permission its registered source, type, record or upload purpose names. A route or
screen node the site adds next to the module asks for its own module's: `cms.can:<id>.view,<id>.manage`
to read, `cms.can:<id>.manage` to write (next to the cache: `cms.can:cache.view,cache.reset`, `cms.can:cache.reset`).

## Change it without forking

| You want                                | Do this                                                                                                                                                                                               |
| --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| The panel at another address            | `WEBX_ADMIN_PATH` (and `WEBX_ADMIN_API_PATH` for its JSON)                                                                                                                                            |
| Another panel name or tab icons         | `WEBX_ADMIN_TITLE`; `WEBX_ADMIN_ICONS` = a directory with the same file names                                                                                                                         |
| A shortcode of the site's own (`[dot]`) | `Shortcodes::register('dot', '<span class="accent-dot">.</span>', plain: '.')` in `AppServiceProvider::boot()`                                                                                        |
| A field or tab on a module's screen     | a patch: `Screens::extend('<module>.<screen>', [...])` in `AppServiceProvider::boot()`                                                                                                                |
| A field stored without a migration      | the same patch on a model with `HasExtra`; read it with `$model->extra('<name>')`                                                                                                                     |
| A field with a value until it is set    | `"default": …` on the node: drawn in the panel and read by the site, never written by itself                                                                                                          |
| An email, address or phone field        | `wx-input` with `"props": { "type": "email" }` (or `url`, `tel`); the server holds the format too                                                                                                     |
| A screen of your own                    | `Screens::register('<module>.<screen>', $pathOrTree)` from a provider                                                                                                                                 |
| A section of your own                   | `php artisan webx:make-module <Name>`, register it in `ModuleRegistry` from a provider                                                                                                                |
| A navigation group or caption           | `groups` in `config/webx-admin.php` (`php artisan vendor:publish --tag=webx-admin-config`)                                                                                                            |
| Your own lines in the panel's JS        | anywhere in `resources/js/admin.ts` outside the three `webx:` regions                                                                                                                                 |
| Your own assets in the shell            | push onto the `webx-head` / `webx-body` stacks, or `--tag=webx-admin-views`                                                                                                                           |
| Other words in the panel                | `php artisan vendor:publish --tag=webx-admin-lang`                                                                                                                                                    |
| Keep the site out of sight              | testing: `WEBX_SITE_GATE=true`, `WEBX_SITE_GATE_USERS="user:secret"`, `gate.except`; a site with no public part: `WEBX_PUBLIC=false` (robots.txt disallows all, noindex)                              |
| Keep more or fewer publications         | `versions.limit` / `versions.autosaves`, then `php artisan webx:versions:prune`                                                                                                                       |
| Journal retention                       | `WEBX_HISTORY_RETENTION_DAYS`, `WEBX_HISTORY_PRUNE_AT`; off with `WEBX_HISTORY_ENABLED=false`                                                                                                         |
| Backup time, place, tool                | `WEBX_BACKUP_AT`, `WEBX_BACKUP_KEEP`, `WEBX_BACKUP_DISK`, `WEBX_BACKUP_PATH`, `WEBX_BACKUP_BINARY`, `WEBX_BACKUP_OPTIONS`                                                                             |
| Larger or longer-lived uploads          | `WEBX_UPLOADS_CHUNK_MB`, `WEBX_UPLOADS_TTL_HOURS`                                                                                                                                                     |
| A table of the site's own in snapshots  | `webx-admin.snapshot.tables` (`content`, `stand`, … ; `shop_*` is a prefix), or `SnapshotTables` from a provider                                                                                      |
| A setting that stays on its stand       | its key in `webx-settings.stand_own`; a restore keeps this stand's value                                                                                                                              |
| A check of your own in `webx:doctor`    | `$this->app->make(DoctorChecks::class)->register(YourCheck::class)` in a provider                                                                                                                     |
| Cache something the site reads a lot    | `WebxUi\Cache\CacheGroups::remember('name', ['menu', 'settings'], fn () => …)`; a mass write without a model → `bump('menu')`                                                                         |
| A record of yours on the site's panel   | `SiteBar::entity(<RouteType type>, new EntityLink(...))` per address type: https://webx-ui.github.io/webx-ui/guide/site-bar-modules                                                                   |
| A link source, note type, history type  | register into `LinkSources`, `NoteTypes`, `HistoryTypes` from a provider; a route of yours kept out of link fields — `Route::group([SiteRoutes::SERVICE => true], …)`, a path mask in `links.exclude` |

A patch addresses nodes by their `id`; every screen names its own (the module's guide lists them).
Operations are `add`, `remove`, `replace`, `move`, `set`, applied in the order registered. A patch whose target is gone throws when the screen is first built — a bug in whoever wrote it.

## Do not

- Do not edit `vendor/webx-ui/module-admin` or copy it into the site: `composer update` overwrites it. Every row above is the supported way; if none fits, say which seam is missing.
- Do not write inside the `<!-- webx:agents -->` block of the root `AGENTS.md`: the next `webx:panel --sync` replaces it. Site notes go under `## This project`.
- Do not hand-wire a module between the `webx:` markers of `admin.ts` and forget the Composer half: `webx:panel --sync` wires what is installed, and `webx:doctor` fails on a half alone.
- Do not route site pages under `webx-admin.path`: the shell answers every address below it; move the panel with `WEBX_ADMIN_PATH`.
- Do not add a column for one site's field on a module's table: a patch on a `HasExtra` model stores it in `extra`, and the next module release will not fight your migration.
- Do not put a site live without `webx-ui/module-auth`: without it `webx-admin.middleware` is only `web` and the panel is open to anyone.
- Do not restore a snapshot onto a stand whose content you have not copied first: it is replaced.
- Do not print anything for the panel on the site in a view (`@auth`, `data-*`): visitors get it too.
- Do not write `cms_versions` or `cms_history` with SQL: use `publish()`, `restoreVersion()` then `publish()`, and `History::record()` — a raw row has no number, no author, no source.
- Do not call `cache:clear` to refresh the site, and do not leave a mass write without its `bump()`: a reset somebody had to press is a `bump` somebody forgot.
- Do not download an address an administrator, an editor or an agent typed with `Http::get()`: only `RemoteFetcher` keeps the server's own network out of reach.

## Check your work

- `php artisan webx:doctor` (`--strict` on a deploy) — every failing line names the fix.
- `php artisan webx:panel --sync` twice: the second run must say `already wired`, `already lists what is installed` and change nothing.
- Open `/<webx-admin.path>` and `GET /<webx-admin.api_path>/screens/<name>`: a broken patch fails there with the screen, the operation and the missing target.
- With MCP: `history_get` on a record you changed shows the save, its source and the fields.
- A module of the site: its `PerformanceTest` (from `webx:make-module`) is filled in and green — https://webx-ui.github.io/webx-ui/guide/performance; an N+1 on a page is found with `debug_page` (`webx-ui/module-debug`) before and after the fix.

## Read more

- [README.md](README.md) in this directory — the module contract, manifest, drafts, backups, uploads.
- New site, `webx:panel --sync`, `webx:doctor`: https://webx-ui.github.io/webx-ui/guide/new-site
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending; moving content between stands: https://webx-ui.github.io/webx-ui/guide/snapshots
- Screens and patches: https://webx-ui.github.io/webx-ui/guide/screens; their specification — https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_SCREENS.md
- Specifications of the journal and of these guides: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_HISTORY.md, https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_AGENT_DOCS.md
