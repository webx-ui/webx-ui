# Site modules

A site module is a section of the panel that lives in the repository of one site rather than in
this monorepo, and is built exactly like a packaged module: the same service provider, JSON
screens, migrations, MCP tools, permissions and ready-made roles, help, demo and `AGENTS.md`, and
the same npm half. It is a folder, `modules/<name>`, installed by Composer as a `path` package and
by npm as a `file:` link. Moving it into the monorepo later is moving the folder and renaming three
things — not rewriting it.

The example all the way through is a made-up directory of offices: id `offices`, table `offices`,
folder `modules/offices`. The design and every decision behind it are in the
[specification](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_SITE_MODULES.md).

## Which kind of module

| You need                                                                                         | Reach for                                                                                                                         |
| ------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| a section many sites would install                                                               | a package of the monorepo (`webx-ui/module-*`) — see [Contributing](https://github.com/webx-ui/webx-ui/blob/main/CONTRIBUTING.md) |
| a section only this site has: its own records, its own screens, its own blocks                   | a site module — `webx:make-module --local offices`                                                                                |
| one class on top of what is already there, with no table, no front end and no screens of its own | `webx:make-module Offices` without `--local`: a class in `app/Cms/Modules` and two tests                                          |

The difference between the last two is what grows. A class in `app/` has no npm half, no
migrations, help or MCP in one place, and cannot move into the monorepo without being rewritten.
When in doubt, take `--local`: a site module that turns out to be needed by a second site moves
as a folder.

## Creating one

```bash
php artisan webx:make-module offices --local
```

In the order it runs:

1. **The names.** `offices` gives the id `offices`, the namespace `Site\Offices\`, the class
   `OfficesModule`, the model `Office`, the packages `site/module-offices` and
   `@site/module-offices`. Two words — `OfficeHours` — give `office-hours`, tables
   `office_hours_*` and the function `officeHours()`, exactly as a package of the monorepo would.
2. **The refusals.** An id a module of the monorepo has (`faq`, `blog` — anything in
   `webx:modules`), an id an installed package already goes by, and a folder that is already there.
   `--force` writes over the files of the scaffold in an existing folder and nothing else; what you
   added beside them stays.
3. **The files** — both halves, below.
4. **The site's wiring**, one line of output per file, and safe to run again: the path repository
   `modules/*` and `autoload-dev` for the module's tests in `composer.json`; the `Modules` suite in
   `phpunit.xml`; `@vitejs/plugin-vue`, `vue-tsc`, `typescript`, `vitest`, `@vue/test-utils`,
   `jsdom` and the scripts `typecheck:modules`, `test:modules` in `package.json`;
   `modules/tsconfig.json`. Two files are only read: when `vite.config.*` has no Vue plugin, or the
   `Dockerfile` builds the front end without `modules/`, the command says which line to add.
5. **The install**, as a child `php artisan webx:module:add site/module-offices` — Composer,
   [`webx:panel --sync`](./new-site.md#what-webx-panel-sync-touches), the ready-made roles,
   `npm install`, the build — then `migrate`, and `webx:blocks:offered --install --module=offices`
   when the module offers block types.

`--no-install` stops after the wiring: the files are written and nothing is installed, for a
person who wants to read them first. A module made this way passes its own tests and
`webx:doctor` straight away; anything that does not is a bug of the scaffold.

## What is in the folder

```
modules/offices/
  composer.json          site/module-offices, version 0.1.0, extra.laravel + extra.webx
  AGENTS.md              what an agent needs to know about the module
  README.md
  config/webx-offices.php
  database/migrations/
  lang/<language>/       en + the panel's languages
  resources/
    screens/form.json
    help/<language>/     offices.index.md, offices.form.md — every field described
    demo/offices.json    three records for webx:demo
  routes/api.php         list, record, order — behind cms.can
  src/                   Site\Offices\ — provider, Panel\OfficesModule, Models, Http, Mcp, Demo
  tests/                 Site\Offices\Tests\
  js/
    package.json         @site/module-offices, private, peerDependencies only
    tsconfig.json
    src/                 index.ts with offices(), the list and form pages, messages.ts and its test
```

The scaffold is one record type: `Office` with a translatable `title` and a `position` in the
list, the permissions `offices.view` and `offices.manage` (named in the `editor` and `seo` ready-made
roles through `HasDefaultRoles` — see [Permissions](./permissions.md#in-the-ready-made-roles)), the
MCP tools `offices_list`, `offices_get`, `offices_create`, `offices_update`, `offices_delete`,
`offices_reorder` with `dry_run` on every one that writes, and the resource `offices://catalog`.
Everything past that is yours to write, by the same rules as a package: a new screen or field
comes with its help ([Help in the panel](./panel-help.md)), a new route with a permission.

### The names

Everything the site, the panel, the database and an agent see is named exactly as it would be in
the monorepo. Only the three names of the packaging differ, and moving the module changes those
three:

| what                                   | site module                 | in the monorepo           |
| -------------------------------------- | --------------------------- | ------------------------- |
| Composer package                       | `site/module-offices`       | `webx-ui/module-offices`  |
| PHP namespace                          | `Site\Offices\`             | `WebxUi\Offices\`         |
| npm package                            | `@site/module-offices`      | `@webx-ui/module-offices` |
| id, permissions, MCP tools, tables     | `offices`, `offices.*`      | the same                  |
| translations, views, config            | `webx-offices`              | the same                  |
| keys of help and screens, npm function | `offices.form`, `offices()` | the same                  |

`webx-offices` is not a slip: the panel's dictionary is assembled from the namespaces that start
with `webx-`, and `webx:panel --sync` looks for the module's config under `webx-<id>`. The vendor
`site` is one for every site — two sites never share an application, so they have nothing to
collide on — and it is how the tools tell a site module apart: `webx:modules` marks it `local`, the
site's `AGENTS.md` lists it under its own heading pointing at `modules/offices/AGENTS.md`,
`webx:theme:sync` takes its `resources/theme/*.css`, `webx:snapshot` writes its version, and the
[debug bar](./debugbar.md) names it as the source of a query.

### Composer and npm

```json
"repositories": [
    { "type": "path", "url": "modules/*", "options": { "symlink": true } }
],
"require": {
    "site/module-offices": "^0.1.0"
}
```

The module's `composer.json` carries `"version": "0.1.0"`, and it stays `0.1.0` forever: the
module ships with a commit of the site, and that commit is its version. Without the field
Composer takes the version from the site's git — `dev-main` — and the skeleton's
`minimum-stability: stable` refuses it. `vendor/site/module-offices` is a **relative** symlink to
`../../modules/offices/`, which is what lets it survive a `COPY` into an image and another
checkout.

The npm half is `"@site/module-offices": "file:modules/offices/js"` in the site's
`package.json`, written there by `webx:panel --sync` from the module's `extra.webx.npm`. It has
no `dist`: **the site's Vite builds it from its sources**, which is why the site needs
`@vitejs/plugin-vue`, why there is no `panel.style` (the styles of its components go into the
panel's CSS by themselves), and why `npm run dev` gives hot reload on the module's files.
`@webx-ui/*` and `vue` are `peerDependencies`: the module takes the site's, so there is never a
second copy of Vue. A library the site does not have goes into the module's `dependencies`, and
npm hoists it to the site.

## Tests and languages

The module's PHP tests run **on the site's application**, not on a Testbench of their own — they
see exactly the packages and configuration the module works with:

```bash
php artisan test --testsuite=Modules   # every module of the site
npm run typecheck:modules              # vue-tsc over modules/*/js — required
npm run test:modules                   # vitest — the test of messages.ts keys is generated
```

`Site\Offices\Tests\TestCase` extends the site's `Tests\TestCase` with `RefreshDatabase`. What the
scaffold writes, and what has to stay green:

| test                 | holds                                                                               |
| -------------------- | ----------------------------------------------------------------------------------- |
| `AccessCoverageTest` | every route behind `cms.can`, every permission declared                             |
| `HelpCoverageTest`   | every screen and field of **this** module described — `assertModuleScreensHaveHelp` |
| `TranslationsTest`   | the languages of the module have exactly the keys of `en` — `AssertsTranslations`   |
| `McpDryRunTest`      | every tool that writes, given `dry_run`, writes nothing                             |
| `PerformanceTest`    | query budgets of the list, the form and the MCP list                                |
| `PanelTest`          | create, read, update, delete, order, permissions, the demo                          |

**Languages:** `en` is required — it is the fallback of the dictionary and the source of
`messages.ts` — plus the languages of the site's panel, `config('webx-localization.panel')`. The
help is written in the same languages. The scaffold has its own text in English, Russian and
Ukrainian; any other language of the panel gets the English, marked as waiting for a translation.
The tests check the languages the module has against each other and require only those; the ten
languages of the monorepo are a step of moving it there.

No PHPStan, no `AgentDocsCoverageTest`, no changeset: the skeleton has none of those, and they come
with the move. Who runs the tests is the site's decision — the skeleton has no CI. A site that runs
`php artisan test` in a test stage of its image gets the modules' tests without a change.

## Deploying

A site module is ordinary files of the site's repository; nothing delivers it separately.

**The image.** The skeleton's `Dockerfile` installs Composer in the `vendor` stage, where
`modules/` is in the context and the symlink is relative, and copies `modules/` into the `assets`
stage before `npm ci` (`COPY --from=vendor /app/modules ./modules`, the same way as `theme/`, so
that a site with no modules still builds). `.dockerignore` excludes `**/node_modules`, not just the
root one.

**A site made before site modules** has its own copy of the `Dockerfile`, and no release changes it.
Its `assets` stage copies `package.json`, the lock, `vite.config.js`, `resources`, `theme` and
`public` — not `modules/`: `npm ci` quietly links `file:modules/offices/js` to nothing, and
`npm run build` stops on `failed to resolve import "@site/module-offices"`. Add one line before
`npm ci` in that stage:

```dockerfile
COPY modules ./modules
```

`webx:doctor` warns about a `Dockerfile` without it (`--strict` stops on it), and `webx:make-module
--local` names the line. The same doctor check fails when the module has `.vue` files and the site's
Vite has no Vue plugin, and when a site module goes by the id of a module of the monorepo.

**A host on cPanel**, where the front end is built elsewhere and `public/build` is committed to a
`dist` branch: nothing to change. `modules/` is in git, so the build sees it; `composer install`
from the lock makes the relative symlink; `webx:boot` migrates the module's tables. `composer.lock`
has to be in git. A deploy script that reports new migrations by looking at `database/migrations`
alone should look at `modules/*/database/migrations` too.

`composer install --no-dev --classmap-authoritative` treats the module as any package: its `src/` is
in the class map, its tests (the site's `autoload-dev`) are not loaded in production.

## Live sites and the lab

On a live site of the hosting platform the `webx-ui/*` packages are links to the monorepo; the
site module is a relative path repository of the site itself, which the links do not touch. An edit
of its PHP, Blade or `lang` shows at once, like an edit of `app/`. An edit of its `composer.json`,
a new migration, or its front end with watch off — `webx sync <site>`, as for a package.

**The lab site** (`scripts/lab-site.sh`) and every site linked by `scripts/lib/linked-site.sh`
keep the site's relative path repositories through `refresh`, ahead of Packagist, so
`composer update "webx-ui/*"` still finds `site/module-offices`. The lab has no git and `reset`
deletes its folder: a module made on the lab lives until the next `reset`. Try one on a copy —
`LAB_NAME=webx-lab-copy scripts/lab-site.sh create`.

## Things that look like bugs in your code

- **An edit of the module's `composer.json` does nothing** — a new `require`, `autoload`, a
  provider. Composer reads them from the lock, not from the disk: `composer update
site/module-offices`.
- **`autoload-dev` and `require-dev` in the module's `composer.json` do nothing** — Composer never
  reads them for a dependency. The module's tests are autoloaded from the **site's**
  `autoload-dev`, which the scaffold writes.
- **`"version"` changed or removed** — `site/module-offices` no longer resolves. Leave it at
  `0.1.0`.
- **The image builds the panel without the module** — the `Dockerfile` copies `modules/` after
  `npm ci`, or not at all. Above.

## Moving it into the monorepo

When a second site needs the module, it moves. In short — the full list is §13 of the
[specification](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_SITE_MODULES.md#13-перенос-в-монорепу):

1. The id is free among the monorepo's modules; if not, rename it on the site first — the id is
   permissions, tables and MCP, so that is a data migration.
2. `modules/offices` without `js/` goes to `php/packages/module-offices`, `js/` to
   `packages/module-offices`.
3. The three names change (`site/` → `webx-ui/`, `Site\` → `WebxUi\`, `@site/` → `@webx-ui/`), the
   `"version"` field goes.
4. The npm half becomes a package of the monorepo: its own build into `dist` and `style.css`,
   `panel.style`, `workspace:^` dependencies, as `packages/module-faq`.
5. `tests/TestCase.php` is rewritten on Testbench; it is the only test file that changes.
6. What the monorepo asks of every module and a site module did without: the places a new package
   and a new section are registered (`CLAUDE.md` §5), ten languages, PHPStan, `AgentDocsCoverageTest`,
   not a word about the client anywhere, a spec, a guide, a changeset — and the first publication,
   which a person does.
7. The site switches to the package: delete `modules/offices`, `require` the package,
   `webx:panel --sync`, remove the old import, the `autoload-dev` and the `file:` dependency. The
   migrations are not repeated — the tables and the migration names are the same.
