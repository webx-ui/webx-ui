# webx-ui/module-admin

The panel itself: the shell page at `config('webx-admin.path')`, the module registry and the
manifest the front end reads, screens and their patches, and the machinery other modules borrow —
drafts, versions, the change journal, notes, relations, categories, chunked uploads, the nightly
backup — plus the commands that install a site and say whether it stands up. Signing in is not
here: that is `webx-ui/module-auth`; languages are `webx-ui/localization`; the MCP server is
`webx-ui/mcp` — read their guides for those.

## What it owns

- **Shell** — every address under `webx-admin.path` (default `cms`) serves one page that mounts
  `#webx-app`; routing inside belongs to the front end. Its JSON lives under `webx-admin.api_path`
  (default `api/cms`): `manifest`, `screens/{name}`, `links/*`, `collections`, `relations/{target}`,
  `entities/{type}/{id}/notes`, `history/{type}/{id}`, `history/runs/{id}`, `uploads`, `locales`,
  `translations/{locale}`. The panel's icons are served under its prefix too.
- **Auth** — none of its own. `webx-admin.middleware` is `['web']`, so until `webx-ui/module-auth`
  adds its middleware the panel is open. Modules put their API behind the middleware group
  `webx.panel` (`web`, `webx.panel-locale`, `cms.auth`, `webx.history`).
- **Modules** — `ModuleRegistry`: a module (`AbstractModule`, `id()` required) is registered from a
  provider and lands in the manifest with its title, icon, order, group and permissions. Two
  modules with one id are refused. This package declares no permissions of its own.
- **Screens** — `ScreenRegistry` behind the `Screens` facade; field types in `FieldTypes`
  (`wx-input`, `wx-link`, `wx-repeater`, `wx-relations`, …), which check and cast what a screen saves.
- **Drafts and versions** — traits `HasDraft` (the `$table->draft()` macro: `draft`,
  `published_at`) and `HasVersions` (table `cms_versions`; `publish()` writes a version,
  `restoreVersion()` puts one back into the draft). `HasExtra` stores fields a patch adds in `extra`.
- **History** — table `cms_history`, trait `RecordsHistory`, facade `History`, types in
  `HistoryTypes`. With `webx-ui/mcp` installed and a type registered: MCP tools `history_get`,
  `history_runs`, resource `history://types`, scope `history:read`.
- **Also** — tables `cms_notes` (`HasNotes`), `cms_relations` (`HasRelations`), `cms_uploads`
  (chunked uploads, `UploadPurposes`); Blueprint macros `category()`, `categoryLinks()`; the
  `@webxPart` / `@webxPartAssets` directives; the site password (`CloseSite`, `Openings`).
- **Shortcodes** — the registry `WebxUi\Admin\Shortcodes\Shortcodes` (facade `Shortcodes`):
  `register()`, `source()`, `html()`, `htmlIn()`, `plain()`, `text()`; directives `@shortcodes`,
  `@shortcodesIn`, `@shortcodesPlain`; `GET /api/cms/shortcodes` for the panel's text fields.
- **Front end entry** — `resources/js/admin.ts` in the site, written from `stubs/panel.stub` (or
  `panel-auth.stub` when module-auth is installed). Three regions are the installer's:
  `// webx:imports`, `// webx:styles`, `// webx:modules`; everything outside them is the site's.

### Commands

