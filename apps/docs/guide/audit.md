# Site audit

`@webx-ui/module-audit` is the section that checks a site the way an SEO, a front-end developer
and an administrator check every new project by hand: the production config, the mirrors and
slashes, robots.txt and the sitemap, titles and headings, broken links, structured data, the
security headers — and every address in the content that still points at a development stand.
Its other half, `webx-ui/module-audit` on the server, crawls the site, keeps a snapshot of every
page and runs about a hundred checks. No external service is involved: everything is a request to
the site itself, its database or its config.

The specification, with every check and its default threshold, is
`docs/architecture/WEBX_UI_MODULE_AUDIT.md` in the repository.

## The section

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { audit } from '@webx-ui/module-audit'
import '@webx-ui/module-audit/style.css'

createAdmin({
  modules: [audit()],
})
```

It appears under **System**, after SEO, once `webx-ui/module-audit` is installed and migrated.
Permissions: `audit.view` to look, `audit.run` to start a run, `audit.manage` to fix, hide and
change the settings.

The section has five views and its settings:

- **Overview** — the last run: health in percent and what it is made of, errors, warnings and
  notices, what is new and what got fixed since the run before, and «Run the audit» with the scope.
- **Findings** — one row per check, worst first; a row opens into its addresses, each with the
  details the check left (a summary line and a table). «?» beside a check says what was found, why
  it matters and how to fix it.
- **Pages** — every address the last full run crawled, with any field of its snapshot as a
  column, filters on any field and CSV export. A row opens the page's card: answer, headers,
  markup, findings, links in and out, pictures, CSS, JS and structured data. A picture that
  answered the run shows its thumbnail and opens full size; one on a stand, past the run's limit
  or broken keeps a placeholder and is not fetched.
- **Outgoing** — every host the site points at, in its pages and in its database, by class:
  development stands on top in red, then other mirrors of the site, external hosts and the site
  itself. A row opens into the pages that link there and the records that hold the address, with
  «Open in the editor».
- **Runs** — the history. Tick one run to compare it with the run before it, tick two to compare
  those: what is new, what persists and what is gone, check by check. «Clear audit data» below
  (`audit.manage`) deletes every run with everything it found, after a warning; the hiding rules
  and the settings stay. It is refused while a run is going — cancel it first.
- **Settings** (the button in the head) — where the site is, its stands, the limits of the crawl,
  the paths it leaves out, the thresholds, the nightly run and how much history to keep.

## Running it

A run is a job in the queue, done a piece at a time, so a host that limits a worker to a minute
still finishes it. Scopes:

- **Quick** — the config, the host and the database: seconds. What a deploy runs.
- **Full** — the same, then the crawl: the home page and the home of every other language
  (`/de`, `/fr`), the sitemap and the address registry first, then every link and hreflang, a
  couple of requests at a time, up to the page limit. Each language version is a page of its own
  under that one limit — there is no separate crawl per language.
- **Recheck** — «Recheck» on a page's card asks that one address again and says which of its
  findings in the last full run are fixed. Checks that judge the whole site (duplicates, orphans,
  depth, the sitemap) do not run on one page.

### Health

The percentage on the Overview is the share of crawled pages without an error, then:

- minus **10** for each check with an error that belongs to no page — a mirror that does not
  redirect, `APP_DEBUG` in production, a stand's address in the database: those touch every page;
- minus **2** for each check with warnings, counted once however many addresses it found, and
  **20 at most** for all of them — a hundred pictures without `alt` are one warning.

A page with three errors is lost once. Notices and hidden findings weigh nothing; a quick run,
which crawls no pages, starts from 100. Beside the circle the Overview says how many pages are
clean and how many site errors and warning checks took points.

The audit keeps no page HTML — only what it extracted (titles, meta, headings, links, counters).
Where a check counts elements (pictures without `alt`, buttons without a name, fields without a
label), its finding quotes up to five of them as the page wrote them, so the template that makes
them is easy to find.

On a `sync` queue the panel does not start a run — it would run inside the request — and says so.
The command works everywhere:

```bash
php artisan webx:audit:run --quick --fail-on=error
```

`--fail-on=error` exits with 1 when the run found an error: put it after the migrations of a
deploy, and a site with `APP_DEBUG=true` or links to a stand in its content does not go out
silently. Without `--quick` the command runs a full audit in the terminal.

The site knocks on its own door by its public name. When DNS from inside the server does not lead
back to it (Docker, NAT), set **Connect to** in the settings: the connection goes there, the
request keeps the real name.

## Fixing

A finding never fixes itself. Where the module that owns the thing can fix it, the address in the
findings has a **Fix** button; the dialog shows what would change — the records and fields with
the number of replacements, or a setting before and after — and nothing changes until «Apply».
The finding stays, marked «Fixed — waiting for the next run», until a run confirms it is gone.

| Fix                  | Closes                                | What it changes                                                                       |
| -------------------- | ------------------------------------- | ------------------------------------------------------------------------------------- |
| `audit.replace-host` | `hosts.dev_content`                   | The stand's host in the field, replaced by the site's, through the module's own model |
| `seo.normalise-*`    | `host.mirror`, `https`, `slashes`, …  | A setting of SEO's «One address for a page»: one 301 to the right address             |
| `seo.collapse-chain` | `redirects.chain`                     | The SEO redirects of the chain, pointed straight at its end                           |
| `seo.robots-sitemap` | `robots.no_sitemap`, `robots.missing` | A `Sitemap:` line in the robots.txt setting                                           |

Changes go through the models of their modules, so the history journal of the panel records each
one and it can be rolled back there. Where Laravel cannot fix it — a mirror the web server answers
before PHP sees the request — there is no button: the check’s «How to fix it» says what to change
on the server.

## Hiding what is meant

Some findings are decisions: `noindex` on the search page, a long title on a landing. **Hide** on
an address asks which addresses — this one, a mask (`/search/**`: `*` is a stretch without a
slash, `**` one with slashes) or every finding of the check — and why. The dialog says how many
findings of the last run the rule would hide before it hides anything.

A hidden finding is still found and stored; it stops counting, in that run and in every later one.
«Hidden» in the state filter of the findings lists them with the reason and who decided, and
«Show again» removes the rule.

## Settings

| Setting                      | Default   | What it does                                                               |
| ---------------------------- | --------- | -------------------------------------------------------------------------- |
| Address to audit             | `APP_URL` | The address the audit opens                                                |
| Connect to                   | DNS       | Where the connection goes, the public name kept                            |
| Other addresses of this site | —         | Stands and old domains: a link to any of them is an error                  |
| Pages at most                | 1000      | Where a full run stops adding pages                                        |
| Requests at a time           | 2         | How hard the crawl leans on the server                                     |
| Paths not to crawl           | —         | Masks the crawl does not ask (`/cart`, `/search/**`)                       |
| Thresholds                   | §5        | Title and description lengths, words, text share, depth, picture weight, … |
| Run the audit every night    | off       | A run of the chosen scope at the chosen hour, started by `schedule`        |
| Runs to keep                 | 50        | Older runs go with their findings; the last full run always stays          |
| Page snapshots to keep       | 5         | Pages and links of this many full runs — the bulk of what the audit stores |

The nightly run needs the Laravel scheduler in cron, like the rest of the site. The settings
override `config/webx-audit.php`; what is not on the screen (the TLS and cache thresholds, the
zones and words that make a host a stand) stays in that file.

## For an agent (MCP)

The section's tools, with the scopes `audit:read` and `audit:write`:

| Tool             | What it does                                                                                    |
| ---------------- | ----------------------------------------------------------------------------------------------- |
| `audit_run`      | Starts a run: `quick`, `full`, or `urls` with a list of addresses to recheck                    |
| `audit_status`   | The run that is going and the last finished one                                                 |
| `audit_issues`   | Without `check`: one row per check with its three texts. With `check`: its findings             |
| `audit_pages`    | The crawled pages, filtered on any field of the snapshot                                        |
| `audit_page_get` | One page by id or address, with its findings                                                    |
| `audit_hosts`    | The hosts by class, stands first; with `host`, where it stands in pages and in the database     |
| `audit_fix`      | The fixes a finding has, a preview with `dry_run`, then applied                                 |
| `audit_ignore`   | Hides with a reason (`dry_run` counts first), lists the rules, `remove` shows a rule's findings |

The resource `audit://checks` is the catalogue of checks with their texts and fixes: an agent
reads it to fix the cause, not the symptom. The reading tools call the same controllers as the
screens, so a filter that works in the panel works for the agent too.

## Adding checks from a module

A module brings its own checks and fixes when the audit is installed — `module-audit` in
`suggest`, registration under `class_exists`:

```php
use WebxUi\Audit\Checks\AuditChecks;
use WebxUi\Audit\Content\AuditContentSources;

if (class_exists(AuditChecks::class)) {
    $this->app->make(AuditChecks::class)->register($this->app->make(MyCheck::class));
}

if (class_exists(AuditContentSources::class)) {
    $this->app->make(AuditContentSources::class)->register($this->app->make(MyContentSource::class));
}
```

A check extends `WebxUi\Audit\Checks\ModuleCheck` and yields findings; a content source hands its
text fields over so that addresses of a stand are found in drafts and hidden records too. The
overview lists installed content modules that gave no source — their fields are not searched.
