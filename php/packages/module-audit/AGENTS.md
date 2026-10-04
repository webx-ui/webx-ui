# webx-ui/module-audit

The site audit, a section «System → Audit» of the panel: the production config, the host
(mirrors, https, slashes, headers), every absolute address in the database, and a crawl of the
site with page, indexing, redirect, hreflang, JSON-LD and resource checks. Runs go to the queue
in pieces; findings are kept per run, can be hidden by rule and closed by fixes other modules
bring. The panel shell, screens and permissions are `webx-ui/module-admin`, the stored settings
`webx-ui/module-settings`, the MCP server `webx-ui/mcp`; the content it searches comes from
`webx-ui/module-pages` and `webx-ui/module-blocks` — read their guides for those.

## What it owns

- **Tables** `audit_runs` (`WebxUi\Audit\Runs\AuditRun`), `audit_issues`, `audit_pages`,
  `audit_links`, `audit_resources`, `audit_content_urls`, `audit_ignores`. Run scopes are
  `quick`, `full` and `urls`.
- **Config** `config/webx-audit.php`: `user_agent`, `timeout`, `job_seconds`, `pages_limit`,
  `concurrency`, `keep_snapshots`, `keep_runs`, `exclude`, `resources_limit`, `thresholds.*`,
  `dev_zones`, `dev_words`.
- **Settings screen** `audit.settings` (`resources/screens/audit-settings.json`), values stored
  under `audit.*` in the site's settings. What is set there wins over the config file.
- **API** under `<api_path>/audit` (`runs`, `runs/{run}/issues`, `runs/{run}/pages`, `hosts`,
  `ignores`, `settings`, fixes under `runs/{run}/issues/{issue}/fixes`); permissions
  `audit.view`, `audit.run`, `audit.manage`.
- **MCP** tools `audit_run`, `audit_status`, `audit_issues`, `audit_pages`, `audit_page_get`,
  `audit_hosts`, `audit_fix`, `audit_ignore`; resource `audit://checks` (the catalogue of
  checks). Scopes `audit:read`, `audit:write`.
- **Command** `php artisan webx:audit:run [--quick] [--fail-on=error|warning|notice]` — runs in
  this process, no queue.
- **Schedule**: `webx-audit:heartbeat` every minute (so the schedule check can tell cron runs)
  and `webx-audit:nightly` once the settings switch it on.
- **Registries** (singletons): `WebxUi\Audit\Checks\AuditChecks`,
  `WebxUi\Audit\Content\AuditContentSources`, `WebxUi\Audit\Fixes\AuditFixes`. Its own fix is
  `audit.replace-host` (a stand's host in the content replaced by the site's).

## Change it without forking

| You want                                 | Do this                                                                                              |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------- |
| Another address to audit, or no DNS      | settings screen: `audit.base-url`, `audit.resolve-to` (an IP or host to connect to)                  |
| Mark stands, staging, old domains        | settings screen: `audit.other-hosts`, one per line                                                   |
| Other limits or thresholds               | the settings screen; for keys it does not show, `php artisan vendor:publish --tag=webx-audit-config` |
| Skip paths in the crawl                  | `audit.exclude` on the screen or `exclude` in the config: masks, `*` and `**`                        |
| More stand zones or words                | `dev_zones`, `dev_words` in the published config                                                     |
| A nightly run                            | settings screen: `audit.schedule`, `audit.schedule-scope`, `audit.schedule-hour`                     |
| Hide a finding on purpose                | an ignore rule in the panel or `audit_ignore` with a reason — a check, an address or a mask          |
| A check of your own                      | `app(AuditChecks::class)->register(new MyCheck)`; implement `AuditCheck` or extend `ModuleCheck`     |
| Your module's fields searched for stands | implement `AuditContentSource`, register into `AuditContentSources`                                  |
| A button that closes a finding           | implement `AuditFix`, register into `AuditFixes`                                                     |
| A field on the settings screen           | a patch: `Screens::extend('audit.settings', [...])` in `AppServiceProvider::boot()`                  |
| Other words in the panel                 | `php artisan vendor:publish --tag=webx-audit-lang`                                                   |

Register checks, sources and fixes from a provider's `boot()` behind
`class_exists(AuditChecks::class)` (or the class you use), so the package works without the
audit installed. A check's texts live in its dictionary under `checks.<id>.title|found|why|fix`
(`ModuleCheck::NAMESPACE`). The contracts are in `WebxUi\Audit\Contracts`.

A content source gives `id()`, `records()` (lazily, drafts and hidden records included),
`find()`, `fields()` (one `ContentField` per locale) and `replace()`, which must write through
the model so the history journal sees the change.

The settings screen's node ids: `site`, `base-url`, `resolve-to`, `other-hosts`, `crawl`,
`pages-limit`, `concurrency`, `resources-limit`, `exclude`, `thresholds`, `title-min`,
`title-max`, `description-min`, `description-max`, `thin-words`, `text-ratio`, `url-length`,
`ttfb-ms`, `depth`, `image-kb`, `external-links`, `schedule`, `schedule-on`, `schedule-scope`,
`schedule-hour`, `history`, `keep-runs`, `keep-snapshots`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-audit` or copy it into the site. The registries
  and the screen patch above are the supported seams; if none fits, say the seam is missing.
- Do not make a check crawl or request the site itself: by the time a `crawl` check runs the
  snapshot is collected. Read it from `AuditContext` and declare what you need in `needs()`
  (`config`, `probes`, `database`, `crawl`).
- Do not make a finding fix itself inside a check. Changes go through an `AuditFix` with a
  `preview()`, so the panel and `audit_fix` with `dry_run` show them before they happen.
- Do not write content with SQL in a fix or `replace()`: the history journal would not see it.
  Save through the owning module's models.
- Do not start a run from the panel on a `sync` queue — it is refused (409), a crawl would run
  inside the request. Use `webx:audit:run` or a real queue worker.
- Do not raise `concurrency` or `pages_limit` on a shared or production host without reason: the
  defaults keep the crawl polite. Prefer `exclude` for sections that need not be crawled.
- Do not delete `audit_*` rows by hand to clean history: `keep_runs` and `keep_snapshots` prune
  it. Hide a finding with an ignore rule rather than deleting it — it would come back next run.
- Do not hard-code your stands' hosts into a check; list them in `audit.other-hosts`.

## Check your work

- `php artisan webx:audit:run --quick --fail-on=error` — the config, the host and the database in
  seconds; exit code 1 on an error. Put it in the deploy after the migrations.
- A new check appears in `audit://checks`; run `audit_run` (scope `quick`, or `full` for page
  checks), then `audit_status` and `audit_issues` with `check` set to its id.
- A new content source: put a stand's address in a draft, run `audit_run` (scope `quick`) and
  see `audit_issues` with `check: "hosts.dev_content"` find it.
- With MCP, `audit_fix` without `fix` lists what it can do; with `fix` and `dry_run: true` it shows
  the preview. The finding stays until the next run confirms it is gone.

## Read more

- [README.md](README.md) in this directory — install, the deploy step, a check and a fix of your own.
- Guide: https://webx-ui.github.io/webx-ui/guide/audit
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_AUDIT.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