| Command                                                                             | What it does                                                                                                                                                             |
| ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `webx:setup`                                                                        | database, `composer require` of the ticked modules, `webx:panel --sync`, npm build, migrate, demo, doctor. Run it again to add a module                                  |
| `webx:panel`                                                                        | writes the entry file; refuses if it exists unless `--sync` or `--force`; `--entry=` moves it                                                                            |
| `webx:panel --sync`                                                                 | tops up the three regions, `package.json`, the Vite input, `webx-admin.vite`, modules' `layout`, `AGENTS.md`                                                             |
| `webx:doctor`                                                                       | checks both halves, bundle, npm ranges, migrations, storage, layout, regions, languages, caches, Passport keys, site gate; repairs nothing. `--strict` fails on warnings |
| `webx:modules`, `webx:module:add`                                                   | list the modules a site can have; install one without the questions (`--no-build`)                                                                                       |
| `webx:demo`                                                                         | seed demo content; `--remove`, `--module=`, `--force`                                                                                                                    |
| `webx:make-module`                                                                  | a module class                                                                                                                                                           |
| `webx:boot`                                                                         | container start: wait for the database, migrate, cache                                                                                                                   |
| `webx:db:backup`, `webx:history:prune`, `webx:versions:prune`, `webx:prune-uploads` | scheduled by the package itself; need the system cron behind `schedule:run`                                                                                              |
| `webx:snapshot`                                                                     | packs content tables + the public disk into `storage/app/snapshots/<site>-<env>-<date>.tar.gz`; `--with-admins`, `--all`, `--no-media`, `--output=`                      |
| `webx:snapshot:restore <archive>`                                                   | **replaces this stand's content** with the archive's: backup first, stand-own tables untouched, addresses rewritten, files mirrored (`--keep-extra` to only add)         |

### Moving content between stands

`webx:snapshot` on one stand, `scp` the archive, `webx:snapshot:restore` on the other. Every
package declares its tables in `SnapshotTables` as `content` (travels), `admins`
(`--with-admins`), `stand` (enquiries, journals, tokens, audit; only `--all`), `derived` (emptied
on restore) or `transient` (sessions, cache, queues, `migrations`; never). A restore refuses an
archive with migrations this code lacks, refuses production without `--force`, and asks unless
`--no-interaction --force`. Its first line of work is a `webx:db:backup` dump — the rollback.

### The site's root `AGENTS.md`

`webx:panel` (and so `--sync`) writes a block between `<!-- webx:agents -->` and
`<!-- /webx:agents -->`: what the site is, the first rule, where its look lives (a link to the
styles guide, `RootFile::STYLES_GUIDE`), a link to `vendor/webx-ui/<pkg>/AGENTS.md` for every
installed `webx-ui/*` package that has one, what to run after updating. The block is
replaced whole on each run; everything outside it — the `## This project` section — is never
touched. A hand-written file without markers keeps every word, with the block put on top.
`CLAUDE.md` is written once as `@AGENTS.md`; an existing one without that line only gets a warning.
Where `webx-ui/mcp` serves its HTTP endpoint (route `webx.mcp`), `.mcp.json` gets one server named
after the site (`Site::name()`), at `app.url` + the route; other servers, and a key renamed over the
same address, are kept; a file that is not JSON is left alone with a warning (`Agents\McpConfig`).

## Change it without forking

| You want                                | Do this                                                                                                                   |
| --------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| The panel at another address            | `WEBX_ADMIN_PATH` (and `WEBX_ADMIN_API_PATH` for its JSON)                                                                |
| Another panel name or tab icons         | `WEBX_ADMIN_TITLE`; `WEBX_ADMIN_ICONS` = a directory with the same file names                                             |
| A shortcode of the site's own (`[dot]`) | `Shortcodes::register('dot', '<span class="accent-dot">.</span>', plain: '.')` in `AppServiceProvider::boot()`            |
| A field or tab on a module's screen     | a patch: `Screens::extend('<module>.<screen>', [...])` in `AppServiceProvider::boot()`                                    |
| A field stored without a migration      | the same patch on a model with `HasExtra`; read it with `$model->extra('<name>')`                                         |
| A field with a value until it is set    | `"default": …` on the node: drawn in the panel and read by the site, never written by itself                              |
| An email, address or phone field        | `wx-input` with `"props": { "type": "email" }` (or `url`, `tel`); the server holds the format too                         |
| A screen of your own                    | `Screens::register('<module>.<screen>', $pathOrTree)` from a provider                                                     |
| A section of your own                   | `php artisan webx:make-module <Name>`, register it in `ModuleRegistry` from a provider                                    |
| A navigation group or caption           | `groups` in `config/webx-admin.php` (`php artisan vendor:publish --tag=webx-admin-config`)                                |
| Your own lines in the panel's JS        | anywhere in `resources/js/admin.ts` outside the three `webx:` regions                                                     |
| Your own assets in the shell            | push onto the `webx-head` / `webx-body` stacks, or `--tag=webx-admin-views`                                               |
| Other words in the panel                | `php artisan vendor:publish --tag=webx-admin-lang`                                                                        |
| A password over the site while testing  | `WEBX_SITE_GATE=true`, `WEBX_SITE_GATE_USERS="user:secret"`; `gate.except` for open paths                                 |
| Keep more or fewer publications         | `versions.limit` / `versions.autosaves`, then `php artisan webx:versions:prune`                                           |
| Journal retention                       | `WEBX_HISTORY_RETENTION_DAYS`, `WEBX_HISTORY_PRUNE_AT`; off with `WEBX_HISTORY_ENABLED=false`                             |
| Backup time, place, tool                | `WEBX_BACKUP_AT`, `WEBX_BACKUP_KEEP`, `WEBX_BACKUP_DISK`, `WEBX_BACKUP_PATH`, `WEBX_BACKUP_BINARY`, `WEBX_BACKUP_OPTIONS` |
| Larger or longer-lived uploads          | `WEBX_UPLOADS_CHUNK_MB`, `WEBX_UPLOADS_TTL_HOURS`                                                                         |
| A table of the site's own in snapshots  | `webx-admin.snapshot.tables` (`content`, `stand`, … ; `shop_*` is a prefix), or `SnapshotTables` from a provider          |
| A setting that stays on its stand       | its key in `webx-settings.stand_own`; a restore keeps this stand's value                                                  |
| A check of your own in `webx:doctor`    | `$this->app->make(DoctorChecks::class)->register(YourCheck::class)` in a provider                                         |
| A link source, note type, history type  | register into `LinkSources`, `NoteTypes`, `HistoryTypes` from a provider                                                  |

