# WebX UI — Composer packages

The Laravel half of WebX UI. Each directory under `packages/` is published to Packagist as
`webx-ui/<name>`; this directory is the development root that installs them all at once and runs
the tooling over them.

Nothing here is published as `webx-ui/monorepo` — Composer resolves a package from its own
repository, so every package is mirrored, read-only, to `github.com/webx-ui/<name>` by the
`PHP split` workflow. See [the release pipeline](../docs/architecture/WEBX_UI_PHP_RELEASE.md).

## Requirements

- PHP 8.3+ (CI runs 8.3 and 8.4)
- Composer 2

## Setup

```bash
cd php
composer install
```

| Command             | What it does                          |
| ------------------- | ------------------------------------- |
| `composer test`     | PHPUnit over every package's `tests/` |
| `composer lint`     | Pint in check mode (what CI runs)     |
| `composer lint:fix` | Pint, writing fixes                   |
| `composer analyse`  | PHPStan / Larastan, level 6           |

The gate before pushing is all three:

```bash
composer lint && composer analyse && composer test
```

## Seeing the panel

`scripts/php-dev-app.sh` provisions a Laravel application next to this checkout with the
packages linked from it; `scripts/build-panel.sh` builds `apps/admin-example` into its public
directory. Point `config/webx-admin.php` at the result:

```php
'assets' => ['/webx/webx.css', '/webx/webx.js'],
```

After that the panel answers on the real host, over HTTPS, with real cookies — which is the
only place the session and CSRF behave the way they will in production.

### Working on the packages against it

`scripts/link-panel.sh` points the application's panel packages at this checkout instead of the
registry, leaving everything else about the build alone — the application keeps using its own
Vite, its own entry and hashed filenames, the way a real site does.

```bash
scripts/link-panel.sh              # from this checkout
scripts/link-panel.sh --registry   # back to the published packages
```

After changing a package: `pnpm build` here, `npm run build` there.

## The smoke test

Those tests run under Testbench, where the providers are wired by hand, the database is sqlite
in memory and CSRF is switched off. A whole class of breakage lives outside that: a bad
`extra.laravel.providers`, a migration that only works on sqlite, a sign-in that cannot get
past CSRF, a panel that falls open once a deploy runs `config:cache`.

`scripts/php-smoke.sh` installs the packages into a **real Laravel application**, migrates,
creates an administrator, signs in over HTTP with a real CSRF token, and repeats the checks
with the config and route caches on. CI runs it against MariaDB on every pull request; locally
it defaults to sqlite:

```bash
scripts/php-smoke.sh
```

It is shallow on purpose — it proves the thing installs and the panel is shut to strangers,
not that the logic is right.

## Packages

| Package               | Purpose                                                    |
| --------------------- | ---------------------------------------------------------- |
| `webx-ui/admin`       | The frame: module contract, manifest, panel routes         |
| `webx-ui/mcp`         | What a module offers an AI agent, and the registry for it  |
| `webx-ui/module-auth` | Administrators, roles, sign-in — and what closes the panel |
| `webx-ui/nested-set`  | Nested set trees for Eloquent (`HasNestedSet`)             |

The roadmap for the rest lives in
[`docs/architecture/WEBX_UI_COMPOSER_PACKAGES.md`](../docs/architecture/WEBX_UI_COMPOSER_PACKAGES.md).

## Versions

All PHP packages share one version, the way `illuminate/*` does: one tag, one release, no
matrix of which package works with which. That version lives in `php/package.json` — a private
npm package that exists only so changesets can bump it alongside the JavaScript ones.

A package that depends on a sibling writes the constraint as `^<version>`;
`scripts/sync-php-version.mjs` rewrites those in the same commit as the bump, so they can never
drift.

`php/package.json` carries one devDependency it never uses: pnpm refuses to record a workspace
project that has none, and CI then fails on `pnpm install --frozen-lockfile`.

## Adding a package

1. `mkdir packages/<name>` with `composer.json` (`"name": "webx-ui/<name>"`), `src/`, `tests/`,
   `README.md`, `LICENSE` and a `.gitattributes` that `export-ignore`s `tests`.
2. Register it in the development root so the tooling sees it:
   - `php/composer.json` → `require` (`"webx-ui/<name>": "*"`) and `autoload-dev` for the test
     namespace,
   - `php/phpunit.xml.dist` → a `<directory>` in the test suite and in `<source>`,
   - `php/phpstan.neon.dist` → the `src` and `tests` paths, and `viewDirectories` if the
     package ships Blade views.
3. Create the public repository `webx-ui/<name>` and submit it to Packagist. The split
   workflow discovers the package from the directory listing and mints its own write token, so
   the repository is the only thing it cannot do for itself.

Step 3 is the only manual one, and it is described in
[the release pipeline](../docs/architecture/WEBX_UI_PHP_RELEASE.md).
