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

## Packages

| Package              | Purpose                                        |
| -------------------- | ---------------------------------------------- |
| `webx-ui/nested-set` | Nested set trees for Eloquent (`HasNestedSet`) |

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
   - `php/phpstan.neon.dist` → the `src` and `tests` paths.
3. Create the public repository `webx-ui/<name>`, grant the `PHP_SPLIT_TOKEN` write access to it,
   and submit it to Packagist. The split workflow discovers the package from the directory
   listing, but it cannot create the repository — and the token is scoped to named repositories,
   so a mirror missing from it fails the split with a 403.

Step 3 is the only manual one, and it is described in
[the release pipeline](../docs/architecture/WEBX_UI_PHP_RELEASE.md).