A patch addresses nodes by their `id`; every screen names its own (the module's guide lists them).
Operations are `add`, `remove`, `replace`, `move`, `set`. A patch whose target is gone throws when
the screen is first built — a patch here is a bug in whoever wrote it, not something to skip.
Patches apply in the order they were registered.

## Do not

- Do not edit `vendor/webx-ui/module-admin` or copy it into the site: `composer update` overwrites
  it. Every row above is the supported way; if none fits, say which seam is missing.
- Do not write inside the `<!-- webx:agents -->` block of the root `AGENTS.md`: the next
  `webx:panel --sync` replaces it. Site notes go under `## This project`.
- Do not hand-wire a module between the `webx:` markers of `admin.ts` and forget the Composer
  half: `webx:panel --sync` wires what is installed, and `webx:doctor` fails on a half alone.
- Do not route site pages under `webx-admin.path`: the shell answers every address below it.
  Move the panel with `WEBX_ADMIN_PATH` instead.
- Do not add a column for one site's field on a module's table: a patch on a `HasExtra` model
  stores it in `extra`, and the next module release will not fight your migration.
- Do not put a site live without `webx-ui/module-auth`: without it `webx-admin.middleware` is only
  `web` and the panel is open to anyone.
- Do not restore a snapshot onto a stand whose content you have not copied first: a restore
  replaces the content tables and mirrors the files. The rollback is the dump it names.
- Do not write `cms_versions` or `cms_history` with SQL: use `publish()`, `restoreVersion()`
  then `publish()`, and `History::record()` — a raw row has no number, no author, no source.

## Check your work

- `php artisan webx:doctor` (`--strict` on a deploy) — every failing line names the fix.
- `php artisan webx:panel --sync` twice: the second run must say `already wired`,
  `already lists what is installed` and change nothing.
- Open `/<webx-admin.path>` and `GET /<webx-admin.api_path>/screens/<name>`: a broken patch
  fails there with the screen, the operation and the missing target.
- With MCP: `history_get` on a record you changed shows the save, its source and the fields.

## Read more

- [README.md](README.md) in this directory — the module contract, manifest, drafts, backups, uploads.
- New site, `webx:panel --sync`, `webx:doctor`: https://webx-ui.github.io/webx-ui/guide/new-site
- Screens and patches: https://webx-ui.github.io/webx-ui/guide/screens
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
- Specification of screens: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_SCREENS.md
- Moving content between stands: https://webx-ui.github.io/webx-ui/guide/snapshots
- The journal: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_HISTORY.md
- Agent guides: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_AGENT_DOCS.md
